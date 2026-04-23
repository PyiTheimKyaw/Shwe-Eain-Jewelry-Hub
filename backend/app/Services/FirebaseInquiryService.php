<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\InquiryTimelineLog;
use App\Models\Order;
use App\Models\OrderProgressAttachment;
use App\Models\OrderProgressEvent;
use App\Models\PaymentProfile;
use App\Models\Quote;
use App\Models\Receipt;
use App\Models\User;
use Google\Cloud\Core\Timestamp;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            $pipeline = app(InquiryPipelinePresenter::class)->present($inquiry);
            $orderMeta = $pipeline['order'] ?? null;
            $db = $firestore->database();
            $doc = $db->collection('inquiry_threads')->document($inquiry->firebase_thread_id);
            $doc->set([
                'inquiry_id' => $inquiry->id,
                'customer_user_id' => $inquiry->customer_user_id,
                'product_id' => $inquiry->product_id,
                'inquiry_status' => $inquiry->status,
                'pipeline_step' => $pipeline['step'],
                'order_id' => $pipeline['order_id'],
                'order_number' => is_array($orderMeta) ? ($orderMeta['order_number'] ?? null) : null,
                'order_status' => $pipeline['order_status'],
                'active_quote_id' => $pipeline['active_quote_id'],
                'deposit_state' => $pipeline['deposit_state'] ?? null,
                'assignment_state' => $pipeline['assignment_state'] ?? null,
                'primary_assignment_id' => $pipeline['primary_assignment_id'] ?? null,
                'primary_artisan_user_id' => $pipeline['primary_artisan_user_id'] ?? null,
                'deposit_amount_mmk' => $pipeline['deposit_amount_mmk'] ?? null,
                'updated_at' => new Timestamp(new \DateTimeImmutable),
            ], ['merge' => true]);
        } catch (Throwable $e) {
            Log::debug('firebase.thread_sync_failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Server-side quote_card (Firestore Admin SDK bypasses security rules).
     */
    public function publishQuoteCardMessage(Inquiry $inquiry, Quote $quote): void
    {
        $firestore = $this->firestoreClient();
        if ($firestore === null) {
            Log::warning('firebase.quote_card_skipped', [
                'reason' => 'firestore_client_unavailable',
                'inquiry_id' => $inquiry->id,
                'quote_id' => $quote->id,
                'hint' => 'Install PHP ext-grpc and google/cloud-firestore so Laravel can write quote_card messages; until then the mobile app uses the API fallback.',
            ]);

            return;
        }

        if ($inquiry->firebase_thread_id === null) {
            return;
        }

        $quote->loadMissing('lineItems');

        $lines = [];
        foreach ($quote->lineItems as $line) {
            $lines[] = [
                'title' => (string) ($line->custom_title ?? 'Item'),
                'metal_mmk' => (string) $line->metal_value_mmk,
                'labor_mmk' => (string) $line->labor_fee_mmk,
                'gems_mmk' => (string) $line->gem_price_mmk,
                'line_total_mmk' => (string) $line->line_total_mmk,
            ];
        }

        $now = new \DateTimeImmutable;

        try {
            $db = $firestore->database();
            $messages = $db
                ->collection('inquiry_threads')
                ->document($inquiry->firebase_thread_id)
                ->collection('messages');

            $messages->add([
                'type' => 'quote_card',
                'sender_role' => 'admin',
                'created_at' => new Timestamp($now),
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'total_mmk' => (string) $quote->total_mmk,
                'total_usd_snapshot' => (string) $quote->total_usd_snapshot,
                'lines' => $lines,
                'text' => 'Quote '.$quote->quote_number,
            ]);
        } catch (Throwable $e) {
            Log::warning('firebase.quote_card_failed', [
                'message' => $e->getMessage(),
                'inquiry_id' => $inquiry->id,
                'quote_id' => $quote->id,
            ]);
        }
    }

    /**
     * Server-side payment_info_card (admin-only from client perspective).
     */
    public function publishPaymentInfoCard(Inquiry $inquiry, Order $order, PaymentProfile $profile): void
    {
        $firestore = $this->firestoreClient();
        if ($firestore === null) {
            Log::warning('firebase.payment_info_card_skipped', [
                'reason' => 'firestore_client_unavailable',
                'inquiry_id' => $inquiry->id,
                'order_id' => $order->id,
            ]);

            return;
        }

        if ($inquiry->firebase_thread_id === null) {
            return;
        }

        $now = new \DateTimeImmutable;

        $fields = $profile->toFirestorePaymentFields();

        try {
            $db = $firestore->database();
            $messages = $db
                ->collection('inquiry_threads')
                ->document($inquiry->firebase_thread_id)
                ->collection('messages');

            // Single payload so `type` is never lost (explicit string for all clients).
            $payload = [
                'type' => 'payment_info_card',
                'sender_role' => 'admin',
                'created_at' => new Timestamp($now),
                'order_id' => $order->id,
                'order_number' => (string) $order->order_number,
                'amount_mmk' => (string) ($order->deposit_amount_mmk ?? '0'),
                'text' => 'Payment instructions for order '.$order->order_number,
            ];
            foreach ($fields as $k => $v) {
                $payload[(string) $k] = $v;
            }
            foreach (app(InquiryPipelinePresenter::class)->paymentBalanceBreakdown($order) as $k => $v) {
                $payload[(string) $k] = $v;
            }
            $messages->add($payload);
        } catch (Throwable $e) {
            Log::warning('firebase.payment_info_card_failed', [
                'message' => $e->getMessage(),
                'inquiry_id' => $inquiry->id,
                'order_id' => $order->id,
            ]);
        }
    }

    /**
     * Server-side image message for artisan progress (public storage URLs).
     */
    public function publishProgressImageMessage(
        Inquiry $inquiry,
        OrderProgressEvent $event,
        OrderProgressAttachment $attachment,
    ): void {
        $firestore = $this->firestoreClient();
        if ($firestore === null) {
            Log::warning('firebase.progress_image_skipped', [
                'reason' => 'firestore_client_unavailable',
                'inquiry_id' => $inquiry->id,
                'event_id' => $event->id,
            ]);

            return;
        }

        if ($inquiry->firebase_thread_id === null) {
            return;
        }

        $now = new \DateTimeImmutable;
        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');
        $url = url($publicDisk->url($attachment->storage_path));

        try {
            $db = $firestore->database();
            $messages = $db
                ->collection('inquiry_threads')
                ->document($inquiry->firebase_thread_id)
                ->collection('messages');

            $messages->add([
                'type' => 'image',
                'sender_role' => 'admin',
                'created_at' => new Timestamp($now),
                'image_url' => $url,
                'caption' => 'Workshop · '.$event->stage.' · '.($event->note ?? ''),
                'stage' => $event->stage,
                'note' => $event->note,
                'order_id' => $event->order_id,
                'progress_event_id' => $event->id,
                'text' => 'Progress update',
            ]);
        } catch (Throwable $e) {
            Log::warning('firebase.progress_image_failed', [
                'message' => $e->getMessage(),
                'inquiry_id' => $inquiry->id,
                'event_id' => $event->id,
            ]);
        }
    }

    /**
     * Server-side plain text/system message.
     */
    public function publishSystemTextMessage(Inquiry $inquiry, string $message): void
    {
        $firestore = $this->firestoreClient();
        if ($firestore === null) {
            Log::warning('firebase.system_text_skipped', [
                'reason' => 'firestore_client_unavailable',
                'inquiry_id' => $inquiry->id,
            ]);

            return;
        }

        if ($inquiry->firebase_thread_id === null || trim($message) === '') {
            return;
        }

        try {
            $this->addThreadMessage($firestore, (string) $inquiry->firebase_thread_id, [
                'type' => 'text',
                'sender_role' => 'admin',
                'text' => trim($message),
                'created_at' => new Timestamp(new \DateTimeImmutable),
            ]);
        } catch (Throwable $e) {
            Log::warning('firebase.system_text_failed', [
                'message' => $e->getMessage(),
                'inquiry_id' => $inquiry->id,
            ]);
        }
    }

    /**
     * Server-side centered timeline log for major workflow milestones.
     */
    public function publishTimelineLogMessage(Inquiry $inquiry, string $message): void
    {
        $message = trim($message);
        if ($message === '') {
            return;
        }

        InquiryTimelineLog::query()->create([
            'inquiry_id' => $inquiry->id,
            'message' => $message,
            'occurred_at' => now(),
        ]);

        $firestore = $this->firestoreClient();
        if ($firestore === null) {
            Log::warning('firebase.timeline_log_skipped', [
                'reason' => 'firestore_client_unavailable',
                'inquiry_id' => $inquiry->id,
            ]);

            return;
        }

        if ($inquiry->firebase_thread_id === null) {
            return;
        }

        try {
            $this->addThreadMessage($firestore, (string) $inquiry->firebase_thread_id, [
                'type' => 'timeline_log',
                'sender_role' => 'system',
                'text' => $message,
                'created_at' => new Timestamp(new \DateTimeImmutable),
            ]);
        } catch (Throwable $e) {
            Log::warning('firebase.timeline_log_failed', [
                'message' => $e->getMessage(),
                'inquiry_id' => $inquiry->id,
            ]);
        }
    }

    /**
     * Server-side receipt card with CTA metadata for customer chat.
     */
    public function publishReceiptCardMessage(Inquiry $inquiry, Receipt $receipt): void
    {
        $firestore = $this->firestoreClient();
        if ($firestore === null) {
            Log::warning('firebase.receipt_card_skipped', [
                'reason' => 'firestore_client_unavailable',
                'inquiry_id' => $inquiry->id,
                'receipt_id' => $receipt->id,
            ]);

            return;
        }

        if ($inquiry->firebase_thread_id === null) {
            return;
        }

        try {
            $db = $firestore->database();
            $db->collection('inquiry_threads')
                ->document($inquiry->firebase_thread_id)
                ->collection('messages')
                ->add([
                    'type' => 'receipt_card',
                    'sender_role' => 'admin',
                    'created_at' => new Timestamp(new \DateTimeImmutable),
                    'text' => 'Your payment receipt is ready.',
                    'order_id' => $receipt->order_id,
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'amount_mmk' => (string) $receipt->amount_mmk,
                    'cta_label' => 'View Receipt',
                ]);
        } catch (Throwable $e) {
            Log::warning('firebase.receipt_card_failed', [
                'message' => $e->getMessage(),
                'inquiry_id' => $inquiry->id,
                'receipt_id' => $receipt->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function addThreadMessage(FirestoreContract $firestore, string $threadId, array $payload): void
    {
        $firestore->database()
            ->collection('inquiry_threads')
            ->document($threadId)
            ->collection('messages')
            ->add($payload);
    }
}
