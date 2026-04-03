<?php

namespace App\Http\Controllers\Api\V1\Artisan;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Services\FirebaseInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderWorkflowController extends Controller
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry
    ) {}

    public function start(Order $order): JsonResponse
    {
        $this->authorize('startAsArtisan', $order);

        $assignment = OrderAssignment::query()
            ->where('order_id', $order->id)
            ->where('artisan_user_id', auth()->id())
            ->where('status', 'assigned')
            ->firstOrFail();

        DB::transaction(function () use ($order, $assignment) {
            $order->update(['status' => 'in_production']);
            $assignment->update(['status' => 'in_progress']);
        });

        $inquiry = $order->fresh()->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return response()->json(['data' => $order->fresh(['assignments'])]);
    }

    public function readyForReview(Order $order): JsonResponse
    {
        $this->authorize('markReadyForReview', $order);

        $assignment = OrderAssignment::query()
            ->where('order_id', $order->id)
            ->where('artisan_user_id', auth()->id())
            ->where('status', 'in_progress')
            ->firstOrFail();

        DB::transaction(function () use ($order, $assignment) {
            $order->update(['status' => 'pending_qc']);
            $assignment->update(['status' => 'done']);
        });

        $inquiry = $order->fresh()->resolveInquiry();
        if ($inquiry !== null) {
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return response()->json(['data' => $order->fresh(['assignments'])]);
    }
}
