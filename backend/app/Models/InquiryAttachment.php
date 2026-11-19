<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryAttachment extends Model
{
    protected $fillable = [
        'inquiry_id',
        'storage_path',
        'mime_type',
    ];

    protected $appends = ['url'];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function getUrlAttribute(): string
    {
        return app(MediaStorageService::class)->publicUrl($this->storage_path);
    }
}
