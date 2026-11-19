<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'sku',
        'title',
        'description',
        'weight_grams',
        'labor_fee_mmk',
        'gem_price_mmk',
        'is_published',
        'is_ready_made',
        'is_custom_order_template',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:4',
            'labor_fee_mmk' => 'decimal:4',
            'gem_price_mmk' => 'decimal:4',
            'is_published' => 'boolean',
            'is_ready_made' => 'boolean',
            'is_custom_order_template' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeReadyMade($query)
    {
        return $query->where('is_ready_made', true);
    }
}
