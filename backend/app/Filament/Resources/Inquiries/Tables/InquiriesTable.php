<?php

namespace App\Filament\Resources\Inquiries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class InquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('1s')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('message')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('status')
                    ->form([
                        Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'reviewing' => 'Reviewing',
                                'quoted' => 'Quoted',
                                'in_production' => 'In production',
                                'ready' => 'Ready',
                                'closed' => 'Closed',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['status'])) {
                            $query->where('status', $data['status']);
                        }

                        return $query;
                    }),
                Filter::make('created_window')
                    ->label('Created window')
                    ->form([
                        DatePicker::make('created_from')->native(false),
                        DatePicker::make('created_until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['created_from'])) {
                            $query->whereDate('created_at', '>=', $data['created_from']);
                        }
                        if (! empty($data['created_until'])) {
                            $query->whereDate('created_at', '<=', $data['created_until']);
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
