<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProgressAttachment extends Model
{
    protected $fillable = [
        'progress_event_id',
        'storage_path',
        'mime_type',
    ];

    public function progressEvent(): BelongsTo
    {
        return $this->belongsTo(OrderProgressEvent::class, 'progress_event_id');
    }
}
