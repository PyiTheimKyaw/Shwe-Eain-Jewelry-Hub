<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('occurred_at')
                    ->label('Occurred at')
                    ->dateTime(),
                TextEntry::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System'),
                TextEntry::make('actor.email')
                    ->label('Actor email')
                    ->placeholder('-'),
                TextEntry::make('category')
                    ->badge(),
                TextEntry::make('action')
                    ->copyable(),
                TextEntry::make('target_type')
                    ->label('Target type')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
                TextEntry::make('target_id')
                    ->label('Target ID')
                    ->placeholder('-'),
                TextEntry::make('ip_address')
                    ->label('IP address')
                    ->placeholder('-'),
                TextEntry::make('user_agent')
                    ->label('User agent')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('context')
                    ->label('Context (JSON)')
                    ->state(fn ($record): string => json_encode($record->context ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                    ->columnSpanFull(),
            ]);
    }
}
