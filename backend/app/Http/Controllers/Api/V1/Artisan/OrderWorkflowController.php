<?php

namespace App\Http\Controllers\Api\V1\Artisan;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FirebaseInquiryService;
use App\Services\OrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrderWorkflowController extends Controller
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry,
        protected OrderLifecycleService $lifecycle
    ) {}

    public function start(Order $order): JsonResponse
    {
        $this->authorize('startAsArtisan', $order);
        try {
            $this->lifecycle->startProduction($order, (int) request()->user()->getKey());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $inquiry = $order->fresh()->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return response()->json(['data' => $order->fresh(['assignments'])]);
    }

    public function readyForReview(Order $order): JsonResponse
    {
        $this->authorize('markReadyForReview', $order);
        try {
            $this->lifecycle->markReadyForReview($order, (int) request()->user()->getKey());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $inquiry = $order->fresh()->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return response()->json(['data' => $order->fresh(['assignments'])]);
    }
}
