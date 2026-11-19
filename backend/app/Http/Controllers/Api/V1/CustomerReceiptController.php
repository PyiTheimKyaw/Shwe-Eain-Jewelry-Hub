<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerReceiptController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $receipts = $order->receipts()
            ->where('customer_user_id', $request->user()->id)
            ->orderByDesc('issued_at')
            ->get();

        return response()->json([
            'data' => $receipts->map(fn (Receipt $receipt): array => $this->serializeReceipt($receipt))->values()->all(),
        ]);
    }

    public function show(Request $request, Order $order, Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $order);
        $this->assertBelongsToOrder($request, $order, $receipt);

        return response()->json(['data' => $this->serializeReceipt($receipt)]);
    }

    public function download(Request $request, Order $order, Receipt $receipt): StreamedResponse
    {
        $this->authorize('view', $order);
        $this->assertBelongsToOrder($request, $order, $receipt);

        $filename = $receipt->receipt_number.'.pdf';

        $disk = Storage::disk('local');

        return response()->streamDownload(
            fn () => print($disk->get($receipt->pdf_storage_path)),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    private function assertBelongsToOrder(Request $request, Order $order, Receipt $receipt): void
    {
        abort_unless($receipt->order_id === $order->id, 404);
        abort_unless($receipt->customer_user_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReceipt(Receipt $receipt): array
    {
        $payload = $receipt->toArray();
        $payload['payment_type'] = (string) ($receipt->meta_json['payment_type'] ?? 'deposit');

        return $payload;
    }
}

