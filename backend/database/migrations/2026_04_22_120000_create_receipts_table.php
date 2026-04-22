<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency_code', 8)->default('MMK');
            $table->decimal('amount_mmk', 14, 4);
            $table->decimal('amount_usd_snapshot', 14, 4)->nullable();
            $table->string('branding_name')->default('Shwe Eain Jewelry Hub');
            $table->string('pdf_storage_path');
            $table->string('sha256', 64);
            $table->json('meta_json')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};

