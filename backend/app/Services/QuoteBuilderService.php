<?php

namespace App\Services;

use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
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
     * @param  array<int, array{product_id?: int|null, custom_title?: string|null, weight_grams: float|string, labor_fee_mmk?: float|string|null, gem_price_mmk?: float|string|null}>  $lines
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
        string $initialStatus = 'draft'
    ): Quote {
        return DB::transaction(function () use ($customer, $gold, $fx, $lines, $inquiryId, $notesInternal, $validUntil, $initialStatus) {
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

    protected function uniqueQuoteNumber(): string
    {
        do {
            $n = 'Q-'.strtoupper(Str::random(10));
        } while (Quote::query()->where('quote_number', $n)->exists());

        return $n;
    }
}
