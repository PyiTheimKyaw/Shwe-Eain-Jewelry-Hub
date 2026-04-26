<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Inquiry;
use App\Models\QuoteLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryProductPrefillTest extends TestCase
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

    public function test_publish_quote_prefills_missing_line_fields_from_inquiry_linked_product(): void
    {
        $this->seedPricing();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $token = $admin->createToken('t')->plainTextToken;

        $category = Category::create(['name' => 'Rings', 'slug' => 'rings', 'sort_order' => 1]);
        $product = \App\Models\Product::create([
            'category_id' => $category->id,
            'sku' => 'R-100',
            'title' => 'Halo Ring',
            'description' => 'Ready-made ring',
            'weight_grams' => 6.2,
            'labor_fee_mmk' => 50000,
            'gem_price_mmk' => 25000,
            'is_published' => true,
            'is_ready_made' => true,
            'is_custom_order_template' => true,
        ]);

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => $product->id,
            'message' => 'I want this design',
            'status' => 'open',
            'firebase_thread_id' => 'thread-prefill-1',
        ]);

        $response = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => [[]]],
            ['Authorization' => 'Bearer '.$token]
        );

        $response->assertCreated();
        $line = QuoteLineItem::query()->latest('id')->firstOrFail();
        $this->assertSame($product->id, $line->product_id);
        $this->assertSame('Halo Ring', $line->custom_title);
        $this->assertEqualsWithDelta(6.2, (float) $line->weight_grams, 0.001);
        // Canonical product-linked pricing remains authoritative in builder.
        $this->assertEqualsWithDelta(50000, (float) $line->labor_fee_mmk, 0.001);
        $this->assertEqualsWithDelta(25000, (float) $line->gem_price_mmk, 0.001);
    }

    public function test_manual_values_are_preserved_when_prefill_runs(): void
    {
        $this->seedPricing();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $token = $admin->createToken('t')->plainTextToken;

        $category = Category::create(['name' => 'Necklace', 'slug' => 'necklace', 'sort_order' => 1]);
        $product = \App\Models\Product::create([
            'category_id' => $category->id,
            'sku' => 'N-100',
            'title' => 'Diamond Necklace',
            'description' => 'Customizable',
            'weight_grams' => 12.0,
            'labor_fee_mmk' => 80000,
            'gem_price_mmk' => 120000,
            'is_published' => true,
            'is_ready_made' => false,
            'is_custom_order_template' => true,
        ]);

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => $product->id,
            'message' => 'Need custom variation',
            'status' => 'open',
            'firebase_thread_id' => 'thread-prefill-2',
        ]);

        $response = $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            [
                'lines' => [[
                    'custom_title' => 'Custom bridal necklace',
                    'weight_grams' => 14.5,
                    'metal_type' => '18K White Gold',
                ]],
            ],
            ['Authorization' => 'Bearer '.$token]
        );

        $response->assertCreated();
        $line = QuoteLineItem::query()->latest('id')->firstOrFail();
        $this->assertSame('Custom bridal necklace', $line->custom_title);
        $this->assertEqualsWithDelta(14.5, (float) $line->weight_grams, 0.001);
        $this->assertSame('18K White Gold', $line->metal_type);
    }

    public function test_blank_slate_inquiry_without_linked_product_still_requires_weight(): void
    {
        $this->seedPricing();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $token = $admin->createToken('t')->plainTextToken;

        $inquiry = Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Pure bespoke',
            'status' => 'open',
            'firebase_thread_id' => 'thread-prefill-3',
        ]);

        $this->postJson(
            "/api/v1/admin/inquiries/{$inquiry->id}/quotes/publish",
            ['lines' => [[]]],
            ['Authorization' => 'Bearer '.$token]
        )->assertStatus(422);
    }
}
