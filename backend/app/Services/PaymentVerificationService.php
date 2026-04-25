<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentVerificationService
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry,
        protected ReceiptService $receipts,
        protected AdminAuditLogService $auditLogs,
        protected BusinessConfigService $businessConfig
    ) {}

    public function confirm(User $user, Payment $payment): Payment
    {
        if ($payment->type !== Payment::TYPE_DEPOSIT) {
            abort(422, 'Unsupported payment type.');
        }

        if (! in_array($payment->status, [Payment::STATUS_AWAITING_VERIFICATION, Payment::STATUS_CONFIRMED], true)) {
            abort(422, 'Only payments awaiting verification can be confirmed.');
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
            $this->auditLogs->log(
                action: 'admin.payment.confirmed',
                actor: $user,
                target: $payment,
                category: 'workflow',
                context: [
                    'order_id' => $payment->order_id,
                    'amount_mmk' => (string) $payment->amount_mmk,
                ],
            );
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
                $this->firebaseInquiry->publishTimelineLogMessage($inquiry, $this->businessConfig->paymentAcceptTemplate());
                $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
            }
        }

        return $payment->load(['order', 'receipt']);
    }

    public function reject(Payment $payment, ?string $reason): Payment
    {
        if ($payment->type !== Payment::TYPE_DEPOSIT) {
            abort(422, 'Unsupported payment type.');
        }

        if ($payment->status === Payment::STATUS_CONFIRMED) {
            abort(422, 'A confirmed payment cannot be rejected.');
        }

        if ($payment->status !== Payment::STATUS_AWAITING_VERIFICATION) {
            abort(422, 'Only payments awaiting verification can be rejected.');
        }

        $payment->update([
            'status' => Payment::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'verified_at' => null,
            'verified_by_user_id' => null,
        ]);
        $this->auditLogs->log(
            action: 'admin.payment.rejected',
            actor: Auth::user(),
            target: $payment,
            category: 'workflow',
            context: [
                'order_id' => $payment->order_id,
                'reason' => $reason,
            ],
        );

        $inquiry = $payment->order->resolveInquiry();
        if ($inquiry !== null) {
            $cleanReason = trim((string) $reason) !== '' ? trim((string) $reason) : 'No reason specified';
            $message = str_replace('{reason}', $cleanReason, $this->businessConfig->paymentRejectTemplate());
            $this->firebaseInquiry->publishTimelineLogMessage($inquiry, $message);
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return $payment->fresh();
    }
}
