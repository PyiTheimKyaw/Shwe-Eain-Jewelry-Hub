<?php

namespace App\Filament\Resources\Products\Tables;

use App\Services\MediaStorageService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Image')
                    ->getStateUsing(
                        fn ($record) => app(MediaStorageService::class)->publicUrl(
                            $record->images()->orderBy('sort_order')->value('storage_path')
                        )
                    )
                    ->circular(false)
                    ->size(56),
                TextColumn::make('category.name')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('weight_grams')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('labor_fee_mmk')
                    ->label('Labor (MMK)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('gem_price_mmk')
                    ->label('Gems (MMK)')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_published')
                    ->boolean(),
                IconColumn::make('is_custom_order_template')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_ready_made')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
