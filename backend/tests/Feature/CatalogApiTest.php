<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createPublishedProduct(array $overrides = []): Product
    {
        $category = Category::create([
            'name' => 'Rings',
            'slug' => 'rings-'.uniqid(),
            'sort_order' => 0,
        ]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'sku' => 'SKU-'.uniqid(),
            'title' => 'Gold Ring',
            'description' => null,
            'weight_grams' => 10,
            'labor_fee_mmk' => 100000,
            'gem_price_mmk' => 40000,
            'is_published' => true,
            'is_ready_made' => true,
            'is_custom_order_template' => false,
        ], $overrides));
    }

    public function test_products_filters_ready_made(): void
    {
        $this->createPublishedProduct(['is_ready_made' => true, 'sku' => 'RM-1']);
        $this->createPublishedProduct(['is_ready_made' => false, 'sku' => 'CT-1']);

        $this->getJson('/api/v1/catalog/products?ready_made=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/catalog/products?ready_made=0')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_products_include_pricing_when_snapshots_exist(): void
    {
        $this->createPublishedProduct();

        GoldPriceSnapshot::create([
            'price_mmk_per_gram' => 160000,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);

        ExchangeRateSnapshot::create([
            'mmk_per_usd' => 2000,
            'effective_from' => now()->subHour(),
            'effective_to' => null,
            'set_by_user_id' => null,
        ]);

        $response = $this->getJson('/api/v1/catalog/products');
        $response->assertOk()
            ->assertJsonPath('meta.pricing_configured', true);

        $pricing = $response->json('data.0.pricing');
        $this->assertNotNull($pricing);
        $this->assertEquals('1600000.0000', $pricing['metal_value_mmk']); // 10 * 160000
        $this->assertArrayHasKey('line_total_mmk', $pricing);
        $this->assertEquals('1740000.0000', $pricing['line_total_mmk']);
        $this->assertEquals('870.0000', $pricing['line_total_usd']);
    }

    public function test_products_pricing_null_when_snapshots_missing(): void
    {
        $this->createPublishedProduct();

        $this->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonPath('meta.pricing_configured', false)
            ->assertJsonPath('data.0.pricing', null);
    }

    public function test_product_detail_includes_images_url_and_meta(): void
    {
        $product = $this->createPublishedProduct();

        $this->getJson('/api/v1/catalog/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('meta.pricing_configured', false);
    }

    public function test_products_include_popular_and_featured_sections(): void
    {
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer+'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);
        $featuredCategory = Category::create([
            'name' => 'Necklaces',
            'slug' => 'necklaces-'.uniqid(),
            'sort_order' => 0,
            'is_featured' => true,
        ]);
        $popularProduct = Product::create([
            'category_id' => $featuredCategory->id,
            'sku' => 'POPULAR-1',
            'title' => 'Popular Necklace',
            'description' => null,
            'weight_grams' => 8,
            'labor_fee_mmk' => 60000,
            'gem_price_mmk' => 20000,
            'is_published' => true,
            'is_ready_made' => true,
            'is_custom_order_template' => false,
        ]);
        Inquiry::create([
            'customer_user_id' => $customer->id,
            'product_id' => $popularProduct->id,
            'message' => 'Interested',
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/catalog/products')->assertOk();
        $response->assertJsonPath('meta.featured_collections.0.id', $featuredCategory->id);
        $response->assertJsonPath('meta.popular_items.0.id', $popularProduct->id);
    }
}
