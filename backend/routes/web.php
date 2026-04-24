<?php

use App\Http\Controllers\Admin\InquiryChatUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/admin/inquiries/{inquiry}/chat-upload', InquiryChatUploadController::class)
        ->name('admin.inquiries.chat-upload');
});
