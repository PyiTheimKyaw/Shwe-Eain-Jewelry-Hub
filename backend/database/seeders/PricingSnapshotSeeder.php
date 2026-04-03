<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Database\Seeder;

/**
 * Active gold (MMK/gram) + MMK-per-USD snapshots so catalog estimates and quotes work out of the box.
 * Idempotent: only inserts when no currently effective snapshot exists for that series.
 */
class PricingSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $pricing = app(PricingService::class);
        $adminId = User::query()->where('role', UserRole::Admin)->value('id');

        if ($pricing->currentGoldSnapshot() === null) {
            // ~80 USD/gram at 2100 MMK/USD — demo spot for FYP / local dev
            $pricing->recordGoldPrice(168000, now()->subDay(), $adminId);
        }

        if ($pricing->currentFxSnapshot() === null) {
            $pricing->recordExchangeRate(2100, now()->subDay(), $adminId);
        }
    }
}
