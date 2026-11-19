<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_timeline_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->string('message', 255);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_timeline_logs');
    }
};
