<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Services\MediaStorageService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected function mediaDisk(): string
    {
        return app(MediaStorageService::class)->writeDiskName();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('storage_path')
                    ->label('Image')
                    ->image()
                    ->disk($this->mediaDisk())
                    ->directory(fn (): string => 'products/'.$this->getOwnerRecord()->getKey())
                    ->visibility('public')
                    ->required()
                    ->maxSize(10240),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('storage_path')
            ->columns([
                ImageColumn::make('storage_path')
                    ->getStateUsing(fn ($record) => app(MediaStorageService::class)->publicUrl($record->storage_path))
                    ->label('Preview'),
                TextColumn::make('storage_path')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
