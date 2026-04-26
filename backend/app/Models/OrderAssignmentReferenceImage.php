<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAssignmentReferenceImage extends Model
{
    protected $fillable = [
        'order_assignment_id',
        'inquiry_image_candidate_id',
        'storage_path',
        'url',
        'note',
        'sort_order',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(OrderAssignment::class, 'order_assignment_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(InquiryImageCandidate::class, 'inquiry_image_candidate_id');
    }
}
