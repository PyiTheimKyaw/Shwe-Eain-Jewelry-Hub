<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InquiryImageCandidate extends Model
{
    protected $fillable = [
        'inquiry_id',
        'source',
        'sender_role',
        'storage_path',
        'url',
        'firebase_message_id',
        'metadata_json',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'uploaded_at' => 'datetime',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function assignmentReferences(): HasMany
    {
        return $this->hasMany(OrderAssignmentReferenceImage::class);
    }
}
