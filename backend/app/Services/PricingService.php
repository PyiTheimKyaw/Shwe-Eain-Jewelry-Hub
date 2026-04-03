<?php

namespace App\Services;

use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves time-effective gold (MMK/gram) and FX (MMK per 1 USD) snapshots and records new ones.
 */
class PricingService
{
    /** Burmese mass kyat: grams per one kyat weight (not currency). */
    public const GRAMS_PER_BURMESE_KYAT_MASS = '16.606';

    public const PAE_PER_KYAT = 16;

    public const YWAY_PER_PAE = 8;

    public function currentGoldSnapshot(): ?GoldPriceSnapshot
    {
        $now = Carbon::now();

        return GoldPriceSnapshot::query()
            ->where('effective_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    public function currentFxSnapshot(): ?ExchangeRateSnapshot
    {
        $now = Carbon::now();

        return ExchangeRateSnapshot::query()
            ->where('effective_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Current spot gold price in MMK per gram, or null if no active snapshot exists.
     */
    public function currentGoldMmkPerGram(): ?string
    {
        $snap = $this->currentGoldSnapshot();

        return $snap ? (string) $snap->price_mmk_per_gram : null;
    }

    /**
     * MMK per 1 USD (same numeric value as legacy “usd_to_mmk”), or null if no active snapshot exists.
     */
    public function currentMmkPerUsd(): ?string
    {
        $snap = $this->currentFxSnapshot();

        return $snap ? (string) $snap->mmk_per_usd : null;
    }

    /**
     * @return array{gold: ?GoldPriceSnapshot, fx: ?ExchangeRateSnapshot, weight_standards: array{grams_per_burmese_kyat_mass: string, pae_per_kyat: int, yway_per_pae: int}}
     */
    public function currentPricingContext(): array
    {
        return [
            'gold' => $this->currentGoldSnapshot(),
            'fx' => $this->currentFxSnapshot(),
            'weight_standards' => $this->burmeseMassStandards(),
        ];
    }

    /**
     * @return array{grams_per_burmese_kyat_mass: string, pae_per_kyat: int, yway_per_pae: int}
     */
    public function burmeseMassStandards(): array
    {
        return [
            'grams_per_burmese_kyat_mass' => self::GRAMS_PER_BURMESE_KYAT_MASS,
            'pae_per_kyat' => self::PAE_PER_KYAT,
            'yway_per_pae' => self::YWAY_PER_PAE,
        ];
    }

    public function recordGoldPrice(float|string $priceMmkPerGram, ?Carbon $effectiveFrom = null, ?int $setByUserId = null): GoldPriceSnapshot
    {
        $effectiveFrom ??= Carbon::now();

        return DB::transaction(function () use ($priceMmkPerGram, $effectiveFrom, $setByUserId) {
            GoldPriceSnapshot::query()
                ->whereNull('effective_to')
                ->where('effective_from', '<', $effectiveFrom)
                ->update(['effective_to' => $effectiveFrom]);

            return GoldPriceSnapshot::create([
                'price_mmk_per_gram' => $priceMmkPerGram,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'set_by_user_id' => $setByUserId,
            ]);
        });
    }

    public function recordExchangeRate(float|string $mmkPerUsd, ?Carbon $effectiveFrom = null, ?int $setByUserId = null): ExchangeRateSnapshot
    {
        $effectiveFrom ??= Carbon::now();

        return DB::transaction(function () use ($mmkPerUsd, $effectiveFrom, $setByUserId) {
            ExchangeRateSnapshot::query()
                ->whereNull('effective_to')
                ->where('effective_from', '<', $effectiveFrom)
                ->update(['effective_to' => $effectiveFrom]);

            return ExchangeRateSnapshot::create([
                'mmk_per_usd' => $mmkPerUsd,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'set_by_user_id' => $setByUserId,
            ]);
        });
    }

    public function mmkToUsd(float|string $mmk, ExchangeRateSnapshot $fx): string
    {
        $rate = (string) $fx->mmk_per_usd;

        return bccomp($rate, '0', 4) === 0 ? '0' : bcdiv((string) $mmk, $rate, 4);
    }

    public function usdToMmk(float|string $usd, ExchangeRateSnapshot $fx): string
    {
        return bcmul((string) $usd, (string) $fx->mmk_per_usd, 4);
    }
}
