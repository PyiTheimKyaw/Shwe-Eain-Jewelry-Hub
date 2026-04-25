<?php

namespace App\Filament\Widgets;

use App\Services\DashboardAnalyticsService;
use Filament\Widgets\ChartWidget;

class OrderStageDoughnutWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 41;

    protected string $color = 'primary';

    protected ?string $heading = 'Order pipeline';

    protected ?string $description = 'Distribution by operational stage (non-cancelled pipeline)';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
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
        $dist = app(DashboardAnalyticsService::class)->orderStageDistribution();

        return [
            'labels' => $dist['labels'],
            'datasets' => [
                [
                    'data' => $dist['data'],
                    'backgroundColor' => $dist['colors'],
                    'borderColor' => '#0F172A',
                    'borderWidth' => 2,
                    'hoverOffset' => 6,
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
            'cutout' => '62%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'color' => '#E2E8F0',
                        'boxWidth' => 10,
                    ],
                ],
            ],
        ];
    }
}
