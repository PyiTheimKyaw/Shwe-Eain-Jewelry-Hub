<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ArtisanOverviewStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $inProduction = Order::query()
            ->where('status', 'in_production')
            ->count();

        return [
            Stat::make(__('Orders in production'), (string) $inProduction)
                ->description(__('Currently active artisan workload'))
                ->color('info'),
        ];
    }
}
