<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Services\PricingService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayMmk = (float) Payment::query()
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereDate('verified_at', now()->toDateString())
            ->sum('amount_mmk');

        $monthMmk = (float) Payment::query()
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereBetween('verified_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_mmk');

        $pricing = app(PricingService::class);
        $fx = $pricing->currentFxSnapshot();
        $todayUsd = $fx ? (float) $pricing->mmkToUsd((string) $todayMmk, $fx) : 0.0;
        $monthUsd = $fx ? (float) $pricing->mmkToUsd((string) $monthMmk, $fx) : 0.0;

        return [
            Stat::make(
                __('Revenue today'),
                sprintf('%s MMK / %s USD', number_format($todayMmk, 2), number_format($todayUsd, 2))
            )
                ->description(__('Confirmed payments verified today'))
                ->color('success'),
            Stat::make(
                __('Revenue this month'),
                sprintf('%s MMK / %s USD', number_format($monthMmk, 2), number_format($monthUsd, 2))
            )
                ->description(__('Confirmed payments in current month'))
                ->color('success'),
        ];
    }
}
