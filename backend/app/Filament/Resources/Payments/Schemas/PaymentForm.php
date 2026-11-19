<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Services\OrderSettlementService;
use App\Services\MediaStorageService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Create payment record')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->columns(2)
                    ->schema([
                        Select::make('order_id')
                            ->label('Order')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if ($state === null) {
                                    return;
                                }
                                $order = \App\Models\Order::query()->find((int) $state);
                                if ($order === null) {
                                    return;
                                }

                                if (in_array($order->status, ['ready', 'awaiting_final_settlement'], true)) {
                                    $remaining = app(OrderSettlementService::class)
                                        ->remainingBalanceFromDepositsMmk($order);
                                    $set('amount_mmk', number_format($remaining, 4, '.', ''));
                                    $set('type', Payment::TYPE_FINAL);
                                }
                            }),
                        Select::make('payment_profile_id')
                            ->label('Payment profile')
                            ->options(
                                PaymentProfile::query()
                                    ->active()
                                    ->ordered()
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->required(fn (Get $get): bool => ($get('payment_method') ?? 'profile') !== 'cash')
                            ->visible(fn (Get $get): bool => ($get('payment_method') ?? 'profile') !== 'cash')
                            ->helperText('Required for bank/wallet transfer.'),
                        Select::make('payment_method')
                            ->label('Collection method')
                            ->options([
                                'profile' => 'Bank / Wallet Transfer',
                                'cash' => 'Cash Payment',
                            ])
                            ->default('profile')
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Select Cash Payment for in-store settlement without payment profile.'),
                        Select::make('type')
                            ->label('Payment type')
                            ->options([
                                Payment::TYPE_DEPOSIT => 'Deposit',
                                Payment::TYPE_FINAL => 'Final',
                            ])
                            ->default(Payment::TYPE_FINAL)
                            ->required(),
                        TextInput::make('amount_mmk')
                            ->label('Amount (MMK)')
                            ->numeric()
                            ->helperText('For pickup/settlement orders, this auto-fills the current remaining balance.')
                            ->required(),
                        Select::make('currency')
                            ->options(['MMK' => 'MMK'])
                            ->default('MMK')
                            ->required(),
                        Select::make('status')
                            ->options([
                                Payment::STATUS_AWAITING_VERIFICATION => 'Awaiting verification',
                                Payment::STATUS_CONFIRMED => 'Confirmed',
                                Payment::STATUS_REJECTED => 'Rejected',
                                Payment::STATUS_PENDING_PROOF => 'Pending proof',
                            ])
                            ->default(Payment::STATUS_AWAITING_VERIFICATION)
                            ->required(),
                        Textarea::make('notes_internal')
                            ->label('Internal notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpan(2),
                        Placeholder::make('settlement_hint')
                            ->label('Settlement status')
                            ->columnSpan(2)
                            ->content(function (Get $get): string {
                                $orderId = $get('order_id');
                                if (blank($orderId)) {
                                    return 'Select an order to view settlement guidance.';
                                }

                                $order = \App\Models\Order::query()->find((int) $orderId);
                                if ($order === null) {
                                    return 'Selected order could not be loaded.';
                                }

                                $remaining = app(OrderSettlementService::class)
                                    ->remainingBalanceFromDepositsMmk($order);

                                if ($remaining <= 0) {
                                    return 'This order is already fully settled. Do not create another final payment.';
                                }

                                return 'Remaining settlement balance: '.number_format($remaining, 2).' MMK';
                            }),
                    ]),
                Section::make('Payment proof review')
                    ->visible(fn (string $operation): bool => $operation !== 'create')
                    ->columns(2)
                    ->schema([
                        TextInput::make('order.order_number')
                            ->label('Order')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('order.customer.name')
                            ->label('Customer')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('amount_mmk')
                            ->label('Amount (MMK)')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('status')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('submitted_at')
                            ->label('Submitted at')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(2),
                        Placeholder::make('proof_preview')
                            ->label('Payment screenshot')
                            ->columnSpan(2)
                            ->content(function (?Payment $record): HtmlString {
                                if (! $record?->proof_storage_path) {
                                    return new HtmlString('<span class="text-sm text-gray-500">No payment screenshot uploaded.</span>');
                                }

                                $url = app(MediaStorageService::class)->publicUrl($record->proof_storage_path);
                                if ($url === '') {
                                    return new HtmlString('<span class="text-sm text-gray-500">Screenshot URL is unavailable.</span>');
                                }

                                $safeUrl = e($url);

                                return new HtmlString(
                                    '<a href="'.$safeUrl.'" target="_blank" rel="noopener noreferrer">'.
                                        '<img src="'.$safeUrl.'" alt="Payment proof" style="max-height: 420px; width: 100%; object-fit: contain; border-radius: 12px; border: 1px solid rgba(148,163,184,0.35);" />'.
                                    '</a>'
                                );
                            }),
                        Textarea::make('notes_internal')
                            ->label('Internal notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
