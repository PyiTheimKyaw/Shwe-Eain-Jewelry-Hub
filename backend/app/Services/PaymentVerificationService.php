<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentVerificationService
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry,
        protected ReceiptService $receipts
    ) {}

    public function confirm(User $user, Payment $payment): Payment
    {
        if ($payment->type !== Payment::TYPE_DEPOSIT) {
            abort(422, 'Unsupported payment type.');
        }

        $order = $payment->order;
        $alreadyConfirmed = $payment->status === Payment::STATUS_CONFIRMED;

        if (! $alreadyConfirmed) {
            DB::transaction(function () use ($payment, $user, $order) {
                $payment->update([
                    'status' => Payment::STATUS_CONFIRMED,
                    'verified_at' => now(),
                    'verified_by_user_id' => $user->id,
                    'rejection_reason' => null,
                ]);

                $order->update(['status' => 'deposit_confirmed']);
            });
        } elseif ($order->status !== 'deposit_confirmed') {
            $order->update(['status' => 'deposit_confirmed']);
        }

        $hadReceiptBeforeIssue = $payment->receipt()->exists();
        $payment->refresh();

        $issuedReceipt = null;
        try {
            $issuedReceipt = $this->receipts->issueForConfirmedPayment($payment);
        } catch (Throwable $e) {
            Log::warning('receipt.issue_failed', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);
        }

        $inquiry = $order->fresh()->resolveInquiry();
        if ($inquiry !== null) {
            $shouldBroadcastUpdate = ! $alreadyConfirmed || ! $hadReceiptBeforeIssue;
            if ($issuedReceipt !== null && $shouldBroadcastUpdate) {
                $this->firebaseInquiry->publishReceiptCardMessage($inquiry, $issuedReceipt);
            }
            if ($shouldBroadcastUpdate) {
                $this->firebaseInquiry->publishTimelineLogMessage($inquiry, '--- Deposit Confirmed ---');
                $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
            }
        }

        return $payment->load(['order', 'receipt']);
    }

    public function reject(Payment $payment, ?string $reason): Payment
    {
        $payment->update([
            'status' => Payment::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'verified_at' => null,
            'verified_by_user_id' => null,
        ]);

        $inquiry = $payment->order->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->publishTimelineLogMessage($inquiry, '--- Payment Rejected ---');
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return $payment->fresh();
    }
}
