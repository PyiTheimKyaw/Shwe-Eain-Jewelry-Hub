<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRateSnapshot extends Model
{
    protected $fillable = [
        'mmk_per_usd',
        'effective_from',
        'effective_to',
        'set_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'mmk_per_usd' => 'decimal:4',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
