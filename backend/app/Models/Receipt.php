<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'payment_id',
        'order_id',
        'customer_user_id',
        'currency_code',
        'amount_mmk',
        'amount_usd_snapshot',
        'branding_name',
        'pdf_storage_path',
        'sha256',
        'meta_json',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_mmk' => 'decimal:4',
            'amount_usd_snapshot' => 'decimal:4',
            'meta_json' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }
}

