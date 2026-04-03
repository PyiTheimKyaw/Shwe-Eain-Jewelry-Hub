<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title_snapshot');
            $table->decimal('weight_grams', 12, 4);
            $table->decimal('metal_value_mmk', 18, 4);
            $table->decimal('labor_fee_mmk', 18, 4)->default(0);
            $table->decimal('gem_price_mmk', 18, 4)->nullable();
            $table->decimal('line_total_mmk', 18, 4);
            $table->decimal('line_total_usd_snapshot', 14, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
