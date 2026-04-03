<?php

namespace App\Services;

use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class CatalogProductPresenter
{
    public function __construct(
        protected PricingService $pricing
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        Product $product,
        bool $pricingConfigured,
        ?GoldPriceSnapshot $gold,
        ?ExchangeRateSnapshot $fx
    ): array {
        $product->loadMissing(['images', 'category']);

        $row = [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'title' => $product->title,
            'description' => $product->description,
            'weight_grams' => $product->weight_grams,
            'labor_fee_mmk' => $product->labor_fee_mmk,
            'gem_price_mmk' => $product->gem_price_mmk,
            'is_published' => $product->is_published,
            'is_ready_made' => $product->is_ready_made,
            'is_custom_order_template' => $product->is_custom_order_template,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'images' => $product->images->map(fn ($img) => [
                'id' => $img->id,
                'storage_path' => $img->storage_path,
                'url' => Storage::disk('public')->url($img->storage_path),
                'sort_order' => $img->sort_order,
            ])->values()->all(),
            'pricing' => null,
        ];

        if ($pricingConfigured && $gold && $fx) {
            $row['pricing'] = $this->pricingBreakdown($product, $gold, $fx);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pricingBreakdown(Product $product, GoldPriceSnapshot $gold, ExchangeRateSnapshot $fx): array
    {
        $weight = (string) $product->weight_grams;
        $goldPrice = (string) $gold->price_mmk_per_gram;
        $labor = (string) $product->labor_fee_mmk;
        $gem = (string) ($product->gem_price_mmk ?? 0);

        $metal = bcmul($weight, $goldPrice, 4);
        $lineTotalMmk = bcadd(bcadd($metal, $labor, 4), $gem, 4);
        $lineTotalUsd = $this->pricing->mmkToUsd($lineTotalMmk, $fx);

        return [
            'gold_price_snapshot_id' => $gold->id,
            'gold_price_mmk_per_gram' => $gold->price_mmk_per_gram,
            'exchange_rate_snapshot_id' => $fx->id,
            'mmk_per_usd' => $fx->mmk_per_usd,
            'metal_value_mmk' => $metal,
            'labor_fee_mmk' => $labor,
            'gem_price_mmk' => $gem,
            'line_total_mmk' => $lineTotalMmk,
            'line_total_usd' => $lineTotalUsd,
        ];
    }

    /**
     * @return array{pricing_configured: bool, weight_standards: array<string, mixed>}
     */
    public function catalogMeta(bool $pricingConfigured): array
    {
        return [
            'pricing_configured' => $pricingConfigured,
            'weight_standards' => $this->pricing->burmeseMassStandards(),
        ];
    }
}
