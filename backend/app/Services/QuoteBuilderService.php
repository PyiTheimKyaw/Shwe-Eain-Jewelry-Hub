<?php

namespace App\Services;

use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuoteBuilderService
{
    public function __construct(
        protected PricingService $pricing
    ) {}

    /**
     * @param  array<int, array{product_id?: int|null, custom_title?: string|null, metal_type?: string|null, stone_details?: string|null, weight_grams: float|string, labor_fee_mmk?: float|string|null, gem_price_mmk?: float|string|null}>  $lines
     * @param  'draft'|'sent'  $initialStatus
     */
    public function createQuote(
        User $customer,
        GoldPriceSnapshot $gold,
        ExchangeRateSnapshot $fx,
        array $lines,
        ?int $inquiryId = null,
        ?string $notesInternal = null,
        ?\DateTimeInterface $validUntil = null,
        string $initialStatus = 'draft',
        ?float $depositPercentageOverride = null,
        ?float $depositAmountOverrideMmk = null
    ): Quote {
        return DB::transaction(function () use (
            $customer,
            $gold,
            $fx,
            $lines,
            $inquiryId,
            $notesInternal,
            $validUntil,
            $initialStatus,
            $depositPercentageOverride,
            $depositAmountOverrideMmk
        ) {
            $quote = Quote::create([
                'inquiry_id' => $inquiryId,
                'customer_user_id' => $customer->id,
                'gold_price_snapshot_id' => $gold->id,
                'exchange_rate_snapshot_id' => $fx->id,
                'quote_number' => $this->uniqueQuoteNumber(),
                'status' => $initialStatus === 'sent' ? 'sent' : 'draft',
                'total_mmk' => 0,
                'total_usd_snapshot' => 0,
                'valid_until' => $validUntil,
                'notes_internal' => $notesInternal,
                'deposit_percentage_override' => $depositPercentageOverride,
                'deposit_amount_override_mmk' => $depositAmountOverrideMmk,
            ]);

            $totalMmk = '0';
            $totalUsdSnapshot = '0';
            $goldPrice = (string) $gold->price_mmk_per_gram;

            foreach ($lines as $line) {
                $weight = (string) $line['weight_grams'];
                $labor = (string) ($line['labor_fee_mmk'] ?? 0);
                $gem = (string) ($line['gem_price_mmk'] ?? 0);

                $product = isset($line['product_id']) ? Product::query()->find($line['product_id']) : null;
                if ($product) {
                    $labor = (string) $product->labor_fee_mmk;
                    $gem = (string) ($product->gem_price_mmk !== null ? $product->gem_price_mmk : 0);
                }

                $metal = bcmul($weight, $goldPrice, 4);
                $lineTotalMmk = bcadd(bcadd($metal, $labor, 4), $gem, 4);
                $lineTotalUsdSnapshot = $this->pricing->mmkToUsd($lineTotalMmk, $fx);

                QuoteLineItem::create([
                    'quote_id' => $quote->id,
                    'product_id' => $product?->id,
                    'custom_title' => $line['custom_title'] ?? $product?->title,
                    'metal_type' => $line['metal_type'] ?? null,
                    'stone_details' => $line['stone_details'] ?? null,
                    'weight_grams' => $weight,
                    'gold_price_mmk_per_gram' => $goldPrice,
                    'metal_value_mmk' => $metal,
                    'labor_fee_mmk' => $labor,
                    'gem_price_mmk' => $gem,
                    'line_total_mmk' => $lineTotalMmk,
                    'line_total_usd_snapshot' => $lineTotalUsdSnapshot,
                ]);

                $totalMmk = bcadd($totalMmk, $lineTotalMmk, 4);
                $totalUsdSnapshot = bcadd($totalUsdSnapshot, $lineTotalUsdSnapshot, 4);
            }

            $quote->update([
                'total_mmk' => $totalMmk,
                'total_usd_snapshot' => $totalUsdSnapshot,
            ]);

            return $quote->fresh(['lineItems']);
        });
    }

    /**
     * Apply deterministic product/inquiry prefill without overriding explicit admin input.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function normalizeQuoteLines(array $lines, ?Inquiry $inquiry = null): array
    {
        $inquiryProductId = $inquiry?->product_id !== null ? (int) $inquiry->product_id : null;
        $productIds = [];
        if ($inquiryProductId !== null) {
            $productIds[] = $inquiryProductId;
        }

        foreach ($lines as $line) {
            $rawId = $line['product_id'] ?? null;
            if ($rawId === null || $rawId === '') {
                continue;
            }
            $parsed = is_numeric($rawId) ? (int) $rawId : null;
            if ($parsed !== null) {
                $productIds[] = $parsed;
            }
        }

        /** @var array<int, Product> $products */
        $products = Product::query()
            ->withTrashed()
            ->whereIn('id', array_values(array_unique($productIds)))
            ->get()
            ->keyBy('id')
            ->all();

        $normalized = [];
        foreach ($lines as $line) {
            $lineProductId = null;
            $rawId = $line['product_id'] ?? null;
            if ($rawId !== null && $rawId !== '') {
                $lineProductId = is_numeric($rawId) ? (int) $rawId : null;
            }

            $effectiveProductId = $lineProductId ?? $inquiryProductId;
            $effectiveProduct = $effectiveProductId !== null ? ($products[$effectiveProductId] ?? null) : null;
            $merged = $line;

            if ($lineProductId === null && $effectiveProductId !== null) {
                $merged['product_id'] = $effectiveProductId;
            } elseif ($lineProductId === null) {
                $merged['product_id'] = null;
            } else {
                $merged['product_id'] = $lineProductId;
            }

            if ($effectiveProduct !== null) {
                if ($this->isBlank($merged['custom_title'] ?? null)) {
                    $merged['custom_title'] = $effectiveProduct->title;
                }
                if ($this->isBlank($merged['weight_grams'] ?? null) && $effectiveProduct->weight_grams !== null) {
                    $merged['weight_grams'] = (string) $effectiveProduct->weight_grams;
                }
                if ($this->isBlank($merged['labor_fee_mmk'] ?? null)) {
                    $merged['labor_fee_mmk'] = (string) $effectiveProduct->labor_fee_mmk;
                }
                if ($this->isBlank($merged['gem_price_mmk'] ?? null)) {
                    $merged['gem_price_mmk'] = (string) ($effectiveProduct->gem_price_mmk ?? 0);
                }
            }

            $normalized[] = $merged;
        }

        return $normalized;
    }

    protected function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function readyMadeDefaultDepositPercentage(array $lines): float
    {
        $allReadyMade = false;
        foreach ($lines as $line) {
            $id = $line['product_id'] ?? null;
            if (! is_numeric($id)) {
                continue;
            }
            $product = Product::query()->withTrashed()->find((int) $id);
            if ($product === null) {
                continue;
            }
            $allReadyMade = true;
            if (! $product->is_ready_made) {
                return app(BusinessConfigService::class)->depositPercentage();
            }
        }

        if ($allReadyMade) {
            return 100.0;
        }

        return app(BusinessConfigService::class)->depositPercentage();
    }

    protected function uniqueQuoteNumber(): string
    {
        do {
            $n = 'Q-'.strtoupper(Str::random(10));
        } while (Quote::query()->where('quote_number', $n)->exists());

        return $n;
    }
}
