<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('artisan_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('task_scope', ['full_order', 'line_item'])->default('full_order');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->cascadeOnDelete();
            $table->enum('status', ['assigned', 'in_progress', 'blocked', 'done'])->default('assigned');
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->index(['artisan_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_assignments');
    }
};
