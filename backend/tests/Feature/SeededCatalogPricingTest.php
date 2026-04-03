<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededCatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seed_enables_catalog_pricing_meta(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonPath('meta.pricing_configured', true);

        $pricing = $response->json('data.0.pricing');
        $this->assertNotNull($pricing);
        $this->assertArrayHasKey('line_total_mmk', $pricing);
    }
}
