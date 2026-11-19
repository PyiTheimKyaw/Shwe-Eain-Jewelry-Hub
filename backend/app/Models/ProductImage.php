<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $appends = [
        'url',
    ];

    protected $fillable = [
        'product_id',
        'storage_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return app(MediaStorageService::class)->publicUrl((string) $this->storage_path);
    }
}
