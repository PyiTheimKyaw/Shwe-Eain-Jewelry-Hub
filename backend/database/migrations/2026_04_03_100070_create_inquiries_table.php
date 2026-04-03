<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->enum('status', ['open', 'reviewing', 'quoted', 'closed'])->default('open');
            $table->string('firebase_thread_id')->nullable();
            $table->text('notes_internal')->nullable();
            $table->timestamps();

            $table->index(['customer_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
