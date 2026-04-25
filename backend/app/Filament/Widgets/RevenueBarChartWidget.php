<?php

namespace App\Filament\Widgets;

use App\Services\DashboardAnalyticsService;
use Filament\Widgets\ChartWidget;

class RevenueBarChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 40;

    protected string $color = 'warning';

    protected ?string $heading = 'Monthly revenue (confirmed payments)';

    protected ?string $description = 'MMK and USD (from order FX snapshot at time of payment)';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getPollingInterval(): ?string
    {
        return '120s';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $series = app(DashboardAnalyticsService::class)->monthlyRevenueSeries(12);

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => 'Revenue (MMK)',
                    'data' => $series['mmk'],
                    'backgroundColor' => 'rgba(212, 175, 55, 0.55)',
                    'borderColor' => '#D4AF37',
                    'borderWidth' => 1,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Revenue (USD)',
                    'data' => $series['usd'],
                    'backgroundColor' => 'rgba(30, 58, 95, 0.55)',
                    'borderColor' => '#1E3A5F',
                    'borderWidth' => 1,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'color' => '#E2E8F0',
                        'boxWidth' => 12,
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => ['color' => '#94A3B8'],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.12)'],
                ],
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => ['display' => true, 'text' => 'MMK', 'color' => '#D4AF37'],
                    'ticks' => ['color' => '#CBD5E1'],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.15)'],
                    'beginAtZero' => true,
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => ['display' => true, 'text' => 'USD', 'color' => '#F5E6C8'],
                    'ticks' => ['color' => '#CBD5E1'],
                    'grid' => ['drawOnChartArea' => false],
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
