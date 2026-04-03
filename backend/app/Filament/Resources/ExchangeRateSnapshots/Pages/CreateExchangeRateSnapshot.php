<?php

namespace App\Filament\Resources\ExchangeRateSnapshots\Pages;

use App\Filament\Resources\ExchangeRateSnapshots\ExchangeRateSnapshotResource;
use App\Services\PricingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExchangeRateSnapshot extends CreateRecord
{
    protected static string $resource = ExchangeRateSnapshotResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $from = isset($data['effective_from']) && $data['effective_from']
            ? Carbon::parse($data['effective_from'])
            : Carbon::now();

        return app(PricingService::class)->recordExchangeRate(
            $data['mmk_per_usd'],
            $from,
            auth()->id()
        );
    }
}
