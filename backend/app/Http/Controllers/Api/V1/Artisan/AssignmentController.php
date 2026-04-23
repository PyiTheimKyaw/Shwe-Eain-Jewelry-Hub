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

    public function show(Request $request, OrderAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $assignment->load([
            'order.customer',
            'order.items.product.images',
            'order.quote.inquiry.attachments',
            'orderItem.product.images',
        ]);

        return response()->json(['data' => $assignment]);
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
