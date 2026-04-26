<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    protected $fillable = [
        'customer_user_id',
        'product_id',
        'message',
        'status',
        'firebase_thread_id',
        'notes_internal',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InquiryAttachment::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function timelineLogs(): HasMany
    {
        return $this->hasMany(InquiryTimelineLog::class);
    }

    public function imageCandidates(): HasMany
    {
        return $this->hasMany(InquiryImageCandidate::class);
    }
}
