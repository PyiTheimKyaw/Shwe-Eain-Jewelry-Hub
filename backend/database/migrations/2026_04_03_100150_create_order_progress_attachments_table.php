<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_progress_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_event_id')->constrained('order_progress_events')->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_progress_attachments');
    }
};
