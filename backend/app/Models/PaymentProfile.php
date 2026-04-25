<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel',
        'institution_name',
        'account_name',
        'account_number',
        'wallet_id',
        'qr_image_path',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return array<string, string|int>
     */
    public function toFirestorePaymentFields(): array
    {
        $channel = $this->channel;
        $digital = in_array($channel, [PaymentChannel::KPay, PaymentChannel::KBZ, PaymentChannel::CB], true);

        $kpayId = '';
        if ($this->wallet_id) {
            $kpayId = $this->wallet_id;
        } elseif ($digital) {
            $kpayId = (string) ($this->account_number ?? '');
        }

        $bankName = match ($channel) {
            PaymentChannel::Bank => (string) ($this->institution_name ?? $this->name),
            PaymentChannel::KPay => 'KPay',
            PaymentChannel::KBZ => 'KBZ Pay',
            PaymentChannel::CB => 'CB Bank',
            default => $this->name,
        };

        $qrUrl = '';
        if ($this->qr_image_path) {
            $qrUrl = app(\App\Services\MediaStorageService::class)->publicUrl($this->qr_image_path);
        }

        return [
            'payment_profile_id' => $this->id,
            'payment_method_label' => $this->name,
            'channel' => $channel->value,
            'kpay_id' => $kpayId,
            'bank_name' => $bankName,
            'bank_account_number' => (string) ($this->account_number ?? ''),
            'bank_account_name' => (string) ($this->account_name ?? ''),
            'qr_image_url' => $qrUrl,
        ];
    }

    /**
     * @param  Builder<PaymentProfile>  $query
     * @return Builder<PaymentProfile>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<PaymentProfile>  $query
     * @return Builder<PaymentProfile>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
