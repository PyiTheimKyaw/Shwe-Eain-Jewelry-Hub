<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'title_snapshot',
        'metal_type_snapshot',
        'stone_details_snapshot',
        'weight_grams',
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
            'metal_value_mmk' => 'decimal:4',
            'labor_fee_mmk' => 'decimal:4',
            'gem_price_mmk' => 'decimal:4',
            'line_total_mmk' => 'decimal:4',
            'line_total_usd_snapshot' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class);
    }
}
