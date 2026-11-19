<?php

namespace App\Filament\Resources\ExchangeRateSnapshots\Pages;

use App\Filament\Resources\ExchangeRateSnapshots\ExchangeRateSnapshotResource;
use App\Services\AdminAuditLogService;
use App\Services\PricingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateExchangeRateSnapshot extends CreateRecord
{
    protected static string $resource = ExchangeRateSnapshotResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $from = isset($data['effective_from']) && $data['effective_from']
            ? Carbon::parse($data['effective_from'])
            : Carbon::now();

        $snapshot = app(PricingService::class)->recordExchangeRate(
            $data['mmk_per_usd'],
            $from,
            Auth::id()
        );

        app(AdminAuditLogService::class)->log(
            action: 'admin.pricing.fx_snapshot_created',
            actor: Auth::user(),
            target: $snapshot,
            category: 'admin',
            context: [
                'mmk_per_usd' => (string) $snapshot->mmk_per_usd,
                'effective_from' => $snapshot->effective_from?->toDateTimeString(),
            ],
        );

        return $snapshot;
    }
}
