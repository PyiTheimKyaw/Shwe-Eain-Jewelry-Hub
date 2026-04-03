<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_ready_made')->default(true)->after('is_published');
        });

        // Custom-order templates are not "ready-made" shelf stock.
        DB::table('products')->where('is_custom_order_template', true)->update(['is_ready_made' => false]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_ready_made');
        });
    }
};
