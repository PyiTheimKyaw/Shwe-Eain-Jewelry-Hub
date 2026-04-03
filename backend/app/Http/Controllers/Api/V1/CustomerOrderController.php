<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $items = Order::query()
            ->where('customer_user_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['items', 'assignments.artisan', 'progressEvents.attachments', 'progressEvents.artisan']);

        return response()->json(['data' => $order]);
    }
}
