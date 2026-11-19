<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Services\AdminAuditLogService;
use App\Services\FirebaseInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrderPaymentInstructionController extends Controller
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry,
        protected AdminAuditLogService $auditLogs
    ) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorize('sendPaymentInstructions', $order);

        $data = $request->validate([
            'deposit_amount_mmk' => ['nullable', 'numeric', 'min:0'],
            'payment_profile_id' => ['required', 'integer', 'exists:payment_profiles,id'],
        ]);

        $profile = PaymentProfile::query()
            ->where('is_active', true)
            ->whereKey($data['payment_profile_id'])
            ->firstOrFail();

        if ($order->status !== 'awaiting_deposit') {
            return response()->json(['message' => 'Payment instructions can only be sent while awaiting deposit.'], 422);
        }

        if (isset($data['deposit_amount_mmk'])) {
            $order->deposit_amount_mmk = $data['deposit_amount_mmk'];
        }

        if ($order->deposit_amount_mmk === null) {
            return response()->json(['message' => 'Order has no deposit amount.'], 422);
        }

        $order->deposit_instructions_sent_at = Carbon::now();
        $order->save();

        $existing = Payment::query()
            ->where('order_id', $order->id)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->first();
        if ($existing !== null && ! in_array($existing->status, [Payment::STATUS_PENDING_PROOF, Payment::STATUS_REJECTED], true)) {
            return response()->json([
                'message' => 'Payment instructions cannot be resent while deposit verification is in progress or confirmed.',
            ], 422);
        }

        Payment::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'type' => Payment::TYPE_DEPOSIT,
            ],
            [
                'payment_profile_id' => $profile->id,
                'amount_mmk' => $order->deposit_amount_mmk ?? 0,
                'currency' => 'MMK',
                'status' => Payment::STATUS_PENDING_PROOF,
            ]
        );

        $inquiry = $order->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->publishPaymentInfoCard(
                $inquiry,
                $order->fresh(),
                $profile
            );
            $this->firebaseInquiry->publishTimelineLogMessage($inquiry, '--- Payment Instructions Provided ---');
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }
        $this->auditLogs->log(
            action: 'admin.order.payment_instructions_sent',
            actor: $request->user(),
            target: $order,
            category: 'workflow',
            context: [
                'payment_profile_id' => $profile->id,
                'deposit_amount_mmk' => (string) $order->deposit_amount_mmk,
                'inquiry_id' => $inquiry?->id,
            ],
        );

        return response()->json(['data' => $order->fresh(['payments'])]);
    }
}
