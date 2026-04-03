<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BespokeOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function seedPricing(): void
    {
        GoldPriceSnapshot::create([
            'price_mmk_per_gram' => 150000,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);

        ExchangeRateSnapshot::create([
            'mmk_per_usd' => 2100.5,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);
    }

    public function test_deposit_confirm_assign_start_qc_flow(): void
    {
        $this->seedPricing();
        Storage::fake('public');

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $artisan = User::factory()->create(['role' => UserRole::Artisan]);

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Ring',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-1',
        ]);

        $adminToken = $admin->createToken('t')->plainTextToken;

        $publish = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [
                    [
                        'weight_grams' => 10,
                        'labor_fee_mmk' => 50000,
                        'gem_price_mmk' => 10000,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish->assertCreated();
        $quoteId = (int) $publish->json('data.id');

        Sanctum::actingAs($customer);
        $accept = $this->postJson("/api/v1/quotes/{$quoteId}/accept");
        $accept->assertOk();
        $orderId = (int) $accept->json('data.order.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'awaiting_deposit',
        ]);

        $profile = PaymentProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        Sanctum::actingAs($customer);
        $orderNumber = Order::query()->findOrFail($orderId)->order_number;
        $this->getJson("/api/v1/inquiries/{$inquiry->id}")
            ->assertOk()
            ->assertJsonPath('meta.pipeline.deposit_payment.order_number', $orderNumber)
            ->assertJsonPath('meta.pipeline.deposit_payment.payment_profile_id', $profile->id);

        Sanctum::actingAs($customer);
        $proof = UploadedFile::fake()->image('proof.jpg');
        $this->postJson(
            "/api/v1/orders/{$orderId}/deposit-proof",
            ['proof' => $proof],
            ['Authorization' => 'Bearer '.$customer->createToken('c')->plainTextToken]
        )->assertCreated();

        $payment = Payment::query()->where('order_id', $orderId)->first();
        $this->assertNotNull($payment);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/payments/{$payment->id}/confirm",
            [],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'deposit_confirmed']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_CONFIRMED]);

        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/assignments",
            [
                'artisan_user_id' => $artisan->id,
                'task_scope' => 'full_order',
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertCreated();

        Sanctum::actingAs($artisan);
        $artisanToken = $artisan->createToken('a')->plainTextToken;
        $this->postJson(
            "/api/v1/artisan/orders/{$orderId}/start",
            [],
            ['Authorization' => 'Bearer '.$artisanToken]
        )->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'in_production']);

        $photo = UploadedFile::fake()->image('p.jpg');
        $this->post(
            "/api/v1/artisan/orders/{$orderId}/progress",
            [
                'stage' => 'casting',
                'photos' => [$photo],
            ],
            ['Authorization' => 'Bearer '.$artisanToken]
        )->assertCreated();

        $this->postJson(
            "/api/v1/artisan/orders/{$orderId}/ready-for-review",
            [],
            ['Authorization' => 'Bearer '.$artisanToken]
        )->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending_qc']);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/qc/approve",
            [],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'ready']);
    }

    public function test_cannot_assign_before_deposit_confirmed(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $artisan = User::factory()->create(['role' => UserRole::Artisan]);

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Ring',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-2',
        ]);

        $adminToken = $admin->createToken('t')->plainTextToken;

        $publish = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [
                    [
                        'weight_grams' => 10,
                        'labor_fee_mmk' => 50000,
                        'gem_price_mmk' => 10000,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $quoteId = (int) $publish->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId}/accept")->assertOk();
        $orderId = (int) Order::query()->first()->id;

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/assignments",
            [
                'artisan_user_id' => $artisan->id,
                'task_scope' => 'full_order',
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertStatus(422);
    }
}
