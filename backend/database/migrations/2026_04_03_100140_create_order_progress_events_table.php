<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_progress_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('artisan_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('stage', ['casting', 'filing', 'polishing', 'setting', 'qc', 'other'])->default('other');
            $table->text('note')->nullable();
            $table->string('firebase_event_id')->nullable();
            $table->timestamps();

            $table->index('firebase_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_progress_events');
    }
};
