<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InquiryQuoteFlowTest extends TestCase
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

    public function test_admin_publish_creates_sent_quote_and_customer_can_accept_into_order(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Custom ring',
            'status' => 'open',
            'firebase_thread_id' => 'test-thread-uuid-1',
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
        $publish->assertJsonPath('data.status', 'sent');

        $quoteId = (int) $publish->json('data.id');
        $this->assertDatabaseHas('quotes', [
            'id' => $quoteId,
            'status' => 'sent',
            'inquiry_id' => $inquiry->id,
        ]);

        Sanctum::actingAs($customer);

        $accept = $this->postJson("/api/v1/quotes/{$quoteId}/accept");

        $accept->assertOk();
        $accept->assertJsonPath('data.order.order_number', fn ($v) => is_string($v) && strlen($v) > 0);

        $this->assertDatabaseHas('quotes', ['id' => $quoteId, 'status' => 'accepted']);

        $orderId = (int) $accept->json('data.order.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'quote_id' => $quoteId,
            'status' => 'awaiting_deposit',
        ]);
    }

    public function test_customer_cannot_accept_draft_quote(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();

        $adminToken = $admin->createToken('t')->plainTextToken;

        $this->postJson('/api/v1/admin/quotes', [
            'customer_user_id' => $customer->id,
            'lines' => [
                ['weight_grams' => 5, 'labor_fee_mmk' => 1000, 'gem_price_mmk' => 0],
            ],
        ], ['Authorization' => 'Bearer '.$adminToken])->assertCreated();

        $quote = Quote::query()->where('customer_user_id', $customer->id)->first();
        $this->assertSame('draft', $quote->status);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/quotes/{$quote->id}/accept")->assertStatus(403);
    }

    public function test_customer_can_decline_sent_quote(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Necklace',
            'status' => 'open',
            'firebase_thread_id' => 'test-thread-uuid-2',
        ]);

        $adminToken = $admin->createToken('t')->plainTextToken;

        $publish = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [
                    [
                        'weight_grams' => 2,
                        'labor_fee_mmk' => 10000,
                        'gem_price_mmk' => 0,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );

        $quoteId = (int) $publish->json('data.id');

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/quotes/{$quoteId}/decline")->assertOk();

        $this->assertDatabaseHas('quotes', ['id' => $quoteId, 'status' => 'void']);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'status' => 'reviewing']);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_accepting_newer_quote_supersedes_awaiting_deposit_order(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Ring',
            'status' => 'open',
            'firebase_thread_id' => 'test-thread-super-1',
        ]);

        $q1 = $this->postJson(
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
        $q1->assertCreated();
        $quoteId1 = (int) $q1->json('data.id');
        $total1 = (float) $q1->json('data.total_mmk');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId1}/accept")->assertOk();
        $order1 = Order::query()->where('quote_id', $quoteId1)->first();
        $this->assertNotNull($order1);
        $this->assertSame('awaiting_deposit', $order1->status);

        Sanctum::actingAs($admin);
        $adminToken2 = $admin->createToken('t2')->plainTextToken;
        $q2 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [
                    [
                        'weight_grams' => 1,
                        'labor_fee_mmk' => 100,
                        'gem_price_mmk' => 0,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken2]
        );
        $q2->assertCreated();
        $quoteId2 = (int) $q2->json('data.id');
        $total2 = (float) $q2->json('data.total_mmk');

        Sanctum::actingAs($customer);
        $accept2 = $this->postJson("/api/v1/quotes/{$quoteId2}/accept");
        $accept2->assertOk();

        $this->assertDatabaseHas('quotes', ['id' => $quoteId1, 'status' => 'void']);
        $this->assertDatabaseHas('quotes', ['id' => $quoteId2, 'status' => 'accepted']);

        $order1->refresh();
        $this->assertSame('cancelled', $order1->status);

        $order2Id = (int) $accept2->json('data.order.id');
        $order2 = Order::query()->findOrFail($order2Id);
        $this->assertSame($quoteId2, $order2->quote_id);
        $this->assertSame('awaiting_deposit', $order2->status);
        $expectedDeposit = round($total2 * 0.5, 4);
        $this->assertEqualsWithDelta($expectedDeposit, (float) $order2->deposit_amount_mmk, 0.01);
        $this->assertEqualsWithDelta($total2, (float) $order2->total_mmk, 0.01);
        $this->assertNotEquals($total1, $total2);
    }

    public function test_can_publish_new_quote_while_deposit_awaiting_verification(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Pendant',
            'status' => 'open',
            'firebase_thread_id' => 'test-thread-super-2',
        ]);

        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [
                    [
                        'weight_grams' => 5,
                        'labor_fee_mmk' => 10000,
                        'gem_price_mmk' => 0,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $quoteId1 = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId1}/accept")->assertOk();
        $order1 = Order::query()->where('quote_id', $quoteId1)->firstOrFail();

        Payment::query()->create([
            'order_id' => $order1->id,
            'payment_profile_id' => null,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => $order1->deposit_amount_mmk,
            'currency' => 'MMK',
            'status' => Payment::STATUS_AWAITING_VERIFICATION,
        ]);

        Sanctum::actingAs($admin);
        $publish2 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [
                    [
                        'weight_grams' => 1,
                        'labor_fee_mmk' => 500,
                        'gem_price_mmk' => 0,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish2->assertCreated();
        $this->assertArrayNotHasKey('warning', $publish2->json('meta') ?? []);
    }

    public function test_customer_can_accept_republished_quote_with_date_only_valid_until_today(): void
    {
        $this->seedPricing();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Bracelet redesign',
            'status' => 'open',
            'firebase_thread_id' => 'test-thread-republish-valid-until',
        ]);

        $publish1 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [
                    [
                        'weight_grams' => 3,
                        'labor_fee_mmk' => 12000,
                        'gem_price_mmk' => 0,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish1->assertCreated();
        $quoteId1 = (int) $publish1->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId1}/decline")->assertOk();

        Sanctum::actingAs($admin);
        $publish2 = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'valid_until' => now()->toDateString(),
                'lines' => [
                    [
                        'weight_grams' => 4,
                        'labor_fee_mmk' => 15000,
                        'gem_price_mmk' => 1000,
                    ],
                ],
            ],
            ['Authorization' => 'Bearer '.$adminToken]
        );
        $publish2->assertCreated();
        $quoteId2 = (int) $publish2->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/quotes/{$quoteId2}/accept")
            ->assertOk()
            ->assertJsonPath('data.quote.id', $quoteId2);
    }
}
