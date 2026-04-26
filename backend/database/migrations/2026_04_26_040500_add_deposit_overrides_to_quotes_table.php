<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('deposit_percentage_override', 5, 2)->nullable()->after('notes_internal');
            $table->decimal('deposit_amount_override_mmk', 18, 4)->nullable()->after('deposit_percentage_override');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['deposit_percentage_override', 'deposit_amount_override_mmk']);
        });
    }
};
