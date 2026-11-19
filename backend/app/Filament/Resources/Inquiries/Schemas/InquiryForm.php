<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'reviewing' => 'Reviewing',
                        'quoted' => 'Quoted',
                        'closed' => 'Closed',
                    ])
                    ->required(),
                Textarea::make('notes_internal')
                    ->label('Internal notes')
                    ->columnSpanFull(),
            ]);
    }
}
