<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use App\Services\DashboardAnalyticsService;
use App\Services\PricingService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommandCenterKpisWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Command center';

    protected ?string $description = 'Spot pricing, pipeline demand, and SLA risk at a glance';

    protected int|string|array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        return '60s';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $pricing = app(PricingService::class);
        $gold = $pricing->currentGoldMmkPerGram();
        $fx = $pricing->currentMmkPerUsd();

        $goldLine = $gold !== null
            ? number_format((float) $gold, 2).' MMK / g'
            : '—';
        $fxLine = $fx !== null
            ? number_format((float) $fx, 2).' MMK / USD'
            : '—';

        $activeInquiries = Inquiry::query()
            ->whereIn('status', ['open', 'reviewing', 'quoted'])
            ->count();

        $sla = app(DashboardAnalyticsService::class)->slaBreachCount();

        return [
            Stat::make(__('Spot gold & FX'), $goldLine)
                ->description($fxLine)
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('primary')
                ->icon(Heroicon::OutlinedSun),
            Stat::make(__('Active inquiries'), (string) $activeInquiries)
                ->description(__('Open, reviewing, or quoted — not closed'))
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('gray')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight),
            Stat::make(__('SLA risk orders'), (string) $sla)
                ->description(__('Stale deposit / pre-production / production thresholds'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($sla > 0 ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedClock),
        ];
    }
}
