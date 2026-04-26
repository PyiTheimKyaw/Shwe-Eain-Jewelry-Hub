<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderItem;
use App\Models\OrderProgressEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArtisanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_stats_returns_active_completed_and_blocked_counts(): void
    {
        $artisan = User::factory()->create(['role' => UserRole::Artisan]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $orderA = $this->makeOrder($customer, 'O-ART-A');
        $orderB = $this->makeOrder($customer, 'O-ART-B');
        $orderC = $this->makeOrder($customer, 'O-ART-C');
        $orderD = $this->makeOrder($customer, 'O-ART-D');

        OrderAssignment::query()->create([
            'order_id' => $orderA->id,
            'artisan_user_id' => $artisan->id,
            'task_scope' => 'full_order',
            'status' => 'assigned',
            'priority_level' => 'normal',
        ]);
        OrderAssignment::query()->create([
            'order_id' => $orderB->id,
            'artisan_user_id' => $artisan->id,
            'task_scope' => 'full_order',
            'status' => 'in_progress',
            'priority_level' => 'high',
        ]);
        OrderAssignment::query()->create([
            'order_id' => $orderC->id,
            'artisan_user_id' => $artisan->id,
            'task_scope' => 'full_order',
            'status' => 'blocked',
            'priority_level' => 'urgent',
        ]);
        OrderAssignment::query()->create([
            'order_id' => $orderD->id,
            'artisan_user_id' => $artisan->id,
            'task_scope' => 'full_order',
            'status' => 'done',
            'priority_level' => 'low',
        ]);

        Sanctum::actingAs($artisan);

        $this->getJson('/api/v1/artisan/stats')
            ->assertOk()
            ->assertJsonPath('data.current_active_load', 2)
            ->assertJsonPath('data.blocked_count', 1)
            ->assertJsonPath('data.total_completed_jobs', 1);
    }

    public function test_assignment_show_includes_deadline_priority_and_latest_feedback(): void
    {
        $artisan = User::factory()->create(['role' => UserRole::Artisan]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $order = $this->makeOrder($customer, 'O-ART-SHOW');

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'title_snapshot' => 'Custom ring',
            'metal_type_snapshot' => '18K Gold',
            'stone_details_snapshot' => 'Ruby center',
            'dimensions_snapshot' => 'Band width 2.4mm',
            'ring_size_snapshot' => 'US 7',
            'weight_grams' => 8.2,
            'metal_value_mmk' => 100000,
            'labor_fee_mmk' => 35000,
            'gem_price_mmk' => 20000,
            'line_total_mmk' => 155000,
            'line_total_usd_snapshot' => 74,
        ]);

        $assignment = OrderAssignment::query()->create([
            'order_id' => $order->id,
            'artisan_user_id' => $artisan->id,
            'task_scope' => 'full_order',
            'status' => 'assigned',
            'instructions' => 'Follow casting thickness guide.',
            'target_deadline_at' => '2026-05-02 10:00:00',
            'priority_level' => 'high',
        ]);

        OrderProgressEvent::query()->create([
            'order_id' => $order->id,
            'artisan_user_id' => $artisan->id,
            'stage' => 'qc',
            'note' => 'Older note',
        ]);
        OrderProgressEvent::query()->create([
            'order_id' => $order->id,
            'artisan_user_id' => $artisan->id,
            'stage' => 'qc',
            'note' => 'QC rejected: tighten prong alignment on left claw.',
        ]);

        Sanctum::actingAs($artisan);

        $this->getJson("/api/v1/artisan/assignments/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('data.priority_level', 'high')
            ->assertJsonPath('data.target_deadline_at', '2026-05-02T10:00:00.000000Z')
            ->assertJsonPath(
                'data.latest_admin_feedback',
                'QC rejected: tighten prong alignment on left claw.'
            );
    }

    private function makeOrder(User $customer, string $number): Order
    {
        return Order::query()->create([
            'customer_user_id' => $customer->id,
            'quote_id' => null,
            'order_number' => $number,
            'status' => 'deposit_confirmed',
            'deposit_amount_mmk' => 100000,
            'deposit_instructions_sent_at' => now(),
            'total_mmk' => 200000,
            'total_usd_snapshot' => 95,
            'exchange_rate_snapshot_id' => null,
            'shipping_notes' => null,
        ]);
    }
}
