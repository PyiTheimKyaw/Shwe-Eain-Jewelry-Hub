<?php

namespace App\Filament\Resources\ExchangeRateSnapshots\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExchangeRateSnapshotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mmk_per_usd')
                    ->label('MMK per 1 USD')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(2100),
                DateTimePicker::make('effective_from')
                    ->label('Effective from')
                    ->required()
                    ->default(now())
                    ->seconds(false),
            ]);
    }
}
