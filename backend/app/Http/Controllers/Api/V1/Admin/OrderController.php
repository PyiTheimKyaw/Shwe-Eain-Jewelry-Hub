<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Payment;
use App\Models\Quote;
use App\Services\OrderLifecycleService;
use App\Services\OrderBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /** @var array<string, list<string>> */
    private const STATUS_TRANSITIONS = [
        'awaiting_deposit' => ['deposit_confirmed', 'cancelled'],
        'deposit_confirmed' => ['in_production', 'cancelled'],
        'in_production' => ['pending_qc', 'cancelled'],
        'pending_qc' => ['ready', 'in_production', 'cancelled'],
        'ready' => ['shipped', 'completed', 'cancelled'],
        'shipped' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        protected OrderBuilderService $orders,
        protected OrderLifecycleService $lifecycle
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
            'payments',
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
            'status' => ['sometimes', 'in:awaiting_deposit,deposit_confirmed,in_production,pending_qc,ready,shipped,completed,cancelled'],
            'shipping_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if (array_key_exists('status', $data)) {
            $nextStatus = $data['status'];
            $currentStatus = (string) $order->status;
            if ($nextStatus !== $currentStatus) {
                $allowed = self::STATUS_TRANSITIONS[$currentStatus] ?? [];
                if (! in_array($nextStatus, $allowed, true)) {
                    return response()->json([
                        'message' => "Invalid status transition: {$currentStatus} -> {$nextStatus}.",
                    ], 422);
                }

                if ($nextStatus === 'in_production') {
                    $depositConfirmed = Payment::query()
                        ->where('order_id', $order->id)
                        ->where('type', Payment::TYPE_DEPOSIT)
                        ->where('status', Payment::STATUS_CONFIRMED)
                        ->exists();
                    if (! $depositConfirmed) {
                        return response()->json([
                            'message' => 'Cannot start production without a confirmed deposit payment.',
                        ], 422);
                    }
                }

                if ($nextStatus === 'pending_qc') {
                    $inProgressAssignment = OrderAssignment::query()
                        ->where('order_id', $order->id)
                        ->where('status', 'in_progress')
                        ->exists();
                    if (! $inProgressAssignment) {
                        return response()->json([
                            'message' => 'Cannot move to quality review without an active in-progress assignment.',
                        ], 422);
                    }
                }
            }
        }

        $order->update($data);

        return response()->json(['data' => $order->fresh()]);
    }

    public function storeAssignment(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'artisan_user_id' => ['required', 'exists:users,id'],
            'task_scope' => ['required', 'in:full_order,line_item'],
            'order_item_id' => ['nullable', 'required_if:task_scope,line_item', 'exists:order_items,id'],
            'instructions' => ['nullable', 'required_if:task_scope,line_item', 'string', 'max:5000'],
        ]);

        try {
            $assignment = $this->lifecycle->assignArtisan(
                $order,
                (int) $data['artisan_user_id'],
                (string) $data['task_scope'],
                isset($data['order_item_id']) ? (int) $data['order_item_id'] : null,
                $data['instructions'] ?? null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['data' => $assignment->load('artisan')], 201);
    }
}
