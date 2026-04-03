<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderBuilderService
{
    /**
     * Customer path: quote is still "sent"; marks accepted, supersedes open deposit orders on same inquiry, creates order.
     */
    public function acceptSentQuoteAndCreateOrder(Quote $quote): Order
    {
        return DB::transaction(function () use ($quote) {
            if ($quote->status !== 'sent') {
                abort(422, 'Only a sent quote can be accepted.');
            }

            $quote->load('lineItems');

            if ($quote->inquiry_id !== null) {
                $this->supersedeAwaitingDepositOrdersForInquiry((int) $quote->inquiry_id);
                Quote::query()
                    ->where('inquiry_id', $quote->inquiry_id)
                    ->where('status', 'accepted')
                    ->update(['status' => 'void']);
            }

            $quote->update(['status' => 'accepted']);

            return $this->insertOrderAndItemsFromQuote($quote->fresh(['lineItems']));
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

            if ($quote->inquiry_id !== null) {
                $this->supersedeAwaitingDepositOrdersForInquiry((int) $quote->inquiry_id);
                Quote::query()
                    ->where('inquiry_id', $quote->inquiry_id)
                    ->whereKeyNot($quote->id)
                    ->where('status', 'accepted')
                    ->update(['status' => 'void']);
            }

            return $this->insertOrderAndItemsFromQuote($quote);
        });
    }

    /**
     * Cancel prior awaiting-deposit orders for this inquiry unless deposit is already in flight or confirmed.
     */
    protected function supersedeAwaitingDepositOrdersForInquiry(int $inquiryId): void
    {
        $orderIds = Order::query()
            ->whereHas('quote', fn ($q) => $q->where('inquiry_id', $inquiryId))
            ->where('status', 'awaiting_deposit')
            ->lockForUpdate()
            ->pluck('id');

        foreach ($orderIds as $orderId) {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            $deposit = $order->payments()
                ->where('type', Payment::TYPE_DEPOSIT)
                ->orderByDesc('id')
                ->first();

            if ($deposit !== null && in_array($deposit->status, [
                Payment::STATUS_CONFIRMED,
                Payment::STATUS_AWAITING_VERIFICATION,
            ], true)) {
                abort(422, 'Cannot replace this order while a deposit is submitted or confirmed. Contact concierge.');
            }

            if ($deposit !== null && in_array($deposit->status, [
                Payment::STATUS_PENDING_PROOF,
                Payment::STATUS_REJECTED,
            ], true)) {
                $deposit->delete();
            }

            $order->update([
                'status' => 'cancelled',
                'deposit_instructions_sent_at' => null,
            ]);
        }
    }

    protected function insertOrderAndItemsFromQuote(Quote $quote): Order
    {
        $depositPct = (float) config('jewelry.default_deposit_percentage', 50);
        $depositAmount = round((float) $quote->total_mmk * ($depositPct / 100), 4);

        $order = Order::create([
            'customer_user_id' => $quote->customer_user_id,
            'quote_id' => $quote->id,
            'order_number' => $this->uniqueOrderNumber(),
            'status' => 'awaiting_deposit',
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

    protected function uniqueOrderNumber(): string
    {
        do {
            $n = 'O-'.strtoupper(Str::random(10));
        } while (Order::query()->where('order_number', $n)->exists());

        return $n;
    }
}
