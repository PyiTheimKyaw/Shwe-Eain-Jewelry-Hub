<?php

namespace App\Filament\Resources\GoldPriceSnapshots\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GoldPriceSnapshotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('price_mmk_per_gram')
                    ->label('Gold price (MMK per gram)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(168000),
                DateTimePicker::make('effective_from')
                    ->label('Effective from')
                    ->required()
                    ->default(now())
                    ->seconds(false),
            ]);
    }
}
