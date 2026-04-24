<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Services\MediaStorageService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment proof review')
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
