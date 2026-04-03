<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Models\Quote;

/**
 * Derives concierge pipeline step from inquiry, quotes, orders, payments, assignments (MySQL).
 */
class InquiryPipelinePresenter
{
    public const STEP_CONSULTING = 'consulting';

    public const STEP_QUOTED = 'quoted';

    public const STEP_AWAITING_DEPOSIT = 'awaiting_deposit';

    public const STEP_PRE_PRODUCTION = 'pre_production';

    public const STEP_IN_PRODUCTION = 'in_production';

    public const STEP_PENDING_QC = 'pending_qc';

    public const STEP_READY = 'ready';

    public const DEPOSIT_NONE = 'none';

    public const DEPOSIT_INSTRUCTIONS_SENT = 'instructions_sent';

    public const DEPOSIT_PROOF_SUBMITTED = 'proof_submitted';

    public const DEPOSIT_CONFIRMED = 'confirmed';

    public const DEPOSIT_REJECTED = 'rejected';

    public const ASSIGNMENT_NONE = 'none';

    public const ASSIGNMENT_ASSIGNED = 'assigned';

    public const ASSIGNMENT_IN_PROGRESS = 'in_progress';

    public const ASSIGNMENT_DONE = 'done';

    /**
     * @return array<string, mixed>
     */
    public function present(Inquiry $inquiry): array
    {
        $latestOrder = Order::query()
            ->whereHas('quote', fn ($q) => $q->where('inquiry_id', $inquiry->id))
            ->with([
                'payments',
                'assignments' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->orderByDesc('id')
            ->first();

        $sentOrAcceptedQuote = Quote::query()
            ->where('inquiry_id', $inquiry->id)
            ->whereIn('status', ['sent', 'accepted'])
            ->orderByDesc('id')
            ->first();

        $activeQuote = $sentOrAcceptedQuote;

        if ($latestOrder !== null) {
            $depositState = $this->resolveDepositState($latestOrder);
            $assignmentState = $this->resolveAssignmentState($latestOrder);
            $step = $this->orderToStep($latestOrder);

            return [
                'step' => $step,
                'order' => [
                    'id' => $latestOrder->id,
                    'order_number' => $latestOrder->order_number,
                    'status' => $latestOrder->status,
                ],
                'active_quote' => $activeQuote ? [
                    'id' => $activeQuote->id,
                    'quote_number' => $activeQuote->quote_number,
                    'status' => $activeQuote->status,
                ] : null,
                'order_id' => $latestOrder->id,
                'order_status' => $latestOrder->status,
                'active_quote_id' => $activeQuote?->id,
                'deposit_state' => $depositState,
                'assignment_state' => $assignmentState,
                'deposit_amount_mmk' => $latestOrder->deposit_amount_mmk !== null
                    ? (string) $latestOrder->deposit_amount_mmk
                    : null,
                'deposit_instructions_sent_at' => $latestOrder->deposit_instructions_sent_at?->toIso8601String(),
                'deposit_payment' => $this->depositPaymentDisplay($latestOrder),
                'primary_assignment_id' => $latestOrder->assignments->first()?->id,
                'primary_artisan_user_id' => $latestOrder->assignments->first()?->artisan_user_id,
            ];
        }

        if ($sentOrAcceptedQuote !== null && $sentOrAcceptedQuote->status === 'sent') {
            return [
                'step' => self::STEP_QUOTED,
                'order' => null,
                'active_quote' => [
                    'id' => $sentOrAcceptedQuote->id,
                    'quote_number' => $sentOrAcceptedQuote->quote_number,
                    'status' => $sentOrAcceptedQuote->status,
                ],
                'order_id' => null,
                'order_status' => null,
                'active_quote_id' => $sentOrAcceptedQuote->id,
                'deposit_state' => self::DEPOSIT_NONE,
                'assignment_state' => self::ASSIGNMENT_NONE,
                'deposit_amount_mmk' => null,
                'deposit_instructions_sent_at' => null,
                'deposit_payment' => null,
                'primary_assignment_id' => null,
                'primary_artisan_user_id' => null,
            ];
        }

        if ($sentOrAcceptedQuote !== null && $sentOrAcceptedQuote->status === 'accepted') {
            return [
                'step' => self::STEP_QUOTED,
                'order' => null,
                'active_quote' => [
                    'id' => $sentOrAcceptedQuote->id,
                    'quote_number' => $sentOrAcceptedQuote->quote_number,
                    'status' => $sentOrAcceptedQuote->status,
                ],
                'order_id' => null,
                'order_status' => null,
                'active_quote_id' => $sentOrAcceptedQuote->id,
                'deposit_state' => self::DEPOSIT_NONE,
                'assignment_state' => self::ASSIGNMENT_NONE,
                'deposit_amount_mmk' => null,
                'deposit_instructions_sent_at' => null,
                'deposit_payment' => null,
                'primary_assignment_id' => null,
                'primary_artisan_user_id' => null,
            ];
        }

        return [
            'step' => self::STEP_CONSULTING,
            'order' => null,
            'active_quote' => null,
            'order_id' => null,
            'order_status' => null,
            'active_quote_id' => null,
            'deposit_state' => self::DEPOSIT_NONE,
            'assignment_state' => self::ASSIGNMENT_NONE,
            'deposit_amount_mmk' => null,
            'deposit_instructions_sent_at' => null,
            'deposit_payment' => null,
            'primary_assignment_id' => null,
            'primary_artisan_user_id' => null,
        ];
    }

    /**
     * Same shape as Firestore `payment_info_card` so the mobile app can render when chat sync is unavailable.
     *
     * @return array<string, mixed>|null
     */
    protected function depositPaymentDisplay(Order $order): ?array
    {
        if ($order->status !== 'awaiting_deposit') {
            return null;
        }

        /** @var Payment|null $deposit */
        $deposit = $order->payments
            ->filter(fn (Payment $p) => $p->type === Payment::TYPE_DEPOSIT)
            ->sortByDesc('id')
            ->first();

        if ($deposit === null || $deposit->payment_profile_id === null) {
            return null;
        }

        $profile = PaymentProfile::query()->find($deposit->payment_profile_id);
        if ($profile === null) {
            return null;
        }

        $fields = $profile->toFirestorePaymentFields();
        $fields['order_number'] = $order->order_number;
        $fields['amount_mmk'] = (string) ($order->deposit_amount_mmk ?? '0');

        return $fields;
    }

    protected function resolveDepositState(Order $order): string
    {
        $deposit = $order->payments()->where('type', Payment::TYPE_DEPOSIT)->orderByDesc('id')->first();

        if ($order->status === 'awaiting_deposit') {
            if ($deposit === null) {
                return $order->deposit_instructions_sent_at !== null
                    ? self::DEPOSIT_INSTRUCTIONS_SENT
                    : self::DEPOSIT_NONE;
            }

            return match ($deposit->status) {
                Payment::STATUS_REJECTED => self::DEPOSIT_REJECTED,
                Payment::STATUS_CONFIRMED => self::DEPOSIT_CONFIRMED,
                Payment::STATUS_AWAITING_VERIFICATION => self::DEPOSIT_PROOF_SUBMITTED,
                Payment::STATUS_PENDING_PROOF => self::DEPOSIT_INSTRUCTIONS_SENT,
                default => self::DEPOSIT_NONE,
            };
        }

        if (in_array($order->status, ['deposit_confirmed', 'in_production', 'pending_qc', 'ready', 'shipped', 'completed'], true)) {
            return self::DEPOSIT_CONFIRMED;
        }

        return self::DEPOSIT_NONE;
    }

    protected function resolveAssignmentState(Order $order): string
    {
        /** @var OrderAssignment|null $a */
        $a = $order->assignments()->orderByDesc('id')->first();
        if ($a === null) {
            return self::ASSIGNMENT_NONE;
        }

        return match ($a->status) {
            'assigned' => self::ASSIGNMENT_ASSIGNED,
            'in_progress' => self::ASSIGNMENT_IN_PROGRESS,
            'done', 'blocked' => self::ASSIGNMENT_DONE,
            default => self::ASSIGNMENT_NONE,
        };
    }

    protected function orderToStep(Order $order): string
    {
        return match ($order->status) {
            'ready', 'shipped', 'completed' => self::STEP_READY,
            'pending_qc' => self::STEP_PENDING_QC,
            'in_production' => self::STEP_IN_PRODUCTION,
            'deposit_confirmed' => self::STEP_PRE_PRODUCTION,
            'awaiting_deposit' => self::STEP_AWAITING_DEPOSIT,
            'cancelled' => self::STEP_CONSULTING,
            default => self::STEP_CONSULTING,
        };
    }
}
