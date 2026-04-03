<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'customer_user_id',
        'quote_id',
        'order_number',
        'status',
        'total_mmk',
        'total_usd_snapshot',
        'exchange_rate_snapshot_id',
        'shipping_notes',
    ];

    protected function casts(): array
    {
        return [
            'total_mmk' => 'decimal:4',
            'total_usd_snapshot' => 'decimal:4',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function exchangeRateSnapshot(): BelongsTo
    {
        return $this->belongsTo(ExchangeRateSnapshot::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class);
    }

    public function progressEvents(): HasMany
    {
        return $this->hasMany(OrderProgressEvent::class);
    }
}
