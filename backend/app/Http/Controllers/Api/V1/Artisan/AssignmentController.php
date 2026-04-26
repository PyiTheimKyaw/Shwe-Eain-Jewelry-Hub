<?php

namespace App\Http\Controllers\Api\V1\Artisan;

use App\Http\Controllers\Controller;
use App\Models\OrderAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = OrderAssignment::query()
            ->where('artisan_user_id', $request->user()->id)
            ->with([
                'order.customer',
                'order.items.product.images',
                'order.quote.inquiry.attachments',
                'orderItem.product.images',
            ])
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($items);
    }

    public function stats(Request $request): JsonResponse
    {
        $artisanId = $request->user()->id;

        $activeLoad = OrderAssignment::query()
            ->where('artisan_user_id', $artisanId)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->count();

        $blockedCount = OrderAssignment::query()
            ->where('artisan_user_id', $artisanId)
            ->where('status', 'blocked')
            ->count();

        $completed = OrderAssignment::query()
            ->where('artisan_user_id', $artisanId)
            ->where('status', 'done')
            ->count();

        return response()->json([
            'data' => [
                'current_active_load' => $activeLoad,
                'blocked_count' => $blockedCount,
                'total_completed_jobs' => $completed,
            ],
        ]);
    }

    public function show(Request $request, OrderAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $assignment->load([
            'order.customer',
            'order.items.product.images',
            'order.quote.inquiry.attachments',
            'orderItem.product.images',
        ]);

        $latestAdminFeedback = $assignment->order
            ?->progressEvents()
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->latest('id')
            ->value('note');

        return response()->json([
            'data' => [
                ...$assignment->toArray(),
                'latest_admin_feedback' => $latestAdminFeedback,
            ],
        ]);
    }

    public function update(Request $request, OrderAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);

        $data = $request->validate([
            'status' => ['required', 'in:assigned,in_progress,blocked,done'],
        ]);

        $assignment->update($data);

        return response()->json(['data' => $assignment->fresh()]);
    }
}
