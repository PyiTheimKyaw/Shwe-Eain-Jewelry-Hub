<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Payment;
use App\Services\ReceiptService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('receipts:generate-missing', function (ReceiptService $receipts) {
    $payments = Payment::query()
        ->where('status', Payment::STATUS_CONFIRMED)
        ->whereDoesntHave('receipt')
        ->get();

    $generated = 0;
    foreach ($payments as $payment) {
        $receipts->issueForConfirmedPayment($payment);
        $generated++;
    }

    $this->info("Generated {$generated} receipts.");
})->purpose('Generate missing receipts for confirmed payments');
