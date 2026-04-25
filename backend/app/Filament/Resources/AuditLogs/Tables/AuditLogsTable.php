<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Occurred at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->searchable()
                    ->placeholder('System'),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('action')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('target_type')
                    ->label('Target')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        return class_basename($state);
                    }),
                TextColumn::make('target_id')
                    ->label('Target ID')
                    ->placeholder('-'),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->searchable()
                    ->options([
                        'admin.payment.confirmed' => 'Payment confirmed',
                        'admin.payment.rejected' => 'Payment rejected',
                        'admin.order.qc_approved' => 'QC approved',
                        'admin.order.assignment.created' => 'Artisan assignment created',
                    ]),
                SelectFilter::make('category')
                    ->options([
                        'admin' => 'Admin',
                        'workflow' => 'Workflow',
                        'system' => 'System',
                    ]),
                Filter::make('actor_user_id')
                    ->label('Actor user ID')
                    ->form([
                        TextInput::make('actor_user_id')
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['actor_user_id'])) {
                            $query->where('actor_user_id', (int) $data['actor_user_id']);
                        }

                        return $query;
                    }),
                Filter::make('occurred_window')
                    ->label('Occurred window')
                    ->form([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('to')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['from'])) {
                            $query->whereDate('occurred_at', '>=', $data['from']);
                        }
                        if (! empty($data['to'])) {
                            $query->whereDate('occurred_at', '<=', $data['to']);
                        }

                        return $query;
                    }),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->recordUrl(fn (AuditLog $record): string => AuditLogResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
