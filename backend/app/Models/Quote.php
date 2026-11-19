<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = [
        'inquiry_id',
        'customer_user_id',
        'gold_price_snapshot_id',
        'exchange_rate_snapshot_id',
        'quote_number',
        'status',
        'total_mmk',
        'total_usd_snapshot',
        'valid_until',
        'notes_internal',
        'deposit_percentage_override',
        'deposit_amount_override_mmk',
    ];

    protected function casts(): array
    {
        return [
            'total_mmk' => 'decimal:4',
            'total_usd_snapshot' => 'decimal:4',
            'valid_until' => 'datetime',
            'deposit_percentage_override' => 'decimal:2',
            'deposit_amount_override_mmk' => 'decimal:4',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function goldPriceSnapshot(): BelongsTo
    {
        return $this->belongsTo(GoldPriceSnapshot::class);
    }

    public function exchangeRateSnapshot(): BelongsTo
    {
        return $this->belongsTo(ExchangeRateSnapshot::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
