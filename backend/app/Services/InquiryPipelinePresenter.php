<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\InquiryTimelineLog;
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
    public function __construct(
        protected OrderSettlementService $settlement
    ) {}

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
        /** @var Order|null $latestOrder */
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
                'payment_proof_history' => $this->paymentProofHistory($latestOrder),
                'latest_receipt' => $this->latestReceiptDisplay($latestOrder),
                'primary_assignment_id' => $latestOrder->assignments->first()?->id,
                'primary_artisan_user_id' => $latestOrder->assignments->first()?->artisan_user_id,
                'timeline_logs' => $this->timelineLogs($inquiry),
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
                'payment_proof_history' => [],
                'latest_receipt' => null,
                'primary_assignment_id' => null,
                'primary_artisan_user_id' => null,
                'timeline_logs' => $this->timelineLogs($inquiry),
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
                'payment_proof_history' => [],
                'latest_receipt' => null,
                'primary_assignment_id' => null,
                'primary_artisan_user_id' => null,
                'timeline_logs' => $this->timelineLogs($inquiry),
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
            'payment_proof_history' => [],
            'latest_receipt' => null,
            'primary_assignment_id' => null,
            'primary_artisan_user_id' => null,
            'timeline_logs' => $this->timelineLogs($inquiry),
        ];
    }

    /**
     * @return array<int, array{id:int,message:string,occurred_at:?string}>
     */
    protected function timelineLogs(Inquiry $inquiry): array
    {
        return InquiryTimelineLog::query()
            ->where('inquiry_id', $inquiry->id)
            ->orderByDesc('id')
            ->limit(120)
            ->get(['id', 'message', 'occurred_at'])
            ->sortBy('id')
            ->values()
            ->map(fn (InquiryTimelineLog $log): array => [
                'id' => $log->id,
                'message' => (string) $log->message,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])
            ->all();
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
        if ($deposit->proof_storage_path) {
            $fields['payment_proof_image_url'] = app(MediaStorageService::class)->publicUrl(
                $deposit->proof_storage_path
            );
            $fields['payment_proof_submitted_at'] = $deposit->submitted_at?->toIso8601String();
        }
        $fields['payment_proof_history'] = $this->paymentProofHistory($order);

        foreach ($this->paymentBalanceBreakdown($order) as $k => $v) {
            $fields[$k] = $v;
        }

        return $fields;
    }

    /**
     * @return array<int, array{payment_id:int,image_url:string,submitted_at:?string,status:?string,rejection_reason:?string}>
     */
    protected function paymentProofHistory(Order $order): array
    {
        return $order->payments
            ->filter(fn (Payment $p) => $p->type === Payment::TYPE_DEPOSIT && ! empty($p->proof_storage_path))
            ->sortBy('id')
            ->values()
            ->map(fn (Payment $p): array => [
                'payment_id' => $p->id,
                'image_url' => app(MediaStorageService::class)->publicUrl((string) $p->proof_storage_path),
                'submitted_at' => $p->submitted_at?->toIso8601String(),
                'status' => $p->status,
                'rejection_reason' => $p->rejection_reason,
            ])
            ->all();
    }

    /**
     * Recognized confirmed deposits vs order total (for payment cards).
     *
     * @return array<string, string>
     */
    public function paymentBalanceBreakdown(Order $order): array
    {
        $credited = $this->settlement->confirmedDepositsMmk($order);
        $creditedStr = (string) $credited;
        $balanceRemaining = $this->settlement->remainingBalanceFromDepositsMmk($order);

        return [
            'order_total_mmk' => (string) $order->total_mmk,
            'credited_deposit_mmk' => $creditedStr,
            'balance_remaining_mmk' => (string) $balanceRemaining,
            'additional_deposit_due_mmk' => (string) ($order->deposit_amount_mmk ?? '0'),
        ];
    }

    /**
     * @return array<string, string|int|null>|null
     */
    protected function latestReceiptDisplay(Order $order): ?array
    {
        $receipt = $order->receipts()->latest('id')->first();
        if ($receipt === null) {
            return null;
        }

        return [
            'type' => 'receipt_card',
            'sender_role' => 'admin',
            'id' => $receipt->id,
            'receipt_id' => $receipt->id,
            'order_id' => $order->id,
            'receipt_number' => $receipt->receipt_number,
            'amount_mmk' => (string) $receipt->amount_mmk,
            'cta_label' => 'View Receipt',
            'issued_at' => $receipt->issued_at?->toIso8601String(),
        ];
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
        if ($order->status === 'awaiting_final_settlement') {
            return self::DEPOSIT_CONFIRMED;
        }

        return self::DEPOSIT_NONE;
    }

    protected function resolveAssignmentState(Order $order): string
    {
        if ($order->isReadyMadeFulfillment()) {
            return self::ASSIGNMENT_NONE;
        }

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
        if ($order->isReadyMadeFulfillment()) {
            return match ($order->status) {
                'ready', 'shipped', 'completed' => self::STEP_READY,
                'awaiting_deposit', 'deposit_confirmed', 'awaiting_final_settlement' => self::STEP_AWAITING_DEPOSIT,
                'cancelled' => self::STEP_CONSULTING,
                default => self::STEP_CONSULTING,
            };
        }

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
