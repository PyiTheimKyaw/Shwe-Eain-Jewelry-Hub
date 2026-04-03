<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\User;
use Google\Cloud\Core\Timestamp;
use Kreait\Firebase\Contract\Auth as FirebaseAuthContract;
use Kreait\Firebase\Contract\Firestore as FirestoreContract;
use Kreait\Firebase\Factory;
use RuntimeException;
use Throwable;

/**
 * Firebase custom tokens for inquiry chat + optional Firestore thread metadata.
 */
class FirebaseInquiryService
{
    private ?FirebaseAuthContract $auth = null;

    private ?FirestoreContract $firestore = null;

    private bool $firestoreResolved = false;

    public function __construct(
        private readonly ?Factory $firebase,
    ) {
        if ($this->firebase !== null) {
            $this->auth = $this->firebase->createAuth();
            // Firestore is optional (requires google/cloud-firestore + often ext-grpc). Lazy-load so
            // custom token minting works with Auth only.
        }
    }

    /**
     * @return FirestoreContract|null Null if unavailable or dependencies missing (e.g. ext-grpc).
     */
    private function firestoreClient(): ?FirestoreContract
    {
        if ($this->firestoreResolved) {
            return $this->firestore;
        }
        $this->firestoreResolved = true;
        if ($this->firebase === null) {
            return null;
        }
        try {
            $this->firestore = $this->firebase->createFirestore();
        } catch (Throwable) {
            $this->firestore = null;
        }

        return $this->firestore;
    }

    public function isConfigured(): bool
    {
        return $this->firebase !== null && $this->auth !== null;
    }

    public function mintChatToken(User $user, Inquiry $inquiry): string
    {
        if ($this->auth === null) {
            throw new RuntimeException('Firebase is not configured.');
        }

        if ($inquiry->firebase_thread_id === null || $inquiry->firebase_thread_id === '') {
            throw new RuntimeException('Inquiry has no firebase_thread_id.');
        }

        $uid = 'laravel_'.$user->id;

        $claims = [
            'role' => $user->role->value,
            'thread_id' => $inquiry->firebase_thread_id,
            'inquiry_id' => $inquiry->id,
        ];

        $token = $this->auth->createCustomToken($uid, $claims);

        return $token->toString();
    }

    /**
     * Upserts Firestore metadata for security rules (optional; safe to skip if Firestore unavailable).
     */
    public function syncInquiryThreadDocument(Inquiry $inquiry): void
    {
        $firestore = $this->firestoreClient();
        if ($firestore === null || $inquiry->firebase_thread_id === null) {
            return;
        }

        try {
            $db = $firestore->database();
            $doc = $db->collection('inquiry_threads')->document($inquiry->firebase_thread_id);
            $doc->set([
                'inquiry_id' => $inquiry->id,
                'customer_user_id' => $inquiry->customer_user_id,
                'product_id' => $inquiry->product_id,
                'updated_at' => new Timestamp(new \DateTimeImmutable),
            ], ['merge' => true]);
        } catch (Throwable) {
            // Non-fatal: MySQL remains source of truth; log in production if needed.
        }
    }
}
