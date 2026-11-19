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
        'deposit_amount_mmk',
        'deposit_instructions_sent_at',
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
            'deposit_amount_mmk' => 'decimal:4',
            'deposit_instructions_sent_at' => 'datetime',
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

    public function resolveInquiry(): ?Inquiry
    {
        $this->loadMissing('quote');
        if ($this->quote === null || $this->quote->inquiry_id === null) {
            return null;
        }

        return Inquiry::query()->find($this->quote->inquiry_id);
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function depositPayment(): ?Payment
    {
        return $this->payments()->where('type', Payment::TYPE_DEPOSIT)->orderByDesc('id')->first();
    }

    public function isReadyMadeFulfillment(): bool
    {
        $this->loadMissing(['quote.lineItems.product', 'items.product']);

        if ($this->quote !== null && $this->quote->lineItems->isNotEmpty()) {
            return $this->quote->lineItems
                ->every(fn ($line) => $line->product_id !== null && $line->product?->is_ready_made === true);
        }

        if ($this->items->isNotEmpty()) {
            return $this->items
                ->every(fn ($item) => $item->product_id !== null && $item->product?->is_ready_made === true);
        }

        return false;
    }
}
