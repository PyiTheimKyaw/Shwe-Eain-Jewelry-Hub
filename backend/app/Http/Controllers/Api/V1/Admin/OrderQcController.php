<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FirebaseInquiryService;
use App\Services\OrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrderQcController extends Controller
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry,
        protected OrderLifecycleService $lifecycle
    ) {}

    public function approve(Order $order): JsonResponse
    {
        $this->authorize('qcApprove', $order);

        try {
            $order = $this->lifecycle->approveQc($order);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $inquiry = $order->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->publishSystemTextMessage(
                $inquiry,
                'Your order has passed Quality Control and is now Ready for Collection!'
            );
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return response()->json(['data' => $order->fresh()]);
    }
}
