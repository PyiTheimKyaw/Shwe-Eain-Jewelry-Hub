<?php

namespace App\Filament\Resources\GoldPriceSnapshots\Pages;

use App\Filament\Resources\GoldPriceSnapshots\GoldPriceSnapshotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGoldPriceSnapshots extends ListRecords
{
    protected static string $resource = GoldPriceSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
