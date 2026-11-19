<?php

namespace App\Filament\Widgets;

use App\Services\DashboardAnalyticsService;
use Filament\Widgets\Widget;

class ConversionFunnelWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.conversion-funnel-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 41;

    public int $windowDays = 30;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $window = in_array($this->windowDays, [7, 30, 90], true) ? $this->windowDays : 30;
        $funnels = app(DashboardAnalyticsService::class)->conversionFunnels($window);

        return [
            'windowDays' => $window,
            'customerStages' => $funnels['customer'],
            'operationsStages' => $funnels['operations'],
        ];
    }
}
