<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderItem;
use App\Models\OrderProgressEvent;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_chat_upload_candidate_can_be_attached_and_seen_by_artisan(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $artisan = User::factory()->create(['role' => UserRole::Artisan]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $inquiry = Inquiry::query()->create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Need custom ring',
            'status' => 'open',
            'firebase_thread_id' => 'thread-refs',
        ]);
        $quote = $this->makeQuote($customer, $inquiry);
        $order = $this->makeOrder($customer, 'O-ART-REF', $quote->id);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'title_snapshot' => 'Reference ring',
            'metal_type_snapshot' => '18K Gold',
            'stone_details_snapshot' => 'Ruby',
            'dimensions_snapshot' => 'Band 2.2mm',
            'ring_size_snapshot' => 'US 6',
            'weight_grams' => 7.5,
            'metal_value_mmk' => 120000,
            'labor_fee_mmk' => 20000,
            'gem_price_mmk' => 30000,
            'line_total_mmk' => 170000,
            'line_total_usd_snapshot' => 80,
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'payment_profile_id' => null,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => 100000,
            'currency' => 'MMK',
            'status' => Payment::STATUS_CONFIRMED,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($customer);
        $upload = $this->postJson('/api/v1/uploads', [
            'file' => UploadedFile::fake()->image('ref.png'),
            'folder' => 'inquiry-chat',
            'inquiry_id' => $inquiry->id,
        ])->assertCreated();

        $candidateId = \App\Models\InquiryImageCandidate::query()->value('id');
        $this->assertNotNull($candidateId);

        Sanctum::actingAs($admin);
        $assignmentRes = $this->postJson("/api/v1/admin/orders/{$order->id}/assignments", [
            'artisan_user_id' => $artisan->id,
            'task_scope' => 'full_order',
            'priority_level' => 'high',
            'reference_image_ids' => [$candidateId],
        ])->assertCreated();
        $assignmentId = (int) $assignmentRes->json('data.id');

        Sanctum::actingAs($artisan);
        $this->getJson("/api/v1/artisan/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.reference_images')
            ->assertJsonPath('data.reference_images.0.inquiry_image_candidate_id', (int) $candidateId);
    }

    private function makeOrder(User $customer, string $number, ?int $quoteId = null): Order
    {
        return Order::query()->create([
            'customer_user_id' => $customer->id,
            'quote_id' => $quoteId,
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

    private function makeQuote(User $customer, Inquiry $inquiry): Quote
    {
        $gold = GoldPriceSnapshot::query()->create([
            'price_mmk_per_gram' => 160000,
            'effective_from' => now(),
            'effective_to' => null,
        ]);
        $fx = ExchangeRateSnapshot::query()->create([
            'mmk_per_usd' => 2100,
            'effective_from' => now(),
            'effective_to' => null,
        ]);

        return Quote::query()->create([
            'inquiry_id' => $inquiry->id,
            'customer_user_id' => $customer->id,
            'gold_price_snapshot_id' => $gold->id,
            'exchange_rate_snapshot_id' => $fx->id,
            'quote_number' => 'Q-ART-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'sent',
            'total_mmk' => 200000,
            'total_usd_snapshot' => 95,
            'valid_until' => now()->addDays(7),
        ]);
    }
}
