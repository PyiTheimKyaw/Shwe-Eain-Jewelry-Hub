<?php

namespace App\Filament\Resources\PaymentProfiles\Schemas;

use App\Enums\PaymentChannel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Display name'))
                    ->required()
                    ->maxLength(255)
                    ->helperText(__('Shown to customers on the payment card (e.g. Office KBZ).')),
                Select::make('channel')
                    ->label(__('Channel'))
                    ->options(collect(PaymentChannel::cases())->mapWithKeys(
                        fn (PaymentChannel $c) => [$c->value => $c->label()]
                    ))
                    ->required()
                    ->native(false),
                TextInput::make('institution_name')
                    ->label(__('Bank / institution'))
                    ->maxLength(255)
                    ->helperText(__('For bank transfer: name of the bank. Optional for mobile wallets.')),
                TextInput::make('account_name')
                    ->label(__('Account name'))
                    ->maxLength(255),
                TextInput::make('account_number')
                    ->label(__('Account number'))
                    ->maxLength(255),
                TextInput::make('wallet_id')
                    ->label(__('Wallet / KPay ID'))
                    ->maxLength(255)
                    ->helperText(__('Optional. If set, shown as the wallet ID instead of the account number where applicable.')),
                FileUpload::make('qr_image_path')
                    ->label(__('QR code image'))
                    ->image()
                    ->disk('public')
                    ->directory('payment-profiles')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->imagePreviewHeight('120')
                    ->helperText(__('Optional. Shown on the customer app payment card.')),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
                TextInput::make('sort_order')
                    ->label(__('Sort order'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }
}
