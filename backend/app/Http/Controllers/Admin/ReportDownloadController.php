<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Illuminate\Http\Response;

class ReportDownloadController extends Controller
{
    public function __construct(
        protected ReportExportService $reports
    ) {}

    public function currentMonthSalesSummary(): Response
    {
        return $this->reports->downloadSalesSummaryPdf(
            start: now()->startOfMonth(),
            end: now()->endOfMonth(),
            filename: 'current-month-sales-summary.pdf',
        );
    }

    public function todaySalesSummary(): Response
    {
        return $this->reports->downloadSalesSummaryPdf(
            start: now()->startOfDay(),
            end: now()->endOfDay(),
            filename: 'today-sales-summary.pdf',
        );
    }
}
