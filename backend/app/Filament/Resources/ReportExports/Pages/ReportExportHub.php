<?php

namespace App\Filament\Resources\ReportExports\Pages;

use App\Filament\Resources\ReportExports\ReportExportResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;

class ReportExportHub extends Page
{
    protected static string $resource = ReportExportResource::class;

    protected string $view = 'filament.resources.report-exports.report-export-hub';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Generate Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    Select::make('report_type')
                        ->label('Report type')
                        ->options([
                            'sales' => 'Sales',
                            'payments' => 'Payments',
                            'artisan_performance' => 'Artisan Performance',
                            'order_lifecycle' => 'Order Lifecycle',
                        ])
                        ->default('sales')
                        ->required(),
                    DatePicker::make('start_date')
                        ->required()
                        ->native(false)
                        ->default(now()->startOfMonth()->toDateString()),
                    DatePicker::make('end_date')
                        ->required()
                        ->native(false)
                        ->default(now()->endOfMonth()->toDateString()),
                    Select::make('format')
                        ->options([
                            'pdf' => 'PDF',
                            'xlsx' => 'Excel (.xlsx)',
                            'csv' => 'CSV',
                        ])
                        ->default('pdf')
                        ->required(),
                ])
                ->action(function (array $data) {
                    return redirect()->to(route('admin.exports.reports.dynamic', [
                        'report_type' => (string) $data['report_type'],
                        'format' => (string) $data['format'],
                        'start_date' => (string) $data['start_date'],
                        'end_date' => (string) $data['end_date'],
                    ]));
                }),
        ];
    }
}
