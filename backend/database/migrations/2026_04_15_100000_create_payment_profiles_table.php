<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 32);
            $table->string('institution_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('wallet_id')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_profile_id')
                ->nullable()
                ->after('order_id')
                ->constrained('payment_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_profile_id']);
            $table->dropColumn('payment_profile_id');
        });

        Schema::dropIfExists('payment_profiles');
    }
};
