<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderAssignmentReferenceImage;
use App\Models\InquiryImageCandidate;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderLifecycleService
{
    public function __construct(
        protected AdminAuditLogService $auditLogs,
        protected OrderSettlementService $settlement
    ) {}

    /**
     * @throws ValidationException
     */
    public function assignArtisan(
        Order $order,
        int $artisanUserId,
        string $taskScope,
        ?int $orderItemId = null,
        ?string $instructions = null,
        ?string $targetDeadlineAt = null,
        string $priorityLevel = 'normal',
        array $referenceImageIds = [],
    ): OrderAssignment {
        if ($order->isReadyMadeFulfillment()) {
            throw ValidationException::withMessages([
                'order' => 'Ready-made orders skip artisan assignment and production stages.',
            ]);
        }

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

        $assignment = OrderAssignment::create([
            'order_id' => $order->id,
            'artisan_user_id' => $artisanUserId,
            'task_scope' => $taskScope,
            'order_item_id' => $orderItemId,
            'instructions' => $instructions,
            'target_deadline_at' => $targetDeadlineAt,
            'priority_level' => $priorityLevel,
            'status' => 'assigned',
        ]);

        $candidateRows = $referenceImageIds === []
            ? collect()
            : InquiryImageCandidate::query()
                ->whereIn('id', $referenceImageIds)
                ->where('inquiry_id', $order->quote?->inquiry_id)
                ->orderByDesc('uploaded_at')
                ->orderByDesc('id')
                ->get();
        foreach ($candidateRows->values() as $index => $candidate) {
            OrderAssignmentReferenceImage::query()->create([
                'order_assignment_id' => $assignment->id,
                'inquiry_image_candidate_id' => $candidate->id,
                'storage_path' => $candidate->storage_path,
                'url' => $candidate->url,
                'sort_order' => $index,
            ]);
        }

        $this->auditLogs->log(
            action: 'admin.order.assignment.created',
            actor: Auth::user(),
            target: $assignment,
            category: 'workflow',
            context: [
                'order_id' => $order->id,
                'artisan_user_id' => $artisanUserId,
                'task_scope' => $taskScope,
                'order_item_id' => $orderItemId,
                'target_deadline_at' => $targetDeadlineAt,
                'priority_level' => $priorityLevel,
                'reference_image_ids' => $referenceImageIds,
            ],
        );

        return $assignment;
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
        $this->auditLogs->log(
            action: 'admin.order.qc_approved',
            actor: Auth::user(),
            target: $order,
            category: 'workflow',
            context: [
                'order_status' => 'ready',
            ],
        );

        return $order->fresh();
    }

    /**
     * @throws ValidationException
     */
    public function startProduction(Order $order, int $artisanUserId): OrderAssignment
    {
        if ($order->isReadyMadeFulfillment()) {
            throw ValidationException::withMessages([
                'order' => 'Ready-made orders do not enter production.',
            ]);
        }

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
        $this->auditLogs->log(
            action: 'workflow.order.production_started',
            actor: Auth::user(),
            target: $order,
            category: 'workflow',
            context: [
                'assignment_id' => $assignment->id,
                'artisan_user_id' => $artisanUserId,
            ],
        );

        return $assignment->fresh();
    }

    /**
     * @throws ValidationException
     */
    public function markReadyForReview(Order $order, int $artisanUserId): OrderAssignment
    {
        if ($order->isReadyMadeFulfillment()) {
            throw ValidationException::withMessages([
                'order' => 'Ready-made orders do not require QC review flow.',
            ]);
        }

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
        $this->auditLogs->log(
            action: 'workflow.order.ready_for_qc',
            actor: Auth::user(),
            target: $order,
            category: 'workflow',
            context: [
                'assignment_id' => $assignment->id,
                'artisan_user_id' => $artisanUserId,
            ],
        );

        return $assignment->fresh();
    }

    /**
     * @throws ValidationException
     */
    public function completeAfterFinalSettlement(Order $order): Order
    {
        if ($order->status !== 'ready') {
            throw ValidationException::withMessages([
                'order' => 'Only ready-for-pickup orders can be completed after final settlement.',
            ]);
        }

        $remaining = $this->settlement->remainingBalanceMmk($order);
        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'order' => 'Order still has an outstanding balance.',
            ]);
        }

        $order->update(['status' => 'completed']);
        $this->auditLogs->log(
            action: 'workflow.order.completed_after_final_payment',
            actor: Auth::user(),
            target: $order,
            category: 'workflow',
            context: [
                'remaining_balance_mmk' => (string) $remaining,
            ],
        );

        return $order->fresh();
    }

    /**
     * @throws ValidationException
     */
    public function markReadyForPickup(Order $order): Order
    {
        if (! $order->isReadyMadeFulfillment()) {
            throw ValidationException::withMessages([
                'order' => 'Only ready-made orders use this shortcut action.',
            ]);
        }

        if (! in_array($order->status, ['awaiting_final_settlement', 'deposit_confirmed'], true)) {
            throw ValidationException::withMessages([
                'order' => 'Order is not in a pickup-eligible ready-made state.',
            ]);
        }

        $remaining = $this->settlement->remainingBalanceMmk($order);
        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'order' => 'Final settlement is still outstanding.',
            ]);
        }

        $order->update(['status' => 'ready']);
        $this->auditLogs->log(
            action: 'workflow.order.ready_for_pickup_marked',
            actor: Auth::user(),
            target: $order,
            category: 'workflow',
            context: [
                'remaining_balance_mmk' => (string) $remaining,
                'ready_made' => true,
            ],
        );

        return $order->fresh();
    }
}
