<?php

use App\Http\Controllers\Admin\FilamentExportController;
use App\Http\Controllers\Admin\InquiryChatUploadController;
use App\Http\Controllers\Admin\ReportDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth', 'role:admin'])->group(function () {
    Route::post('/admin/inquiries/{inquiry}/chat-upload', InquiryChatUploadController::class)
        ->name('admin.inquiries.chat-upload');
    Route::get('/admin/reports/sales-summary/current-month', [ReportDownloadController::class, 'currentMonthSalesSummary'])
        ->name('admin.reports.sales-summary.current-month');
    Route::get('/admin/reports/sales-summary/today', [ReportDownloadController::class, 'todaySalesSummary'])
        ->name('admin.reports.sales-summary.today');
    Route::get('/admin/exports/orders/bulk', [FilamentExportController::class, 'bulkOrders'])
        ->name('admin.exports.orders.bulk');
    Route::get('/admin/exports/payments/bulk', [FilamentExportController::class, 'bulkPayments'])
        ->name('admin.exports.payments.bulk');
    Route::get('/admin/exports/orders/{order}/pdf', [FilamentExportController::class, 'orderPdf'])
        ->name('admin.exports.orders.pdf');
    Route::get('/admin/exports/orders/{order}/final-receipt', [FilamentExportController::class, 'orderFinalReceiptPdf'])
        ->name('admin.exports.orders.final-receipt');
    Route::get('/admin/exports/payments/{payment}/pdf', [FilamentExportController::class, 'paymentPdf'])
        ->name('admin.exports.payments.pdf');
    Route::get('/admin/exports/reports/dynamic', [FilamentExportController::class, 'dynamicReport'])
        ->name('admin.exports.reports.dynamic');
});
