<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Services\MediaStorageService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order.order_number')
                    ->label('Order')
                    ->copyable(),
                TextEntry::make('order.customer.name')
                    ->label('Customer')
                    ->placeholder('-'),
                TextEntry::make('amount_mmk')
                    ->label('Amount (MMK)')
                    ->money('MMK'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('submitted_at')
                    ->label('Submitted at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('verifiedBy.name')
                    ->label('Reviewed by')
                    ->placeholder('-'),
                TextEntry::make('verified_at')
                    ->label('Reviewed at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('rejection_reason')
                    ->label('Rejection reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('proof_preview')
                    ->label('Payment screenshot')
                    ->html()
                    ->state(function (Payment $record): string {
                        if (! $record->proof_storage_path) {
                            return '<span class="text-sm text-gray-500">No payment screenshot uploaded.</span>';
                        }

                        $url = app(MediaStorageService::class)->publicUrl($record->proof_storage_path);
                        if ($url === '') {
                            return '<span class="text-sm text-gray-500">Screenshot URL is unavailable.</span>';
                        }

                        $safeUrl = e($url);

                        return '<a href="'.$safeUrl.'" target="_blank" rel="noopener noreferrer">'.
                            '<img src="'.$safeUrl.'" alt="Payment proof" style="max-height: 520px; width: 100%; object-fit: contain; border-radius: 14px; border: 1px solid rgba(148,163,184,0.35);" />'.
                            '</a>';
                    })
                    ->columnSpanFull(),
            ]);
    }
}
