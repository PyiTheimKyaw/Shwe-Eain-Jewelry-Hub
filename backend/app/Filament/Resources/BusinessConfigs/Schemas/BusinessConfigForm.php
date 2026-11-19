<?php

namespace App\Filament\Resources\BusinessConfigs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BusinessConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('default_deposit_percentage')
                    ->label('Default deposit percentage')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(1)
                    ->maxValue(100)
                    ->required(),
                TextInput::make('payment_at_risk_hours')
                    ->label('Payment at-risk threshold (hours)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                TextInput::make('order_at_risk_hours')
                    ->label('Order at-risk threshold (hours)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                TextInput::make('currency_refresh_interval_minutes')
                    ->label('Currency refresh interval (minutes)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Textarea::make('payment_accept_template')
                    ->label('Default accept notification template')
                    ->helperText('Used when deposit is confirmed.'),
                Textarea::make('payment_reject_template')
                    ->label('Default reject notification template')
                    ->helperText('Supports placeholder: {reason}'),
            ]);
    }
}
