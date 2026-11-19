<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_configs', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key')->unique()->default('default');
            $table->decimal('default_deposit_percentage', 5, 2)->default(50.00);
            $table->unsignedInteger('payment_at_risk_hours')->default(4);
            $table->unsignedInteger('order_at_risk_hours')->default(24);
            $table->unsignedInteger('currency_refresh_interval_minutes')->default(15);
            $table->text('payment_accept_template')->nullable();
            $table->text('payment_reject_template')->nullable();
            $table->timestamps();
        });

        DB::table('business_configs')->insert([
            'singleton_key' => 'default',
            'default_deposit_percentage' => 50.00,
            'payment_at_risk_hours' => 4,
            'order_at_risk_hours' => 24,
            'currency_refresh_interval_minutes' => 15,
            'payment_accept_template' => '--- Deposit Confirmed ---',
            'payment_reject_template' => '--- Payment Rejected --- | Reason: {reason}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_configs');
    }
};
