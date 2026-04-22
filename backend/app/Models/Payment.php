<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    public const TYPE_DEPOSIT = 'deposit';

    public const STATUS_PENDING_PROOF = 'pending_proof';

    public const STATUS_AWAITING_VERIFICATION = 'awaiting_verification';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'payment_profile_id',
        'type',
        'amount_mmk',
        'currency',
        'status',
        'proof_storage_path',
        'proof_mime',
        'submitted_at',
        'verified_at',
        'verified_by_user_id',
        'rejection_reason',
        'notes_internal',
    ];

    protected function casts(): array
    {
        return [
            'amount_mmk' => 'decimal:4',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentProfile(): BelongsTo
    {
        return $this->belongsTo(PaymentProfile::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
