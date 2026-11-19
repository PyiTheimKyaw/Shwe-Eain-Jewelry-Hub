<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_assignments', function (Blueprint $table): void {
            $table->dateTime('target_deadline_at')->nullable()->after('instructions');
            $table->enum('priority_level', ['low', 'normal', 'high', 'urgent'])
                ->default('normal')
                ->after('target_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_assignments', function (Blueprint $table): void {
            $table->dropColumn(['target_deadline_at', 'priority_level']);
        });
    }
};
