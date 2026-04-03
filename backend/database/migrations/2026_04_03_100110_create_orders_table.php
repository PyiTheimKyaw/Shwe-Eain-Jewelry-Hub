<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->enum('status', [
                'pending',
                'confirmed',
                'in_production',
                'qa',
                'ready',
                'shipped',
                'completed',
                'cancelled',
            ])->default('pending');
            $table->decimal('total_mmk', 18, 4);
            $table->decimal('total_usd_snapshot', 14, 4);
            $table->foreignId('exchange_rate_snapshot_id')->nullable()->constrained('exchange_rate_snapshots')->nullOnDelete();
            $table->text('shipping_notes')->nullable();
            $table->timestamps();

            $table->index(['customer_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
