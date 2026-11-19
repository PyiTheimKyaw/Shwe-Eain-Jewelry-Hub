<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpenInquiriesStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $active = Inquiry::query()->whereIn('status', ['open', 'reviewing'])->count();

        return [
            Stat::make(__('Active inquiries'), (string) $active)
                ->description(__('Open + reviewing'))
                ->color('warning'),
        ];
    }
}
