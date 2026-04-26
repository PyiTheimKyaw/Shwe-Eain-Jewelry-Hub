<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_image_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->string('source', 40)->default('chat_upload');
            $table->string('sender_role', 40)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('url')->nullable();
            $table->string('firebase_message_id', 255)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'uploaded_at']);
            $table->unique(['inquiry_id', 'firebase_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_image_candidates');
    }
};
