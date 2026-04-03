<?php

namespace App\Filament\Resources\GoldPriceSnapshots\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GoldPriceSnapshotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('price_mmk_per_gram')
                    ->label('MMK/g')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('effective_from')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('setByUser.name')
                    ->label('Set by')
                    ->placeholder('—'),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
