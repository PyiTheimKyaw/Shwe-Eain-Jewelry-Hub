<?php

namespace App\Filament\Resources\BusinessConfigs;

use App\Filament\Resources\BusinessConfigs\Pages\EditBusinessConfig;
use App\Filament\Resources\BusinessConfigs\Pages\ListBusinessConfigs;
use App\Filament\Resources\BusinessConfigs\Schemas\BusinessConfigForm;
use App\Filament\Resources\BusinessConfigs\Tables\BusinessConfigsTable;
use App\Models\BusinessConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BusinessConfigResource extends Resource
{
    protected static ?string $model = BusinessConfig::class;

    protected static ?string $navigationLabel = 'Business config';

    protected static string | UnitEnum | null $navigationGroup = 'System Settings';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return BusinessConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessConfigsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessConfigs::route('/'),
            'edit' => EditBusinessConfig::route('/{record}/edit'),
        ];
    }
}
