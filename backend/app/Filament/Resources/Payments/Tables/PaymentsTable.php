<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Services\MediaStorageService;
use App\Services\PaymentVerificationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

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
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('amount_mmk')
                    ->label('Amount (MMK)')
                    ->sortable(),
                TextColumn::make('order.total_usd_snapshot')
                    ->label('Amount (USD snapshot)')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('status')
                    ->form([
                        Select::make('status')
                            ->options([
                                Payment::STATUS_PENDING_PROOF => 'Pending proof',
                                Payment::STATUS_AWAITING_VERIFICATION => 'Awaiting verification',
                                Payment::STATUS_CONFIRMED => 'Confirmed',
                                Payment::STATUS_REJECTED => 'Rejected',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['status'])) {
                            $query->where('status', $data['status']);
                        }

                        return $query;
                    }),
                Filter::make('submitted_window')
                    ->label('Submitted window')
                    ->form([
                        DatePicker::make('submitted_from')->native(false),
                        DatePicker::make('submitted_until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['submitted_from'])) {
                            $query->whereDate('submitted_at', '>=', $data['submitted_from']);
                        }
                        if (! empty($data['submitted_until'])) {
                            $query->whereDate('submitted_at', '<=', $data['submitted_until']);
                        }

                        return $query;
                    }),
                Filter::make('verified_window')
                    ->label('Verified window')
                    ->form([
                        DatePicker::make('verified_from')->native(false),
                        DatePicker::make('verified_until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['verified_from'])) {
                            $query->whereDate('verified_at', '>=', $data['verified_from']);
                        }
                        if (! empty($data['verified_until'])) {
                            $query->whereDate('verified_at', '<=', $data['verified_until']);
                        }

                        return $query;
                    }),
                Filter::make('order_lookup')
                    ->label('Order / customer')
                    ->form([
                        Select::make('field')
                            ->options([
                                'order' => 'Order number',
                                'customer' => 'Customer name',
                            ])
                            ->default('order')
                            ->native(false),
                        TextInput::make('term')
                            ->label('Search term'),
                    ])
                    ->query(function ($query, array $data) {
                        $term = trim((string) ($data['term'] ?? ''));
                        if ($term === '') {
                            return $query;
                        }

                        $field = (string) ($data['field'] ?? 'order');
                        if ($field === 'customer') {
                            $query->whereHas('order.customer', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                        } else {
                            $query->whereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$term}%"));
                        }

                        return $query;
                    }),
            ])
            ->recordUrl(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]))
            ->defaultSort('submitted_at', 'desc')
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
                        $user = Auth::user();
                        if ($user === null) {
                            return;
                        }
                        app(PaymentVerificationService::class)->confirm($user, $record);
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

                            return redirect()->to(route('admin.exports.payments.bulk', [
                                'ids' => $ids,
                                'format' => (string) ($data['format'] ?? 'xlsx'),
                            ]));
                        }),
                ]),
            ]);
    }
}
