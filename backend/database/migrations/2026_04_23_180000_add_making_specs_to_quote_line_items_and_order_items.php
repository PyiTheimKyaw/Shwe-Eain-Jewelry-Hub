<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_line_items', function (Blueprint $table): void {
            $table->string('metal_type')->nullable()->after('custom_title');
            $table->text('stone_details')->nullable()->after('metal_type');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('metal_type_snapshot')->nullable()->after('title_snapshot');
            $table->text('stone_details_snapshot')->nullable()->after('metal_type_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['metal_type_snapshot', 'stone_details_snapshot']);
        });

        Schema::table('quote_line_items', function (Blueprint $table): void {
            $table->dropColumn(['metal_type', 'stone_details']);
        });
    }
};
