<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
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

