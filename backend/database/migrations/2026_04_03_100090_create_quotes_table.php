<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gold_price_snapshot_id')->constrained('gold_price_snapshots')->restrictOnDelete();
            $table->foreignId('exchange_rate_snapshot_id')->constrained('exchange_rate_snapshots')->restrictOnDelete();
            $table->string('quote_number')->unique();
            $table->enum('status', ['draft', 'sent', 'accepted', 'expired', 'void'])->default('draft');
            $table->decimal('total_mmk', 18, 4);
            $table->decimal('total_usd_snapshot', 14, 4);
            $table->dateTime('valid_until')->nullable();
            $table->text('notes_internal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
