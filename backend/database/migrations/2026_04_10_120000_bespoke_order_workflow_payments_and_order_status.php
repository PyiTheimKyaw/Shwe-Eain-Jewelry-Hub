<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 32)->default('deposit');
            $table->decimal('amount_mmk', 18, 4);
            $table->string('currency', 8)->default('MMK');
            $table->string('status', 32)->default('pending_proof');
            $table->string('proof_storage_path')->nullable();
            $table->string('proof_mime')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('notes_internal')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('deposit_amount_mmk', 18, 4)->nullable()->after('status');
            $table->timestamp('deposit_instructions_sent_at')->nullable()->after('deposit_amount_mmk');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT "awaiting_deposit"');
            $this->mapOrderStatuses();
        } elseif ($driver === 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status_v2', 32)->default('awaiting_deposit');
            });
            foreach (DB::table('orders')->get() as $row) {
                $map = [
                    'pending' => 'awaiting_deposit',
                    'confirmed' => 'deposit_confirmed',
                    'qa' => 'pending_qc',
                ];
                $s = $map[$row->status] ?? $row->status;
                DB::table('orders')->where('id', $row->id)->update(['status_v2' => $s]);
            }
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('status_v2', 'status');
            });
        } else {
            $this->mapOrderStatuses();
        }
    }

    protected function mapOrderStatuses(): void
    {
        $statusMap = [
            'pending' => 'awaiting_deposit',
            'confirmed' => 'deposit_confirmed',
            'qa' => 'pending_qc',
        ];

        foreach ($statusMap as $from => $to) {
            DB::table('orders')->where('status', $from)->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status_v2', 32)->default('pending');
            });
            foreach (DB::table('orders')->get() as $row) {
                $map = [
                    'awaiting_deposit' => 'pending',
                    'deposit_confirmed' => 'confirmed',
                    'pending_qc' => 'qa',
                ];
                $s = $map[$row->status] ?? $row->status;
                DB::table('orders')->where('id', $row->id)->update(['status_v2' => $s]);
            }
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('status_v2', 'status');
            });
        } else {
            $statusMap = [
                'awaiting_deposit' => 'pending',
                'deposit_confirmed' => 'confirmed',
                'pending_qc' => 'qa',
            ];
            foreach ($statusMap as $from => $to) {
                DB::table('orders')->where('status', $from)->update(['status' => $to]);
            }
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','in_production','qa','ready','shipped','completed','cancelled') NOT NULL DEFAULT 'pending'");
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount_mmk', 'deposit_instructions_sent_at']);
        });

        Schema::dropIfExists('payments');
    }
};
