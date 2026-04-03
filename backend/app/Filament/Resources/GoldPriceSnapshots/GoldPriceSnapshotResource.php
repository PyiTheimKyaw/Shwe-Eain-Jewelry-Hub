<?php

namespace App\Filament\Resources\GoldPriceSnapshots;

use App\Filament\Resources\GoldPriceSnapshots\Pages\CreateGoldPriceSnapshot;
use App\Filament\Resources\GoldPriceSnapshots\Pages\ListGoldPriceSnapshots;
use App\Filament\Resources\GoldPriceSnapshots\Schemas\GoldPriceSnapshotForm;
use App\Filament\Resources\GoldPriceSnapshots\Tables\GoldPriceSnapshotsTable;
use App\Models\GoldPriceSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GoldPriceSnapshotResource extends Resource
{
    protected static ?string $model = GoldPriceSnapshot::class;

    protected static ?string $navigationLabel = 'Gold prices';

    protected static ?string $modelLabel = 'gold price snapshot';

    protected static ?string $pluralModelLabel = 'gold price snapshots';

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    public static function form(Schema $schema): Schema
    {
        return GoldPriceSnapshotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoldPriceSnapshotsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('setByUser');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoldPriceSnapshots::route('/'),
            'create' => CreateGoldPriceSnapshot::route('/create'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
