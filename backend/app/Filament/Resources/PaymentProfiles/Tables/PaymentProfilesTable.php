<?php

namespace App\Filament\Resources\PaymentProfiles\Tables;

use App\Enums\PaymentChannel;
use App\Services\MediaStorageService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PaymentChannel
                        ? $state->label()
                        : (PaymentChannel::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label(__('Number'))
                    ->limit(24),
                ImageColumn::make('qr_image_path')
                    ->label(__('QR'))
                    ->getStateUsing(
                        fn ($record): string => app(MediaStorageService::class)->publicUrl($record->qr_image_path)
                    )
                    ->height(40),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
