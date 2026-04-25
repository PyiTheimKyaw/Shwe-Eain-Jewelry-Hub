<?php

namespace App\Services;

use App\Models\BusinessConfig;

class BusinessConfigService
{
    public function current(): BusinessConfig
    {
        return BusinessConfig::query()->firstOrCreate(
            ['singleton_key' => 'default'],
            [
                'default_deposit_percentage' => (float) config('jewelry.default_deposit_percentage', 50),
                'payment_at_risk_hours' => 4,
                'order_at_risk_hours' => 24,
                'currency_refresh_interval_minutes' => 15,
                'payment_accept_template' => '--- Deposit Confirmed ---',
                'payment_reject_template' => '--- Payment Rejected --- | Reason: {reason}',
            ]
        );
    }

    public function depositPercentage(): float
    {
        return (float) $this->current()->default_deposit_percentage;
    }

    public function paymentRiskHours(): int
    {
        return (int) $this->current()->payment_at_risk_hours;
    }

    public function orderRiskHours(): int
    {
        return (int) $this->current()->order_at_risk_hours;
    }

    public function currencyRefreshIntervalSeconds(): int
    {
        $minutes = max(1, (int) $this->current()->currency_refresh_interval_minutes);

        return $minutes * 60;
    }

    public function paymentAcceptTemplate(): string
    {
        return (string) ($this->current()->payment_accept_template ?: '--- Deposit Confirmed ---');
    }

    public function paymentRejectTemplate(): string
    {
        return (string) ($this->current()->payment_reject_template ?: '--- Payment Rejected --- | Reason: {reason}');
    }
}
