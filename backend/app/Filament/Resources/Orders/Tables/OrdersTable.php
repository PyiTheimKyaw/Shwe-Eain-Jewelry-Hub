<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflow_age')
                    ->label('Workflow age')
                    ->getStateUsing(fn (Order $record): string => $record->created_at?->diffForHumans() ?? '—')
                    ->badge()
                    ->color(function (Order $record): string {
                        $hours = (int) ($record->created_at?->diffInHours(now()) ?? 0);

                        return $hours < 24 ? 'success' : ($hours < 72 ? 'warning' : 'danger');
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'awaiting_deposit' => 'warning',
                        'deposit_confirmed' => 'info',
                        'in_production' => 'primary',
                        'pending_qc' => 'gray',
                        'ready' => 'success',
                        'shipped' => 'success',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_usd_snapshot')
                    ->label('Total (USD)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deposit_amount_mmk')
                    ->label('Deposit (MMK)')
                    ->sortable(),
                TextColumn::make('total_mmk')
                    ->label('Total (MMK)')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('status')
                    ->form([
                        Select::make('status')
                            ->options([
                                'awaiting_deposit' => 'Awaiting deposit',
                                'deposit_confirmed' => 'Deposit confirmed',
                                'in_production' => 'In production',
                                'pending_qc' => 'Pending QC',
                                'ready' => 'Ready',
                                'shipped' => 'Shipped',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
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
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                Action::make('openInquiryChat')
                    ->label('Open related inquiry chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(function (Order $record): ?string {
                        $inquiryId = $record->quote?->inquiry_id;
                        if (! $inquiryId) {
                            return null;
                        }

                        return InquiryResource::getUrl('chat', ['record' => $inquiryId]);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (Order $record): bool => (bool) $record->quote?->inquiry_id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export')
                        ->label('Export selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->form([
                            Select::make('format')
                                ->options([
                                    'csv' => 'CSV',
                                    'xlsx' => 'Excel (.xlsx)',
                                ])
                                ->default('xlsx')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $ids = $records->pluck('id')->implode(',');

                            return redirect()->to(route('admin.exports.orders.bulk', [
                                'ids' => $ids,
                                'format' => (string) ($data['format'] ?? 'xlsx'),
                            ]));
                        }),
                ]),
            ]);
    }
}
