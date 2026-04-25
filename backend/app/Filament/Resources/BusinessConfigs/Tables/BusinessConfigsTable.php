<?php

namespace App\Filament\Resources\BusinessConfigs\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('default_deposit_percentage')
                    ->label('Default deposit')
                    ->suffix('%'),
                TextColumn::make('payment_at_risk_hours')
                    ->label('Payment risk')
                    ->suffix('h'),
                TextColumn::make('order_at_risk_hours')
                    ->label('Order risk')
                    ->suffix('h'),
                TextColumn::make('currency_refresh_interval_minutes')
                    ->label('FX refresh')
                    ->suffix(' min'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
