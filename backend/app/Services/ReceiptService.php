<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    public function __construct(
        protected OrderSettlementService $settlement
    ) {}

    /**
     * @throws ValidationException
     */
    public function issueForConfirmedPayment(Payment $payment): Receipt
    {
        $payment->loadMissing(['order.customer', 'order.quote.exchangeRateSnapshot', 'paymentProfile', 'receipt']);

        if ($payment->receipt !== null) {
            return $payment->receipt;
        }

        if ($payment->status !== Payment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'payment' => 'Receipt can only be issued for confirmed payments.',
            ]);
        }

        return DB::transaction(function () use ($payment): Receipt {
            $existing = Receipt::query()->where('payment_id', $payment->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $order = $payment->order;
            $receiptNumber = $this->nextReceiptNumber();
            $issuedAt = now();
            $amountMmk = (float) $payment->amount_mmk;
            $mmkPerUsd = (float) ($order->exchangeRateSnapshot?->mmk_per_usd ?? 0);
            $amountUsd = $mmkPerUsd > 0 ? round($amountMmk / $mmkPerUsd, 4) : null;
            $depositPaid = $this->settlement->confirmedDepositsMmk($order);
            $totalPaid = $this->settlement->confirmedPaymentsMmk($order);
            $finalPaid = max(0.0, round($totalPaid - $depositPaid, 4));
            $lineItems = $order->items()->with('product')->get()->map(function ($line): array {
                return [
                    'title' => (string) ($line->title_snapshot ?: ($line->product?->title ?? 'Item')),
                    'weight_grams' => (float) $line->weight_grams,
                    'line_total_mmk' => (float) $line->line_total_mmk,
                ];
            })->all();
            $paymentMethodLabel = $payment->paymentProfile?->name ?: 'Cash';

            $payload = [
                'receipt_number' => $receiptNumber,
                'order_number' => (string) $order->order_number,
                'customer_name' => (string) ($order->customer?->name ?? 'Customer'),
                'customer_email' => (string) ($order->customer?->email ?? '-'),
                'payment_type' => (string) $payment->type,
                'currency_code' => (string) ($payment->currency ?: 'MMK'),
                'amount_mmk' => $amountMmk,
                'amount_usd_snapshot' => $amountUsd,
                'issued_at' => $issuedAt,
                'branding_name' => 'Shwe Eain Jewelry Hub',
                'order_total_mmk' => (float) $order->total_mmk,
                'deposit_paid_mmk' => $depositPaid,
                'final_paid_mmk' => $finalPaid,
                'line_items' => $lineItems,
                'payment_method_label' => $paymentMethodLabel,
            ];

            $view = $payment->isFinal() ? 'receipts.final-receipt' : 'receipts.payment-receipt';
            $pdfBinary = Pdf::loadView($view, $payload)->output();
            $sha = hash('sha256', $pdfBinary);
            $path = 'receipts/'.date('Y/m')."/{$receiptNumber}.pdf";
            Storage::disk('local')->put($path, $pdfBinary);

            return Receipt::create([
                'receipt_number' => $receiptNumber,
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'customer_user_id' => $order->customer_user_id,
                'currency_code' => (string) ($payment->currency ?: 'MMK'),
                'amount_mmk' => $amountMmk,
                'amount_usd_snapshot' => $amountUsd,
                'branding_name' => 'Shwe Eain Jewelry Hub',
                'pdf_storage_path' => $path,
                'sha256' => $sha,
                'meta_json' => [
                    'payment_type' => $payment->type,
                    'order_total_mmk' => (float) $order->total_mmk,
                    'deposit_amount_mmk' => (float) ($order->deposit_amount_mmk ?? 0),
                    'deposit_paid_mmk' => $depositPaid,
                    'final_paid_mmk' => $finalPaid,
                    'is_fully_paid' => $this->settlement->remainingBalanceMmk($order) <= 0,
                    'payment_method_label' => $paymentMethodLabel,
                    'verified_at' => optional($payment->verified_at)?->toIso8601String(),
                ],
                'issued_at' => $issuedAt,
            ]);
        });
    }

    private function nextReceiptNumber(): string
    {
        $prefix = 'RCP-'.now()->format('Ymd').'-';
        $countToday = Receipt::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return $prefix.str_pad((string) $countToday, 4, '0', STR_PAD_LEFT);
    }
}

