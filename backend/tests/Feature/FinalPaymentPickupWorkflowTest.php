<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\GoldPriceSnapshot;
use App\Models\ExchangeRateSnapshot;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use App\Services\OrderLifecycleService;
use App\Models\Category;
use App\Models\Product;
use App\Services\OrderSettlementService;
use App\Services\PaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalPaymentPickupWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_payment_confirmation_completes_ready_order_and_issues_final_receipt(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $order = $this->makeReadyOrder($customer, totalMmk: 300000, depositMmk: 100000);

        Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => 100000,
            'currency' => 'MMK',
            'status' => Payment::STATUS_CONFIRMED,
            'verified_at' => now()->subDay(),
            'verified_by_user_id' => $admin->id,
        ]);

        $final = Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_FINAL,
            'amount_mmk' => 200000,
            'currency' => 'MMK',
            'status' => Payment::STATUS_AWAITING_VERIFICATION,
            'submitted_at' => now(),
        ]);

        app(PaymentVerificationService::class)->confirm($admin, $final);

        $order->refresh();
        $final->refresh();
        $receipt = $final->receipt()->first();

        $this->assertSame('completed', $order->status);
        $this->assertSame(Payment::STATUS_CONFIRMED, $final->status);
        $this->assertNotNull($receipt);
        $this->assertSame(Payment::TYPE_FINAL, (string) ($receipt?->meta_json['payment_type'] ?? ''));
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_remaining_balance_from_deposit_calculator_matches_expected_value(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $order = $this->makeReadyOrder($customer, totalMmk: 450000, depositMmk: 120000);

        Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => 120000,
            'currency' => 'MMK',
            'status' => Payment::STATUS_CONFIRMED,
        ]);

        $remaining = app(OrderSettlementService::class)->remainingBalanceFromDepositsMmk($order);
        $this->assertSame(330000.0, $remaining);
    }

    public function test_ready_made_full_settlement_waits_for_mark_ready_action(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $gold = GoldPriceSnapshot::create([
            'price_mmk_per_gram' => 150000,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);
        $fx = ExchangeRateSnapshot::create([
            'mmk_per_usd' => 2100,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);

        $category = Category::create(['name' => 'Rings', 'slug' => 'rings', 'sort_order' => 1]);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'RM-001',
            'title' => 'Ready Ring',
            'description' => 'Ready-made ring',
            'weight_grams' => 5.0,
            'labor_fee_mmk' => 10000,
            'gem_price_mmk' => 5000,
            'is_published' => true,
            'is_ready_made' => true,
            'is_custom_order_template' => true,
        ]);

        $quote = Quote::create([
            'inquiry_id' => null,
            'customer_user_id' => $customer->id,
            'gold_price_snapshot_id' => $gold->id,
            'exchange_rate_snapshot_id' => $fx->id,
            'quote_number' => 'Q-RM-001',
            'status' => 'accepted',
            'total_mmk' => 200000,
            'total_usd_snapshot' => 0,
        ]);
        QuoteLineItem::create([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'custom_title' => 'Ready Ring',
            'metal_type' => null,
            'stone_details' => null,
            'weight_grams' => 5.0,
            'gold_price_mmk_per_gram' => 0,
            'metal_value_mmk' => 185000,
            'labor_fee_mmk' => 10000,
            'gem_price_mmk' => 5000,
            'line_total_mmk' => 200000,
            'line_total_usd_snapshot' => 0,
        ]);

        $order = Order::query()->create([
            'customer_user_id' => $customer->id,
            'quote_id' => $quote->id,
            'order_number' => 'O-RM-001',
            'status' => 'deposit_confirmed',
            'deposit_amount_mmk' => 0,
            'deposit_instructions_sent_at' => now()->subDays(1),
            'total_mmk' => 200000,
            'total_usd_snapshot' => 0,
            'exchange_rate_snapshot_id' => null,
            'shipping_notes' => null,
        ]);

        $final = Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_FINAL,
            'amount_mmk' => 200000,
            'currency' => 'MMK',
            'status' => Payment::STATUS_AWAITING_VERIFICATION,
            'submitted_at' => now(),
        ]);

        app(PaymentVerificationService::class)->confirm($admin, $final);
        $order->refresh();

        $this->assertSame('deposit_confirmed', $order->status);

        app(OrderLifecycleService::class)->markReadyForPickup($order);
        $order->refresh();
        $this->assertSame('ready', $order->status);
    }

    public function test_ready_made_partial_deposit_stays_awaiting_final_settlement(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $gold = GoldPriceSnapshot::create([
            'price_mmk_per_gram' => 150000,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);
        $fx = ExchangeRateSnapshot::create([
            'mmk_per_usd' => 2100,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);
        $category = Category::create(['name' => 'Pendant', 'slug' => 'pendant', 'sort_order' => 1]);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'RM-002',
            'title' => 'Ready Pendant',
            'description' => 'Ready-made pendant',
            'weight_grams' => 3.0,
            'labor_fee_mmk' => 8000,
            'gem_price_mmk' => 0,
            'is_published' => true,
            'is_ready_made' => true,
            'is_custom_order_template' => true,
        ]);
        $quote = Quote::create([
            'inquiry_id' => null,
            'customer_user_id' => $customer->id,
            'gold_price_snapshot_id' => $gold->id,
            'exchange_rate_snapshot_id' => $fx->id,
            'quote_number' => 'Q-RM-002',
            'status' => 'accepted',
            'total_mmk' => 300000,
            'total_usd_snapshot' => 0,
        ]);
        QuoteLineItem::create([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'custom_title' => 'Ready Pendant',
            'metal_type' => null,
            'stone_details' => null,
            'weight_grams' => 3.0,
            'gold_price_mmk_per_gram' => 0,
            'metal_value_mmk' => 292000,
            'labor_fee_mmk' => 8000,
            'gem_price_mmk' => 0,
            'line_total_mmk' => 300000,
            'line_total_usd_snapshot' => 0,
        ]);
        $order = Order::query()->create([
            'customer_user_id' => $customer->id,
            'quote_id' => $quote->id,
            'order_number' => 'O-RM-002',
            'status' => 'awaiting_deposit',
            'deposit_amount_mmk' => 150000,
            'deposit_instructions_sent_at' => now()->subDays(1),
            'total_mmk' => 300000,
            'total_usd_snapshot' => 0,
            'exchange_rate_snapshot_id' => null,
            'shipping_notes' => null,
        ]);
        $deposit = Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => 150000,
            'currency' => 'MMK',
            'status' => Payment::STATUS_AWAITING_VERIFICATION,
            'submitted_at' => now(),
        ]);

        app(PaymentVerificationService::class)->confirm($admin, $deposit);
        $order->refresh();
        $this->assertSame('awaiting_final_settlement', $order->status);
    }

    private function makeReadyOrder(User $customer, float $totalMmk, float $depositMmk): Order
    {
        return Order::query()->create([
            'customer_user_id' => $customer->id,
            'quote_id' => null,
            'order_number' => 'O-FINAL-'.strtoupper(substr(md5((string) microtime(true)), 0, 10)),
            'status' => 'ready',
            'deposit_amount_mmk' => $depositMmk,
            'deposit_instructions_sent_at' => now()->subDays(3),
            'total_mmk' => $totalMmk,
            'total_usd_snapshot' => 0,
            'exchange_rate_snapshot_id' => null,
            'shipping_notes' => null,
        ]);
    }
}

