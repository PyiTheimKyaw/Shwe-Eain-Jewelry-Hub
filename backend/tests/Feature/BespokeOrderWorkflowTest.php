<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Models\Quote;
use App\Models\Receipt;
use App\Models\User;
use App\Services\FirebaseInquiryService;
use App\Services\InquiryQuoteCommitmentGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
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

    public function test_can_publish_revised_quote_while_deposit_pending_proof(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Necklace',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-revise',
        ]);

        $adminToken = $admin->createToken('t')->plainTextToken;

        $lines = [
            [
                'weight_grams' => 10,
                'labor_fee_mmk' => 50000,
                'gem_price_mmk' => 10000,
            ],
        ];

        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish1->assertCreated();
        $quoteId = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId}/accept")->assertOk();
        $orderId = (int) Order::query()->first()->id;

        $profile = PaymentProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $publish2 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish2->assertCreated();
    }

    public function test_can_publish_quote_while_deposit_awaiting_verification(): void
    {
        $this->seedPricing();
        Storage::fake('public');

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Bracelet',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-awaiting',
        ]);

        $adminToken = $admin->createToken('t')->plainTextToken;

        $lines = [
            [
                'weight_grams' => 8,
                'labor_fee_mmk' => 40000,
                'gem_price_mmk' => 5000,
            ],
        ];

        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish1->assertCreated();
        $quoteId = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId}/accept")->assertOk();
        $orderId = (int) Order::query()->first()->id;

        $profile = PaymentProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        Sanctum::actingAs($customer);
        $this->postJson(
            "/api/v1/orders/{$orderId}/deposit-proof",
            ['proof' => UploadedFile::fake()->image('proof.jpg')],
            ['Authorization' => 'Bearer '.$customer->createToken('c')->plainTextToken]
        )->assertCreated();

        Sanctum::actingAs($admin);
        $republish = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $republish->assertCreated();
        $this->assertArrayNotHasKey('warning', $republish->json('meta') ?? []);
    }

    public function test_publish_quote_after_deposit_confirmed_returns_warning_meta(): void
    {
        $this->seedPricing();
        Storage::fake('public');

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Earrings',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-confirmed',
        ]);

        $adminToken = $admin->createToken('t')->plainTextToken;

        $lines = [
            [
                'weight_grams' => 5,
                'labor_fee_mmk' => 30000,
                'gem_price_mmk' => 20000,
            ],
        ];

        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish1->assertCreated();
        $quoteId = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId}/accept")->assertOk();
        $orderId = (int) Order::query()->first()->id;

        $profile = PaymentProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        Sanctum::actingAs($customer);
        $this->postJson(
            "/api/v1/orders/{$orderId}/deposit-proof",
            ['proof' => UploadedFile::fake()->image('proof.jpg')],
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

        $republish = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $republish->assertCreated();
        $republish->assertJsonPath('meta.warning', InquiryQuoteCommitmentGate::WARNING_CONFIRMED_DEPOSIT);
    }

    public function test_promote_draft_quote_to_sent_after_deposit_confirmed_returns_warning_meta(): void
    {
        $this->seedPricing();
        Storage::fake('public');

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Pendant',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-patch',
        ]);

        $adminToken = $admin->createToken('t')->plainTextToken;

        $lines = [
            [
                'weight_grams' => 6,
                'labor_fee_mmk' => 20000,
                'gem_price_mmk' => 15000,
            ],
        ];

        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish1->assertCreated();
        $quoteId = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId}/accept")->assertOk();
        $orderId = (int) Order::query()->first()->id;

        Sanctum::actingAs($admin);
        $draft = $this->postJson(
            '/api/v1/admin/quotes',
            [
                'customer_user_id' => $customer->id,
                'inquiry_id' => $inquiry->id,
                'lines' => $lines,
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $draft->assertCreated();
        $draftQuoteId = (int) $draft->json('data.id');

        $profile = PaymentProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$orderId}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        Sanctum::actingAs($customer);
        $this->postJson(
            "/api/v1/orders/{$orderId}/deposit-proof",
            ['proof' => UploadedFile::fake()->image('proof.jpg')],
            ['Authorization' => 'Bearer '.$customer->createToken('c')->plainTextToken]
        )->assertCreated();

        $payment = Payment::query()->where('order_id', $orderId)->first();
        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/payments/{$payment->id}/confirm",
            [],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $patch = $this->patchJson(
            "/api/v1/admin/quotes/{$draftQuoteId}",
            ['status' => 'sent'],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $patch->assertOk();
        $patch->assertJsonPath('meta.warning', InquiryQuoteCommitmentGate::WARNING_CONFIRMED_DEPOSIT);
    }

    public function test_accepting_newer_quote_after_confirmed_deposit_reparents_payment_and_reduces_deposit_due(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Ring credit',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-credit',
        ]);

        $lines1 = [
            ['weight_grams' => 10, 'labor_fee_mmk' => 50000, 'gem_price_mmk' => 10000],
        ];
        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines1],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertCreated();
        $quoteId1 = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId1}/accept")->assertOk();
        $order1 = Order::query()->where('quote_id', $quoteId1)->firstOrFail();
        $confirmedAmount = (float) $order1->deposit_amount_mmk;

        $profile = PaymentProfile::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$order1->id}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $payment = Payment::query()->where('order_id', $order1->id)->firstOrFail();
        $payment->update([
            'status' => Payment::STATUS_CONFIRMED,
            'verified_at' => now(),
            'verified_by_user_id' => $admin->id,
        ]);
        $order1->update(['status' => 'deposit_confirmed']);

        $lines2 = [
            ['weight_grams' => 12, 'labor_fee_mmk' => 60000, 'gem_price_mmk' => 12000],
        ];
        Sanctum::actingAs($admin);
        $publish2 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines2],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertCreated();
        $quoteId2 = (int) $publish2->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId2}/accept")->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order1->id, 'status' => 'cancelled']);

        $order2 = Order::query()->where('quote_id', $quoteId2)->firstOrFail();
        $this->assertSame('awaiting_deposit', $order2->status);

        $payment->refresh();
        $this->assertSame($order2->id, $payment->order_id);
        $this->assertSame(Payment::STATUS_CONFIRMED, $payment->status);

        $quote2 = Quote::query()->findOrFail($quoteId2);
        $required2 = round((float) $quote2->total_mmk * 0.5, 4);
        $expectedDue = max(0, round($required2 - $confirmedAmount, 4));
        $this->assertEqualsWithDelta($expectedDue, (float) $order2->deposit_amount_mmk, 0.02);
    }

    public function test_accepting_newer_quote_notifies_assigned_artisan_and_migrates_assignment(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $artisan = User::factory()->create(['role' => UserRole::Artisan]);
        $adminToken = $admin->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Notify',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-notify',
        ]);

        $lines = [
            ['weight_grams' => 4, 'labor_fee_mmk' => 20000, 'gem_price_mmk' => 0],
        ];
        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertCreated();
        $quoteId1 = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId1}/accept")->assertOk();
        $order1 = Order::query()->where('quote_id', $quoteId1)->firstOrFail();

        $profile = PaymentProfile::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$order1->id}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $pay = Payment::query()->where('order_id', $order1->id)->firstOrFail();
        $pay->update([
            'status' => Payment::STATUS_CONFIRMED,
            'verified_at' => now(),
            'verified_by_user_id' => $admin->id,
        ]);
        $order1->update(['status' => 'deposit_confirmed']);

        $this->postJson(
            "/api/v1/admin/orders/{$order1->id}/assignments",
            [
                'artisan_user_id' => $artisan->id,
                'task_scope' => 'full_order',
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertCreated();

        $assignment = OrderAssignment::query()->where('order_id', $order1->id)->firstOrFail();

        Sanctum::actingAs($admin);
        $publish2 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => $lines],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertCreated();
        $quoteId2 = (int) $publish2->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId2}/accept")->assertOk();

        $order2 = Order::query()->where('quote_id', $quoteId2)->firstOrFail();
        $assignment->refresh();
        $this->assertSame($order2->id, $assignment->order_id);
        $this->assertStringContainsString('Quote revised', (string) $assignment->instructions);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $artisan->id,
        ]);
    }

    public function test_qc_approve_publishes_required_firestore_system_message(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'QC ready message',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-qc-msg',
        ]);

        $quote = Quote::create([
            'inquiry_id' => $inquiry->id,
            'customer_user_id' => $customer->id,
            'gold_price_snapshot_id' => GoldPriceSnapshot::query()->firstOrFail()->id,
            'exchange_rate_snapshot_id' => ExchangeRateSnapshot::query()->firstOrFail()->id,
            'quote_number' => 'Q-QC-MSG-1',
            'status' => 'accepted',
            'total_mmk' => 100000,
            'total_usd_snapshot' => 47.6,
        ]);

        $order = Order::create([
            'customer_user_id' => $customer->id,
            'quote_id' => $quote->id,
            'order_number' => 'O-QC-MSG-1',
            'status' => 'pending_qc',
            'deposit_amount_mmk' => 0,
            'total_mmk' => 100000,
            'total_usd_snapshot' => 47.6,
            'exchange_rate_snapshot_id' => ExchangeRateSnapshot::query()->firstOrFail()->id,
        ]);

        $firebase = Mockery::mock(FirebaseInquiryService::class);
        $firebase
            ->shouldReceive('publishSystemTextMessage')
            ->once()
            ->withArgs(function ($argInquiry, $message) use ($inquiry) {
                return $argInquiry instanceof Inquiry
                    && $argInquiry->id === $inquiry->id
                    && $message === 'Your order has passed Quality Control and is now Ready for Collection!';
            });
        $firebase->shouldReceive('syncInquiryThreadDocument')->once();
        app()->instance(FirebaseInquiryService::class, $firebase);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/orders/{$order->id}/qc/approve",
            [],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'ready',
        ]);
    }

    public function test_confirm_payment_issues_single_receipt_and_customer_can_download(): void
    {
        $this->seedPricing();
        Storage::fake('public');
        Storage::fake('local');

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('t')->plainTextToken;
        $customerToken = $customer->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Receipt flow',
            'status' => 'open',
            'firebase_thread_id' => 'thread-bespoke-receipt-1',
        ]);

        Sanctum::actingAs($admin);
        $publish = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => [['weight_grams' => 5, 'labor_fee_mmk' => 10000, 'gem_price_mmk' => 5000]]],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertCreated();
        $quoteId = (int) $publish->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId}/accept")->assertOk();
        $order = Order::query()->where('quote_id', $quoteId)->firstOrFail();

        Sanctum::actingAs($admin);
        $profile = PaymentProfile::factory()->create(['is_active' => true]);
        $this->postJson(
            "/api/v1/admin/orders/{$order->id}/payment-instructions",
            ['payment_profile_id' => $profile->id],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        Sanctum::actingAs($customer);
        $this->postJson(
            "/api/v1/orders/{$order->id}/deposit-proof",
            ['proof' => UploadedFile::fake()->image('proof.jpg')],
            ['Authorization' => 'Bearer '.$customerToken]
        )->assertCreated();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $firebase = Mockery::mock(FirebaseInquiryService::class);
        $firebase
            ->shouldReceive('publishReceiptCardMessage')
            ->once()
            ->withArgs(function ($argInquiry, $argReceipt) use ($inquiry, $order) {
                return $argInquiry instanceof Inquiry
                    && $argInquiry->id === $inquiry->id
                    && $argReceipt instanceof Receipt
                    && $argReceipt->order_id === $order->id;
            });
        $firebase->shouldReceive('syncInquiryThreadDocument')->once();
        app()->instance(FirebaseInquiryService::class, $firebase);

        Sanctum::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/payments/{$payment->id}/confirm",
            [],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();
        $this->postJson(
            "/api/v1/admin/payments/{$payment->id}/confirm",
            [],
            ['Authorization' => 'Bearer '.$adminToken]
        )->assertOk();

        $this->assertDatabaseCount('receipts', 1);
        $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($receipt->pdf_storage_path));

        Sanctum::actingAs($customer);
        $this->getJson(
            "/api/v1/orders/{$order->id}/receipts",
            ['Authorization' => 'Bearer '.$customerToken]
        )->assertOk()->assertJsonPath('data.0.id', $receipt->id);

        $this->get(
            "/api/v1/orders/{$order->id}/receipts/{$receipt->id}/download",
            ['Authorization' => 'Bearer '.$customerToken]
        )->assertOk();
    }

    public function test_customer_cannot_access_other_customer_receipt(): void
    {
        $this->seedPricing();
        Storage::fake('local');

        $owner = User::factory()->create(['role' => UserRole::Customer]);
        $other = User::factory()->create(['role' => UserRole::Customer]);
        $quote = Quote::create([
            'inquiry_id' => null,
            'customer_user_id' => $owner->id,
            'gold_price_snapshot_id' => GoldPriceSnapshot::query()->firstOrFail()->id,
            'exchange_rate_snapshot_id' => ExchangeRateSnapshot::query()->firstOrFail()->id,
            'quote_number' => 'Q-RESTRICT-1',
            'status' => 'accepted',
            'total_mmk' => 120000,
            'total_usd_snapshot' => 50,
        ]);
        $order = Order::create([
            'customer_user_id' => $owner->id,
            'quote_id' => $quote->id,
            'order_number' => 'O-RESTRICT-1',
            'status' => 'deposit_confirmed',
            'deposit_amount_mmk' => 0,
            'total_mmk' => 120000,
            'total_usd_snapshot' => 50,
            'exchange_rate_snapshot_id' => ExchangeRateSnapshot::query()->firstOrFail()->id,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'currency' => 'MMK',
            'amount_mmk' => 50000,
            'status' => Payment::STATUS_CONFIRMED,
            'type' => Payment::TYPE_DEPOSIT,
            'verified_at' => now(),
            'verified_by_user_id' => null,
        ]);
        $receipt = Receipt::create([
            'receipt_number' => 'RCP-TEST-0001',
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'customer_user_id' => $owner->id,
            'currency_code' => 'MMK',
            'amount_mmk' => 50000,
            'amount_usd_snapshot' => 25,
            'branding_name' => 'Shwe Eain Jewelry Hub',
            'pdf_storage_path' => 'receipts/test.pdf',
            'sha256' => str_repeat('a', 64),
            'meta_json' => ['payment_type' => 'deposit'],
            'issued_at' => now(),
        ]);

        Sanctum::actingAs($other);
        $otherToken = $other->createToken('t')->plainTextToken;
        $this->getJson(
            "/api/v1/orders/{$order->id}/receipts/{$receipt->id}",
            ['Authorization' => 'Bearer '.$otherToken]
        )->assertForbidden();
    }
}
