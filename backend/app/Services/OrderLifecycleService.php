<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderLifecycleService
{
    /**
     * @throws ValidationException
     */
    public function assignArtisan(
        Order $order,
        int $artisanUserId,
        string $taskScope,
        ?int $orderItemId = null,
        ?string $instructions = null
    ): OrderAssignment {
        if ($taskScope === 'line_item' && $orderItemId !== null) {
            $belongs = $order->items()->whereKey($orderItemId)->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'Order item does not belong to this order.',
                ]);
            }
        }

        if ($order->status !== 'deposit_confirmed') {
            throw ValidationException::withMessages([
                'order' => 'Deposit must be confirmed before assigning an artisan.',
            ]);
        }

        $depositOk = Payment::query()
            ->where('order_id', $order->id)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->exists();
        if (! $depositOk) {
            throw ValidationException::withMessages([
                'order' => 'A confirmed deposit payment is required.',
            ]);
        }

        $artisan = User::query()->findOrFail($artisanUserId);
        if (! $artisan->isArtisan()) {
            throw ValidationException::withMessages([
                'artisan_user_id' => 'User is not an artisan.',
            ]);
        }

        return OrderAssignment::create([
            'order_id' => $order->id,
            'artisan_user_id' => $artisanUserId,
            'task_scope' => $taskScope,
            'order_item_id' => $orderItemId,
            'instructions' => $instructions,
            'status' => 'assigned',
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function approveQc(Order $order): Order
    {
        if ($order->status !== 'pending_qc') {
            throw ValidationException::withMessages([
                'order' => 'Order is not awaiting quality review.',
            ]);
        }

        $order->update(['status' => 'ready']);

        return $order->fresh();
    }

    /**
     * @throws ValidationException
     */
    public function startProduction(Order $order, int $artisanUserId): OrderAssignment
    {
        if ($order->status !== 'deposit_confirmed') {
            throw ValidationException::withMessages([
                'order' => 'Order must be deposit confirmed before starting production.',
            ]);
        }

        $hasConfirmedDeposit = Payment::query()
            ->where('order_id', $order->id)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->exists();
        if (! $hasConfirmedDeposit) {
            throw ValidationException::withMessages([
                'order' => 'A confirmed deposit payment is required before starting production.',
            ]);
        }

        $assignment = OrderAssignment::query()
            ->where('order_id', $order->id)
            ->where('artisan_user_id', $artisanUserId)
            ->where('status', 'assigned')
            ->first();
        if ($assignment === null) {
            throw ValidationException::withMessages([
                'assignment' => 'No assigned artisan task found for this order.',
            ]);
        }

        $order->update(['status' => 'in_production']);
        $assignment->update(['status' => 'in_progress']);

        return $assignment->fresh();
    }

    /**
     * @throws ValidationException
     */
    public function markReadyForReview(Order $order, int $artisanUserId): OrderAssignment
    {
        if ($order->status !== 'in_production') {
            throw ValidationException::withMessages([
                'order' => 'Only in-production orders can be marked ready for review.',
            ]);
        }

        $assignment = OrderAssignment::query()
            ->where('order_id', $order->id)
            ->where('artisan_user_id', $artisanUserId)
            ->where('status', 'in_progress')
            ->first();
        if ($assignment === null) {
            throw ValidationException::withMessages([
                'assignment' => 'No in-progress artisan task found for this order.',
            ]);
        }

        $order->update(['status' => 'pending_qc']);
        $assignment->update(['status' => 'done']);

        return $assignment->fresh();
    }
}

