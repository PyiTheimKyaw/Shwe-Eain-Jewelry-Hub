<?php

namespace App\Filament\Resources\ExchangeRateSnapshots\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExchangeRateSnapshotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('mmk_per_usd')
                    ->label('MMK/USD')
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
