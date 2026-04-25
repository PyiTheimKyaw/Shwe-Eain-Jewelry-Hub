<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessConfig extends Model
{
    protected $fillable = [
        'singleton_key',
        'default_deposit_percentage',
        'payment_at_risk_hours',
        'order_at_risk_hours',
        'currency_refresh_interval_minutes',
        'payment_accept_template',
        'payment_reject_template',
    ];

    protected function casts(): array
    {
        return [
            'default_deposit_percentage' => 'float',
            'payment_at_risk_hours' => 'integer',
            'order_at_risk_hours' => 'integer',
            'currency_refresh_interval_minutes' => 'integer',
        ];
    }
}
