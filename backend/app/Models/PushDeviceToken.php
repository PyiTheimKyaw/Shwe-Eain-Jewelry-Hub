<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'app_version',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
