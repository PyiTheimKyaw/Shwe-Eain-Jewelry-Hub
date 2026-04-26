<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('dimensions_snapshot')->nullable()->after('stone_details_snapshot');
            $table->string('ring_size_snapshot')->nullable()->after('dimensions_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['dimensions_snapshot', 'ring_size_snapshot']);
        });
    }
};
