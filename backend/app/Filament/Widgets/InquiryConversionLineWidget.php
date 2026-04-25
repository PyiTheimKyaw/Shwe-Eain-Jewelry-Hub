<?php

namespace App\Filament\Widgets;

use App\Services\DashboardAnalyticsService;
use Filament\Widgets\ChartWidget;

class InquiryConversionLineWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 42;

    protected string $color = 'warning';

    protected ?string $heading = 'Inquiry → order signal';

    protected ?string $description = 'Monthly ratio: new orders ÷ new inquiries (%) — last 6 months';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'line';
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
        $series = app(DashboardAnalyticsService::class)->inquiryOrderConversionSeries(6);

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => 'Orders / inquiries (%)',
                    'data' => $series['rates'],
                    'fill' => true,
                    'tension' => 0.35,
                    'backgroundColor' => 'rgba(212, 175, 55, 0.12)',
                    'borderColor' => '#D4AF37',
                    'pointBackgroundColor' => '#F5E6C8',
                    'pointBorderColor' => '#D4AF37',
                    'pointRadius' => 4,
                    'borderWidth' => 2,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        $rates = app(DashboardAnalyticsService::class)->inquiryOrderConversionSeries(6)['rates'];
        $peak = max(array_merge([25.0], $rates));

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['color' => '#E2E8F0'],
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => ['color' => '#94A3B8'],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.12)'],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'suggestedMax' => min(500.0, ceil($peak / 5) * 5 + 5),
                    'ticks' => ['color' => '#CBD5E1'],
                    'title' => ['display' => true, 'text' => '%', 'color' => '#D4AF37'],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.15)'],
                ],
            ],
        ];
    }
}
