<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\FirebaseInquiryService;
use App\Services\MediaStorageService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderDepositController extends Controller
{
    public function __construct(
        protected MediaStorageService $mediaStorage,
        protected PushNotificationService $push
    ) {}

    public function store(Request $request, Order $order, FirebaseInquiryService $firebaseInquiry): JsonResponse
    {
        $this->authorize('uploadDepositProof', $order);

        $data = $request->validate([
            'proof' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,webp,heic'],
        ]);

        if ($order->deposit_amount_mmk === null) {
            return response()->json(['message' => 'This order has no deposit amount set.'], 422);
        }

        if ($order->deposit_instructions_sent_at === null) {
            return response()->json(['message' => 'Payment instructions have not been sent yet.'], 422);
        }

        $stored = $this->mediaStorage->storeImage($request->file('proof'), 'orders/'.$order->id.'/deposits');

        // Keep one deposit payment row per order phase; re-uploads refresh the same row.
        $payment = Payment::query()->firstOrNew([
            'order_id' => $order->id,
            'type' => Payment::TYPE_DEPOSIT,
        ]);

        if ($payment->exists &&
            ! in_array($payment->status, [Payment::STATUS_PENDING_PROOF, Payment::STATUS_REJECTED], true)
        ) {
            return response()->json([
                'message' => 'Payment proof cannot be uploaded in the current payment state.',
            ], 422);
        }

        $payment->amount_mmk = $order->deposit_amount_mmk;
        $payment->currency = 'MMK';
        $payment->status = Payment::STATUS_AWAITING_VERIFICATION;
        $payment->proof_storage_path = $stored['storage_path'];
        $payment->proof_mime = $request->file('proof')->getMimeType();
        $payment->submitted_at = now();
        $payment->rejection_reason = null;
        $payment->verified_at = null;
        $payment->verified_by_user_id = null;
        $payment->save();

        $inquiry = $order->resolveInquiry();
        if ($inquiry !== null) {
            $firebaseInquiry->publishPaymentProofImageMessage($inquiry, $payment);
            $firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        $admins = User::query()->where('role', UserRole::Admin->value)->get();
        $this->push->notifyUsers(
            $admins,
            'Deposit proof submitted',
            'A customer submitted deposit proof that requires verification.',
            [
                'category' => 'payment',
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]
        );

        return response()->json([
            'data' => $payment->fresh(),
        ], 201);
    }
}
