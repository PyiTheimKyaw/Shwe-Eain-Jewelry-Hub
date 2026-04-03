<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLineItem extends Model
{
    protected $fillable = [
        'quote_id',
        'product_id',
        'custom_title',
        'weight_grams',
        'gold_price_mmk_per_gram',
        'metal_value_mmk',
        'labor_fee_mmk',
        'gem_price_mmk',
        'line_total_mmk',
        'line_total_usd_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:4',
            'gold_price_mmk_per_gram' => 'decimal:4',
            'metal_value_mmk' => 'decimal:4',
            'labor_fee_mmk' => 'decimal:4',
            'gem_price_mmk' => 'decimal:4',
            'line_total_mmk' => 'decimal:4',
            'line_total_usd_snapshot' => 'decimal:4',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
