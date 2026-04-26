<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\QuoteRevisedAcceptedForArtisan;
use App\Services\BusinessConfigService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrderBuilderService
{
    /**
     * Customer path: quote is still "sent"; marks accepted, supersedes prior orders on same inquiry, creates order.
     */
    public function acceptSentQuoteAndCreateOrder(Quote $quote): Order
    {
        return DB::transaction(function () use ($quote) {
            if ($quote->status !== 'sent') {
                abort(422, 'Only a sent quote can be accepted.');
            }

            $quote->load('lineItems');

            $supersedeOrderIds = [];
            $creditConfirmedMmk = '0';
            $artisanUserIds = [];

            if ($quote->inquiry_id !== null) {
                $ctx = $this->lockedSupersedeContextForInquiry((int) $quote->inquiry_id);
                $supersedeOrderIds = $ctx['orderIds'];
                $creditConfirmedMmk = $ctx['creditConfirmedMmk'];
                $artisanUserIds = $ctx['artisanUserIds'];

                Quote::query()
                    ->where('inquiry_id', $quote->inquiry_id)
                    ->where('status', 'accepted')
                    ->update(['status' => 'void']);
            }

            $quote->update(['status' => 'accepted']);

            $order = $this->insertOrderAndItemsFromQuote($quote->fresh(['lineItems']), $creditConfirmedMmk);

            if ($supersedeOrderIds !== []) {
                $this->applySupersedeSideEffects(
                    $supersedeOrderIds,
                    $order,
                    (int) $quote->inquiry_id,
                    $artisanUserIds
                );
            }

            return $order;
        });
    }

    /**
     * Admin / API path: quote is already "accepted".
     */
    public function createFromQuote(Quote $quote): Order
    {
        if ($quote->status !== 'accepted') {
            abort(422, 'Quote must be accepted before creating an order.');
        }

        return DB::transaction(function () use ($quote) {
            $quote->load('lineItems');

            $supersedeOrderIds = [];
            $creditConfirmedMmk = '0';
            $artisanUserIds = [];
            $inquiryId = $quote->inquiry_id;

            if ($inquiryId !== null) {
                $ctx = $this->lockedSupersedeContextForInquiry((int) $inquiryId);
                $supersedeOrderIds = $ctx['orderIds'];
                $creditConfirmedMmk = $ctx['creditConfirmedMmk'];
                $artisanUserIds = $ctx['artisanUserIds'];

                Quote::query()
                    ->where('inquiry_id', $inquiryId)
                    ->whereKeyNot($quote->id)
                    ->where('status', 'accepted')
                    ->update(['status' => 'void']);
            }

            $order = $this->insertOrderAndItemsFromQuote($quote, $creditConfirmedMmk);

            if ($supersedeOrderIds !== [] && $inquiryId !== null) {
                $this->applySupersedeSideEffects(
                    $supersedeOrderIds,
                    $order,
                    (int) $inquiryId,
                    $artisanUserIds
                );
            }

            return $order;
        });
    }

    /**
     * @return array{orderIds: array<int, int>, creditConfirmedMmk: string, artisanUserIds: array<int, int>}
     */
    protected function lockedSupersedeContextForInquiry(int $inquiryId): array
    {
        $orderIds = Order::query()
            ->whereHas('quote', fn ($q) => $q->where('inquiry_id', $inquiryId))
            ->where('status', '!=', 'cancelled')
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $creditConfirmedMmk = '0';
        if ($orderIds !== []) {
            $sum = Payment::query()
                ->whereIn('order_id', $orderIds)
                ->where('type', Payment::TYPE_DEPOSIT)
                ->where('status', Payment::STATUS_CONFIRMED)
                ->sum('amount_mmk');
            $creditConfirmedMmk = (string) ($sum ?? '0');
        }

        $artisanUserIds = [];
        if ($orderIds !== []) {
            $artisanUserIds = OrderAssignment::query()
                ->whereIn('order_id', $orderIds)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->distinct()
                ->pluck('artisan_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [
            'orderIds' => $orderIds,
            'creditConfirmedMmk' => $creditConfirmedMmk,
            'artisanUserIds' => $artisanUserIds,
        ];
    }

    /**
     * @param  array<int, int>  $supersededOrderIds
     * @param  array<int, int>  $artisanUserIds
     */
    protected function applySupersedeSideEffects(
        array $supersededOrderIds,
        Order $newOrder,
        int $inquiryId,
        array $artisanUserIds
    ): void {
        Payment::query()
            ->whereIn('order_id', $supersededOrderIds)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->update(['order_id' => $newOrder->id]);

        Order::query()->whereIn('id', $supersededOrderIds)->update([
            'status' => 'cancelled',
            'deposit_instructions_sent_at' => null,
        ]);

        $this->migrateAssignmentsToOrder($supersededOrderIds, $newOrder->id);
        $this->notifyArtisansOfQuoteRevision($artisanUserIds, $inquiryId, $newOrder);
    }

    /**
     * @param  array<int, int>  $fromOrderIds
     */
    protected function migrateAssignmentsToOrder(array $fromOrderIds, int $newOrderId): void
    {
        if ($fromOrderIds === []) {
            return;
        }

        $suffix = ' Quote revised — review design changes.';

        $assignments = OrderAssignment::query()
            ->whereIn('order_id', $fromOrderIds)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->get();

        /** @var OrderAssignment $assignment */
        foreach ($assignments as $assignment) {
            $current = trim((string) $assignment->instructions);
            $instructions = str_contains($current, 'Quote revised — review design changes')
                ? $current
                : trim($current.$suffix);

            $assignment->update([
                'order_id' => $newOrderId,
                'order_item_id' => null,
                'instructions' => $instructions !== '' ? $instructions : trim($suffix),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $artisanUserIds
     */
    protected function notifyArtisansOfQuoteRevision(array $artisanUserIds, int $inquiryId, Order $order): void
    {
        if ($artisanUserIds === []) {
            return;
        }

        $users = User::query()->whereKey($artisanUserIds)->get();
        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new QuoteRevisedAcceptedForArtisan($inquiryId, $order));
    }

    /**
     * @param  string  $creditConfirmedMmk  Sum of confirmed deposit payments from superseded orders (applied before reparent).
     */
    protected function insertOrderAndItemsFromQuote(Quote $quote, string $creditConfirmedMmk = '0'): Order
    {
        $depositPct = app(BusinessConfigService::class)->depositPercentage();
        $total = (float) $quote->total_mmk;
        $credit = (float) $creditConfirmedMmk;
        $isReadyMade = $this->inferReadyMadeFulfillment($quote);
        $overridePct = $quote->deposit_percentage_override !== null ? (float) $quote->deposit_percentage_override : null;
        $overrideAmount = $quote->deposit_amount_override_mmk !== null ? (float) $quote->deposit_amount_override_mmk : null;

        if ($overrideAmount !== null) {
            $requiredDeposit = max(0.0, min($overrideAmount, $total));
        } else {
            $effectivePct = $overridePct ?? ($isReadyMade ? 100.0 : $depositPct);
            $requiredDeposit = round($total * ($effectivePct / 100), 4);
        }

        // Customer paid at least required upfront amount already.
        if ($credit >= $requiredDeposit) {
            $depositAmount = max(0.0, round($requiredDeposit - $credit, 4));
            if ($isReadyMade) {
                $status = 'awaiting_final_settlement';
            } else {
                $status = 'deposit_confirmed';
            }
        } else {
            $depositAmount = max(0.0, round($requiredDeposit - $credit, 4));
            $status = 'awaiting_deposit';
        }

        $order = Order::create([
            'customer_user_id' => $quote->customer_user_id,
            'quote_id' => $quote->id,
            'order_number' => $this->uniqueOrderNumber(),
            'status' => $status,
            'deposit_amount_mmk' => $depositAmount,
            'total_mmk' => $quote->total_mmk,
            'total_usd_snapshot' => $quote->total_usd_snapshot,
            'exchange_rate_snapshot_id' => $quote->exchange_rate_snapshot_id,
        ]);

        foreach ($quote->lineItems as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line->product_id,
                'title_snapshot' => $line->custom_title ?? $line->product?->title ?? 'Custom item',
                'metal_type_snapshot' => $line->metal_type,
                'stone_details_snapshot' => $line->stone_details,
                'weight_grams' => $line->weight_grams,
                'metal_value_mmk' => $line->metal_value_mmk,
                'labor_fee_mmk' => $line->labor_fee_mmk,
                'gem_price_mmk' => $line->gem_price_mmk,
                'line_total_mmk' => $line->line_total_mmk,
                'line_total_usd_snapshot' => $line->line_total_usd_snapshot,
            ]);
        }

        return $order->load('items');
    }

    protected function inferReadyMadeFulfillment(Quote $quote): bool
    {
        $quote->loadMissing('lineItems.product');
        if ($quote->lineItems->isEmpty()) {
            return false;
        }

        return $quote->lineItems
            ->every(fn ($line) => $line->product_id !== null && $line->product?->is_ready_made === true);
    }

    protected function uniqueOrderNumber(): string
    {
        do {
            $n = 'O-'.strtoupper(Str::random(10));
        } while (Order::query()->where('order_number', $n)->exists());

        return $n;
    }
}
