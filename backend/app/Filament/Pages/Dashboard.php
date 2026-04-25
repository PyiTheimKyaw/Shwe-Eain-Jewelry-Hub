<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CommandCenterKpisWidget;
use App\Filament\Widgets\CommandCenterStripWidget;
use App\Filament\Widgets\InquiryConversionLineWidget;
use App\Filament\Widgets\OperationsPulseWidget;
use App\Filament\Widgets\OrderStageDoughnutWidget;
use App\Filament\Widgets\QuickExportWidget;
use App\Filament\Widgets\RevenueBarChartWidget;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\Widget;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Command center';

    /** @return array<int, class-string<Widget>> */
    public function getWidgets(): array
    {
        return [
            CommandCenterKpisWidget::class,
            OperationsPulseWidget::class,
            CommandCenterStripWidget::class,
            QuickExportWidget::class,
            RevenueBarChartWidget::class,
            OrderStageDoughnutWidget::class,
            InquiryConversionLineWidget::class,
            AccountWidget::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadCurrentMonthSalesSummary')
                ->label('Download Current Month Sales Summary (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->url(route('admin.reports.sales-summary.current-month'))
                ->openUrlInNewTab(),
        ];
    }
}
