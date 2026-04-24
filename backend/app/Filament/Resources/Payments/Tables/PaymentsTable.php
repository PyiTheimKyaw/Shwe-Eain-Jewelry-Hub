<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Services\MediaStorageService;
use App\Services\PaymentVerificationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                ImageColumn::make('proof_storage_path')
                    ->label('Proof')
                    ->circular()
                    ->size(40)
                    ->getStateUsing(
                        fn (Payment $record): string => app(MediaStorageService::class)->publicUrl($record->proof_storage_path)
                    ),
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
            ->recordUrl(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record])),
                Action::make('confirm')
                    ->label('Approve payment')
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
