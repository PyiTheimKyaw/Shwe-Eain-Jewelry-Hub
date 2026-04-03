<?php

namespace App\Filament\Resources\GoldPriceSnapshots\Pages;

use App\Filament\Resources\GoldPriceSnapshots\GoldPriceSnapshotResource;
use App\Services\PricingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGoldPriceSnapshot extends CreateRecord
{
    protected static string $resource = GoldPriceSnapshotResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $from = isset($data['effective_from']) && $data['effective_from']
            ? Carbon::parse($data['effective_from'])
            : Carbon::now();

        return app(PricingService::class)->recordGoldPrice(
            $data['price_mmk_per_gram'],
            $from,
            auth()->id()
        );
    }
}
