<?php

namespace App\Filament\Resources\ExchangeRateSnapshots\Pages;

use App\Filament\Resources\ExchangeRateSnapshots\ExchangeRateSnapshotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExchangeRateSnapshots extends ListRecords
{
    protected static string $resource = ExchangeRateSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
