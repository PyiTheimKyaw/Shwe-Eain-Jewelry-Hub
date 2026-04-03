<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Inquiry;
use App\Models\Order;
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
}
