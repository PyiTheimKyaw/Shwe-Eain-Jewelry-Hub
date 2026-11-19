<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\ReportExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function orderFinalReceiptPdf(Order $order)
    {
        /** @var Receipt|null $receipt */
        $receipt = $order->receipts()
            ->with('payment')
            ->get()
            ->first(function (Receipt $r): bool {
                $type = (string) ($r->meta_json['payment_type'] ?? '');

                return $type === Payment::TYPE_FINAL;
            });

        abort_if($receipt === null, 404, 'Final receipt not found for this order.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists((string) $receipt->pdf_storage_path), 404, 'Final receipt file is missing.');

        return response()->streamDownload(
            fn () => print($disk->get((string) $receipt->pdf_storage_path)),
            $receipt->receipt_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
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
