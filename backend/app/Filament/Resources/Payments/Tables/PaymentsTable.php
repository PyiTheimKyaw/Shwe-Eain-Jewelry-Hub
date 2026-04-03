<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Payment;
use App\Services\PaymentVerificationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.customer.name')
                    ->label('Customer')
                    ->placeholder('—'),
                TextColumn::make('amount_mmk')
                    ->label('Amount (MMK)')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirm deposit')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record) => $record->status === Payment::STATUS_AWAITING_VERIFICATION)
                    ->action(function (Payment $record) {
                        app(PaymentVerificationService::class)->confirm(auth()->user(), $record);
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->status === Payment::STATUS_AWAITING_VERIFICATION)
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason (optional)')
                            ->rows(3),
                    ])
                    ->action(function (Payment $record, array $data) {
                        app(PaymentVerificationService::class)->reject(
                            $record,
                            $data['reason'] ?? null
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
