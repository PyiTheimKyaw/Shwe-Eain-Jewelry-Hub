<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProgressAttachment extends Model
{
    protected $fillable = [
        'progress_event_id',
        'storage_path',
        'mime_type',
    ];

    protected $appends = [
        'url',
    ];

    public function getUrlAttribute(): string
    {
        return app(MediaStorageService::class)->publicUrl(
            (string) ($this->attributes['storage_path'] ?? ''),
        );
    }

    public function progressEvent(): BelongsTo
    {
        return $this->belongsTo(OrderProgressEvent::class, 'progress_event_id');
    }
}
