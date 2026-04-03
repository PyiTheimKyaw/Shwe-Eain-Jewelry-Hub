<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderProgressEvent extends Model
{
    protected $fillable = [
        'order_id',
        'artisan_user_id',
        'stage',
        'note',
        'firebase_event_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OrderProgressAttachment::class, 'progress_event_id');
    }
}
