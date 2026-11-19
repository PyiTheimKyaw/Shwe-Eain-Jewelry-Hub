<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_assignment_reference_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_assignment_id')->constrained('order_assignments')->cascadeOnDelete();
            $table->foreignId('inquiry_image_candidate_id')
                ->nullable()
                ->constrained('inquiry_image_candidates')
                ->nullOnDelete();
            $table->string('storage_path')->nullable();
            $table->string('url')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['order_assignment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_assignment_reference_images');
    }
};
