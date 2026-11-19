<?php

namespace App\Filament\Resources\ReportExports;

use App\Filament\Resources\ReportExports\Pages\ReportExportHub;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReportExportResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationLabel = 'Report exports';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => ReportExportHub::route('/'),
        ];
    }
}
