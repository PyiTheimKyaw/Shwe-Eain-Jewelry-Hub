<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentVerificationService
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry
    ) {}

    public function confirm(User $user, Payment $payment): Payment
    {
        if ($payment->type !== Payment::TYPE_DEPOSIT) {
            abort(422, 'Unsupported payment type.');
        }

        if ($payment->status === Payment::STATUS_CONFIRMED) {
            return $payment;
        }

        $order = $payment->order;

        DB::transaction(function () use ($payment, $user, $order) {
            $payment->update([
                'status' => Payment::STATUS_CONFIRMED,
                'verified_at' => now(),
                'verified_by_user_id' => $user->id,
                'rejection_reason' => null,
            ]);

            $order->update(['status' => 'deposit_confirmed']);
        });

        $payment->refresh();
        $inquiry = $order->fresh()->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return $payment->load('order');
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
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return $payment->fresh();
    }
}
