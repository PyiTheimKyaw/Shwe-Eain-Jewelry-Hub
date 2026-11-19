<?php

namespace App\Filament\Resources\BusinessConfigs\Pages;

use App\Filament\Resources\BusinessConfigs\BusinessConfigResource;
use Filament\Resources\Pages\ListRecords;

class ListBusinessConfigs extends ListRecords
{
    protected static string $resource = BusinessConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
