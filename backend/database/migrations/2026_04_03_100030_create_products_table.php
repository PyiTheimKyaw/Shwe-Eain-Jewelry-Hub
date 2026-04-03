<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight_grams', 12, 4);
            $table->decimal('labor_fee_mmk', 18, 4)->default(0);
            $table->decimal('gem_price_mmk', 18, 4)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_custom_order_template')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
