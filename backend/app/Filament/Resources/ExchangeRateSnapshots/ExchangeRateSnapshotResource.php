<?php

namespace App\Filament\Resources\ExchangeRateSnapshots;

use App\Filament\Resources\ExchangeRateSnapshots\Pages\CreateExchangeRateSnapshot;
use App\Filament\Resources\ExchangeRateSnapshots\Pages\ListExchangeRateSnapshots;
use App\Filament\Resources\ExchangeRateSnapshots\Schemas\ExchangeRateSnapshotForm;
use App\Filament\Resources\ExchangeRateSnapshots\Tables\ExchangeRateSnapshotsTable;
use App\Models\ExchangeRateSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ExchangeRateSnapshotResource extends Resource
{
    protected static ?string $model = ExchangeRateSnapshot::class;

    protected static ?string $navigationLabel = 'Exchange rates';

    protected static ?string $modelLabel = 'exchange rate snapshot';

    protected static ?string $pluralModelLabel = 'exchange rate snapshots';

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function form(Schema $schema): Schema
    {
        return ExchangeRateSnapshotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExchangeRateSnapshotsTable::configure($table);
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
            'index' => ListExchangeRateSnapshots::route('/'),
            'create' => CreateExchangeRateSnapshot::route('/create'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
