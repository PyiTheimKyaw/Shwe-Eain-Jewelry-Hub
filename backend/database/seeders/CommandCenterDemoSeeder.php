<?php

namespace Database\Seeders;

use App\Models\ExchangeRateSnapshot;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CommandCenterDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'customer@example.com')->first();
        $artisan = User::query()->where('email', 'artisan@jewelryhub.test')->first();
        $admin = User::query()->where('email', 'admin@jewelryhub.test')->first();
        $fxSnapshot = ExchangeRateSnapshot::query()->latest('id')->first();
        $productIds = Product::query()->limit(3)->pluck('id')->all();

        if (! $customer || ! $artisan || ! $admin) {
            return;
        }

        // Seed 6 months of inquiries to drive conversion chart.
        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $monthDate = now()->subMonths($monthOffset)->startOfMonth()->addDays(2);
            for ($i = 0; $i < 4; $i++) {
                $createdAt = $monthDate->copy()->addDays($i * 4);
                Inquiry::query()->updateOrCreate(
                    ['firebase_thread_id' => "demo-thread-{$monthDate->format('Ym')}-{$i}"],
                    [
                        'customer_user_id' => $customer->id,
                        'product_id' => $productIds[$i % max(1, count($productIds))] ?? null,
                        'message' => "Demo inquiry {$monthDate->format('M Y')} #{$i}",
                        'status' => $i % 3 === 0 ? 'open' : ($i % 3 === 1 ? 'reviewing' : 'quoted'),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]
                );
            }
        }

        $statusCycle = [
            'awaiting_deposit',
            'deposit_confirmed',
            'in_production',
            'pending_qc',
            'ready',
            'completed',
        ];

        // Seed 12 months of orders and revenue-bearing payments.
        for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
            $monthDate = now()->subMonths($monthOffset)->startOfMonth()->addDays(5);
            $status = $statusCycle[$monthOffset % count($statusCycle)];

            $order = Order::query()->updateOrCreate(
                ['order_number' => 'DEMO-CC-'.$monthDate->format('Ym')],
                [
                    'customer_user_id' => $customer->id,
                    'quote_id' => null,
                    'status' => $status,
                    'deposit_amount_mmk' => 250000,
                    'deposit_instructions_sent_at' => $monthDate->copy()->addDay(),
                    'total_mmk' => 1200000 + (($monthOffset + 1) * 45000),
                    'total_usd_snapshot' => 0,
                    'exchange_rate_snapshot_id' => $fxSnapshot?->id,
                    'shipping_notes' => 'Demo seeded order for command center analytics.',
                    'created_at' => $monthDate,
                    'updated_at' => $monthDate->copy()->addDays(3),
                ]
            );

            Payment::query()->updateOrCreate(
                ['order_id' => $order->id, 'type' => Payment::TYPE_DEPOSIT, 'status' => Payment::STATUS_CONFIRMED],
                [
                    'payment_profile_id' => null,
                    'amount_mmk' => 180000 + (($monthOffset + 1) * 12000),
                    'currency' => 'MMK',
                    'proof_storage_path' => null,
                    'proof_mime' => null,
                    'submitted_at' => $monthDate->copy()->addDays(1),
                    'verified_at' => $monthDate->copy()->addDays(2),
                    'verified_by_user_id' => $admin->id,
                    'rejection_reason' => null,
                    'notes_internal' => 'Seeded for dashboard revenue chart.',
                    'created_at' => $monthDate->copy()->addDays(1),
                    'updated_at' => $monthDate->copy()->addDays(2),
                ]
            );
        }

        // Ensure at least one pending verification for Operations Pulse.
        $recentOrder = Order::query()->latest('id')->first();
        if ($recentOrder) {
            Payment::query()->updateOrCreate(
                ['order_id' => $recentOrder->id, 'type' => Payment::TYPE_DEPOSIT, 'status' => Payment::STATUS_AWAITING_VERIFICATION],
                [
                    'payment_profile_id' => null,
                    'amount_mmk' => 210000,
                    'currency' => 'MMK',
                    'submitted_at' => now()->subDay(),
                    'verified_at' => null,
                    'verified_by_user_id' => null,
                    'notes_internal' => 'Seeded pending verification queue item.',
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ]
            );
        }

        // Ensure active artisan workload for Operations Pulse.
        $productionOrders = Order::query()
            ->whereIn('status', ['deposit_confirmed', 'in_production', 'pending_qc'])
            ->limit(3)
            ->get();

        foreach ($productionOrders as $index => $order) {
            $assignmentStatus = ['assigned', 'in_progress', 'blocked'][$index % 3];
            OrderAssignment::query()->updateOrCreate(
                ['order_id' => $order->id, 'artisan_user_id' => $artisan->id, 'task_scope' => 'full_order'],
                [
                    'order_item_id' => null,
                    'status' => $assignmentStatus,
                    'instructions' => 'Seeded workload assignment for command center pulse.',
                    'created_at' => Carbon::parse($order->created_at)->addDays(2),
                    'updated_at' => Carbon::parse($order->created_at)->addDays(4),
                ]
            );
        }
    }
}
