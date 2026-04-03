<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Quote;
use App\Models\User;
use App\Services\OrderBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderBuilderService $orders
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = Order::query()->with(['customer', 'items'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json($q->paginate(30));
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'customer',
            'items',
            'assignments.artisan',
            'progressEvents.attachments',
            'progressEvents.artisan',
            'quote',
        ]);

        return response()->json(['data' => $order]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quote_id' => ['required', 'exists:quotes,id'],
        ]);

        $quote = Quote::query()->findOrFail($data['quote_id']);
        $order = $this->orders->createFromQuote($quote);

        return response()->json(['data' => $order], 201);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:pending,confirmed,in_production,qa,ready,shipped,completed,cancelled'],
            'shipping_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $order->update($data);

        return response()->json(['data' => $order->fresh()]);
    }

    public function storeAssignment(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'artisan_user_id' => ['required', 'exists:users,id'],
            'task_scope' => ['required', 'in:full_order,line_item'],
            'order_item_id' => ['nullable', 'required_if:task_scope,line_item', 'exists:order_items,id'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['task_scope'] === 'line_item' && $data['order_item_id']) {
            $belongs = $order->items()->whereKey($data['order_item_id'])->exists();
            if (! $belongs) {
                return response()->json(['message' => 'Order item does not belong to this order.'], 422);
            }
        }

        $artisan = User::query()->findOrFail($data['artisan_user_id']);
        if (! $artisan->isArtisan()) {
            return response()->json(['message' => 'User is not an artisan.'], 422);
        }

        $assignment = OrderAssignment::create([
            'order_id' => $order->id,
            'artisan_user_id' => $data['artisan_user_id'],
            'task_scope' => $data['task_scope'],
            'order_item_id' => $data['order_item_id'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'status' => 'assigned',
        ]);

        return response()->json(['data' => $assignment->load('artisan')], 201);
    }
}
