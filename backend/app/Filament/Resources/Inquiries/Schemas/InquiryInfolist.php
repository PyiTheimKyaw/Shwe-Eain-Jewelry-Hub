<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InquiryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('customer.name')
                    ->label('Customer name')
                    ->placeholder('-'),
                TextEntry::make('customer.email')
                    ->label('Customer email')
                    ->placeholder('-'),
                TextEntry::make('message')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('firebase_thread_id')
                    ->placeholder('-')
                    ->copyable(),
                TextEntry::make('notes_internal')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('product.title')
                    ->label('Product')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
