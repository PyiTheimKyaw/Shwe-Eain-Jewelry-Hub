<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderBuilderService
{
    public function createFromQuote(Quote $quote): Order
    {
        if ($quote->status !== 'accepted') {
            abort(422, 'Quote must be accepted before creating an order.');
        }

        return DB::transaction(function () use ($quote) {
            $quote->load('lineItems');

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
        });
    }

    protected function uniqueOrderNumber(): string
    {
        do {
            $n = 'O-'.strtoupper(Str::random(10));
        } while (Order::query()->where('order_number', $n)->exists());

        return $n;
    }
}
