<?php

namespace Tests\Feature;

use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCurrentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_gold_current_returns_null_when_unconfigured(): void
    {
        $this->getJson('/api/v1/pricing/gold/current')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.configured', false);
    }

    public function test_fx_current_returns_null_when_unconfigured(): void
    {
        $this->getJson('/api/v1/pricing/fx/current')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.configured', false);
    }

    public function test_gold_and_fx_current_return_active_snapshots(): void
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

        $this->getJson('/api/v1/pricing/gold/current')
            ->assertOk()
            ->assertJsonPath('data.price_mmk_per_gram', '150000.0000')
            ->assertJsonPath('meta.configured', true);

        $this->getJson('/api/v1/pricing/fx/current')
            ->assertOk()
            ->assertJsonPath('data.mmk_per_usd', '2100.5000')
            ->assertJsonPath('meta.configured', true);
    }

    public function test_admin_can_post_gold_and_fx_snapshots(): void
    {
        $admin = $this->adminUser();
        $token = $admin->createToken('t')->plainTextToken;

        $this->postJson('/api/v1/pricing/gold', [
            'price_mmk_per_gram' => 155000,
        ], ['Authorization' => 'Bearer '.$token])
            ->assertCreated()
            ->assertJsonPath('data.price_mmk_per_gram', '155000.0000');

        $this->postJson('/api/v1/pricing/fx', [
            'mmk_per_usd' => 2050,
        ], ['Authorization' => 'Bearer '.$token])
            ->assertCreated()
            ->assertJsonPath('data.mmk_per_usd', '2050.0000');
    }
}
