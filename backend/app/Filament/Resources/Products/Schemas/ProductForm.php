<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('weight_grams')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10000)
                    ->step(0.0001),
                TextInput::make('labor_fee_mmk')
                    ->label('Labor fee (MMK)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('gem_price_mmk')
                    ->label('Gem price (MMK)')
                    ->numeric()
                    ->minValue(0),
                Toggle::make('is_published')
                    ->default(false),
                Toggle::make('is_ready_made')
                    ->default(true),
                Toggle::make('is_custom_order_template')
                    ->default(false),
            ]);
    }
}
