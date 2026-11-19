<?php

namespace App\Services;

use App\Exports\ArrayReportExport;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportService
{
    /**
     * @param  EloquentCollection<int, Order>  $orders
     */
    public function exportOrders(EloquentCollection $orders, string $format): Response|BinaryFileResponse
    {
        $headings = ['Order ID', 'Customer Name', 'Total Amount (MMK)', 'Total Amount (USD)', 'Status'];
        $rows = $orders->map(function (Order $order): array {
            return [
                $order->id,
                (string) ($order->customer?->name ?? '-'),
                (string) $order->total_mmk,
                (string) ($order->total_usd_snapshot ?? 0),
                (string) $order->status,
            ];
        })->values()->all();

        return $this->downloadTabular(
            title: 'orders_export',
            headings: $headings,
            rows: $rows,
            format: $format,
        );
    }

    /**
     * @param  EloquentCollection<int, Payment>  $payments
     */
    public function exportPayments(EloquentCollection $payments, string $format): Response|BinaryFileResponse
    {
        $headings = ['Payment ID', 'Order ID', 'Customer Name', 'Total Amount (MMK)', 'Total Amount (USD)', 'Status'];
        $rows = $payments->map(function (Payment $payment): array {
            $order = $payment->order;
            $usd = $this->paymentUsdAmount($payment);

            return [
                $payment->id,
                $order?->id,
                (string) ($order?->customer?->name ?? '-'),
                (string) $payment->amount_mmk,
                (string) $usd,
                (string) $payment->status,
            ];
        })->values()->all();

        return $this->downloadTabular(
            title: 'payments_export',
            headings: $headings,
            rows: $rows,
            format: $format,
        );
    }

    public function downloadOrderPdf(Order $order): Response
    {
        $order->loadMissing(['customer', 'items.product', 'payments']);

        $pdf = Pdf::loadView('reports.order-detail', [
            'branding' => 'Shwe Eain Jewelry Hub',
            'order' => $order,
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="order-report-'.$order->order_number.'.pdf"',
        ]);
    }

    public function downloadPaymentPdf(Payment $payment): Response
    {
        $payment->loadMissing(['order.customer', 'paymentProfile', 'verifiedBy']);

        $pdf = Pdf::loadView('reports.payment-detail', [
            'branding' => 'Shwe Eain Jewelry Hub',
            'payment' => $payment,
            'amount_usd' => $this->paymentUsdAmount($payment),
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="payment-report-'.$payment->id.'.pdf"',
        ]);
    }

    public function downloadSalesSummaryPdf(\DateTimeInterface $start, \DateTimeInterface $end, string $filename): Response
    {
        $rows = Payment::query()
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereBetween('verified_at', [$start, $end])
            ->orderBy('verified_at')
            ->get();

        $totalMmk = (float) $rows->sum('amount_mmk');
        $totalUsd = (float) $rows->sum(fn (Payment $payment): float => (float) $this->paymentUsdAmount($payment));

        $pdf = Pdf::loadView('reports.sales-summary', [
            'branding' => 'Shwe Eain Jewelry Hub',
            'start' => $start,
            'end' => $end,
            'rows' => $rows,
            'total_mmk' => $totalMmk,
            'total_usd' => $totalUsd,
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadDynamicReport(
        string $reportType,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $format
    ): Response|BinaryFileResponse {
        $dataset = match ($reportType) {
            'sales' => $this->salesDataset($start, $end),
            'payments' => $this->paymentsDataset($start, $end),
            'artisan_performance' => $this->artisanPerformanceDataset($start, $end),
            'order_lifecycle' => $this->orderLifecycleDataset($start, $end),
            default => $this->salesDataset($start, $end),
        };

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.dynamic-report', [
                'branding' => 'Shwe Eain Jewelry Hub',
                'title' => $dataset['title'],
                'headings' => $dataset['headings'],
                'rows' => $dataset['rows'],
                'start' => $start,
                'end' => $end,
            ]);

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$dataset['filename'].'.pdf"',
            ]);
        }

        return $this->downloadTabular(
            title: $dataset['filename'],
            headings: $dataset['headings'],
            rows: $dataset['rows'],
            format: $format,
        );
    }

    /**
     * @param  list<string>  $headings
     * @param  list<array<int, mixed>>  $rows
     */
    private function downloadTabular(string $title, array $headings, array $rows, string $format): Response|BinaryFileResponse
    {
        if ($format === 'xlsx') {
            return Excel::download(new ArrayReportExport($headings, $rows), $title.'.xlsx');
        }

        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headings);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$title.'.csv"',
        ]);
    }

    private function paymentUsdAmount(Payment $payment): string
    {
        $order = $payment->order;
        $rate = (float) ($order?->exchangeRateSnapshot?->mmk_per_usd ?? 0);
        if ($rate <= 0.0) {
            return '0';
        }

        return number_format(((float) $payment->amount_mmk) / $rate, 4, '.', '');
    }

    /**
     * @return array{title: string, filename: string, headings: list<string>, rows: list<array<int, mixed>>}
     */
    private function salesDataset(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $payments = Payment::query()
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereBetween('verified_at', [$start, $end])
            ->with('order')
            ->orderBy('verified_at')
            ->get();

        $rows = $payments->map(function (Payment $payment): array {
            return [
                optional($payment->verified_at)->format('Y-m-d') ?? '-',
                $payment->id,
                $payment->order_id,
                (string) $payment->amount_mmk,
                $this->paymentUsdAmount($payment),
                (string) $payment->status,
            ];
        })->values()->all();

        return [
            'title' => 'Sales Report',
            'filename' => 'sales_report',
            'headings' => ['Date', 'Payment ID', 'Order ID', 'Amount MMK', 'Amount USD', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, filename: string, headings: list<string>, rows: list<array<int, mixed>>}
     */
    private function paymentsDataset(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $payments = Payment::query()
            ->with('order.customer')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('id')
            ->get();

        $rows = $payments->map(function (Payment $payment): array {
            return [
                $payment->id,
                $payment->order_id,
                (string) ($payment->order?->customer?->name ?? '-'),
                (string) $payment->amount_mmk,
                $this->paymentUsdAmount($payment),
                (string) $payment->status,
            ];
        })->values()->all();

        return [
            'title' => 'Payments Report',
            'filename' => 'payments_report',
            'headings' => ['Payment ID', 'Order ID', 'Customer', 'Amount MMK', 'Amount USD', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, filename: string, headings: list<string>, rows: list<array<int, mixed>>}
     */
    private function artisanPerformanceDataset(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $assignments = OrderAssignment::query()
            ->with('artisan')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy('artisan_user_id');

        $rows = [];
        foreach ($assignments as $artisanUserId => $group) {
            $artisanName = (string) ($group->first()?->artisan?->name ?? 'Unknown');
            $rows[] = [
                $artisanUserId,
                $artisanName,
                $group->count(),
                $group->where('status', 'done')->count(),
                $group->where('status', 'in_progress')->count(),
            ];
        }

        return [
            'title' => 'Artisan Performance Report',
            'filename' => 'artisan_performance_report',
            'headings' => ['Artisan ID', 'Artisan', 'Assignments', 'Completed', 'In Progress'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, filename: string, headings: list<string>, rows: list<array<int, mixed>>}
     */
    private function orderLifecycleDataset(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $orders = Order::query()
            ->with('customer')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get();

        $rows = $orders->map(function (Order $order): array {
            return [
                $order->id,
                (string) $order->order_number,
                (string) ($order->customer?->name ?? '-'),
                (string) $order->status,
                optional($order->created_at)->format('Y-m-d H:i') ?? '-',
                optional($order->updated_at)->format('Y-m-d H:i') ?? '-',
            ];
        })->values()->all();

        return [
            'title' => 'Order Lifecycle Report',
            'filename' => 'order_lifecycle_report',
            'headings' => ['Order ID', 'Order Number', 'Customer', 'Status', 'Created At', 'Updated At'],
            'rows' => $rows,
        ];
    }
}
