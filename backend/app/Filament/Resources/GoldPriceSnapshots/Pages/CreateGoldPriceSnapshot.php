<?php

namespace App\Filament\Resources\GoldPriceSnapshots\Pages;

use App\Filament\Resources\GoldPriceSnapshots\GoldPriceSnapshotResource;
use App\Services\AdminAuditLogService;
use App\Services\PricingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateGoldPriceSnapshot extends CreateRecord
{
    protected static string $resource = GoldPriceSnapshotResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $from = isset($data['effective_from']) && $data['effective_from']
            ? Carbon::parse($data['effective_from'])
            : Carbon::now();

        $snapshot = app(PricingService::class)->recordGoldPrice(
            $data['price_mmk_per_gram'],
            $from,
            Auth::id()
        );

        app(AdminAuditLogService::class)->log(
            action: 'admin.pricing.gold_snapshot_created',
            actor: Auth::user(),
            target: $snapshot,
            category: 'admin',
            context: [
                'price_mmk_per_gram' => (string) $snapshot->price_mmk_per_gram,
                'effective_from' => $snapshot->effective_from?->toDateTimeString(),
            ],
        );

        return $snapshot;
    }
}
