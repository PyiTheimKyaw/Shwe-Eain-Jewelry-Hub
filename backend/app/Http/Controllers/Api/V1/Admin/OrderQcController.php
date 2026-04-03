<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FirebaseInquiryService;
use Illuminate\Http\JsonResponse;

class OrderQcController extends Controller
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry
    ) {}

    public function approve(Order $order): JsonResponse
    {
        $this->authorize('qcApprove', $order);

        if ($order->status !== 'pending_qc') {
            return response()->json(['message' => 'Order is not awaiting quality review.'], 422);
        }

        $order->update(['status' => 'ready']);

        $inquiry = $order->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return response()->json(['data' => $order->fresh()]);
    }
}
