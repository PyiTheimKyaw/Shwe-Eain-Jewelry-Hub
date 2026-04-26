<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\InquiryImageCandidate;
use App\Models\User;

class InquiryImageCandidateService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createFromUpload(
        Inquiry $inquiry,
        string $source,
        ?string $senderRole,
        ?string $storagePath,
        ?string $url,
        array $metadata = [],
        ?string $firebaseMessageId = null,
    ): InquiryImageCandidate {
        if ($firebaseMessageId === null || trim($firebaseMessageId) === '') {
            return InquiryImageCandidate::query()->create([
                'inquiry_id' => $inquiry->id,
                'source' => $source,
                'sender_role' => $senderRole,
                'storage_path' => $storagePath,
                'url' => $url,
                'metadata_json' => $metadata,
                'uploaded_at' => now(),
            ]);
        }

        return InquiryImageCandidate::query()->updateOrCreate(
            [
                'inquiry_id' => $inquiry->id,
                'firebase_message_id' => $firebaseMessageId,
            ],
            [
                'source' => $source,
                'sender_role' => $senderRole,
                'storage_path' => $storagePath,
                'url' => $url,
                'metadata_json' => $metadata,
                'uploaded_at' => now(),
            ],
        );
    }

    public function canUserAttachToInquiry(User $user, Inquiry $inquiry): bool
    {
        return (int) $inquiry->customer_user_id === (int) $user->id
            || $user->isAdmin()
            || $user->isArtisan();
    }
}
