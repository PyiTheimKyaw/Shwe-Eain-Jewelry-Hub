<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ReportExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FilamentExportController extends Controller
{
    public function __construct(
        protected ReportExportService $reports
    ) {}

    public function bulkOrders(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'string'],
            'format' => ['required', 'in:csv,xlsx'],
        ]);

        $ids = collect(explode(',', (string) $data['ids']))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        $orders = Order::query()
            ->with('customer')
            ->whereIn('id', $ids)
            ->get();

        return $this->reports->exportOrders($orders, (string) $data['format']);
    }

    public function bulkPayments(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'string'],
            'format' => ['required', 'in:csv,xlsx'],
        ]);

        $ids = collect(explode(',', (string) $data['ids']))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        $payments = Payment::query()
            ->with(['order.customer', 'order.exchangeRateSnapshot'])
            ->whereIn('id', $ids)
            ->get();

        return $this->reports->exportPayments($payments, (string) $data['format']);
    }

    public function orderPdf(Order $order)
    {
        return $this->reports->downloadOrderPdf($order);
    }

    public function paymentPdf(Payment $payment)
    {
        return $this->reports->downloadPaymentPdf($payment);
    }

    public function dynamicReport(Request $request)
    {
        $data = $request->validate([
            'report_type' => ['required', 'in:sales,payments,artisan_performance,order_lifecycle'],
            'format' => ['required', 'in:pdf,xlsx,csv'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return $this->reports->downloadDynamicReport(
            reportType: (string) $data['report_type'],
            start: Carbon::parse((string) $data['start_date'])->startOfDay(),
            end: Carbon::parse((string) $data['end_date'])->endOfDay(),
            format: (string) $data['format'],
        );
    }
}
