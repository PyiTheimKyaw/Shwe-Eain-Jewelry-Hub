<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'correct-password',
            'role' => UserRole::Customer,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'customer@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_non_admin_cannot_use_admin_chat_upload_route(): void
    {
        Storage::fake('public');

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $inquiry = Inquiry::query()->create([
            'customer_user_id' => $customer->id,
            'product_id' => null,
            'message' => 'Test inquiry',
            'status' => 'open',
            'firebase_thread_id' => 'thread-security-upload',
        ]);

        $this->actingAs($customer)->post(
            "/admin/inquiries/{$inquiry->id}/chat-upload",
            [
                'file' => UploadedFile::fake()->image('proof.jpg'),
                'folder' => 'inquiry-chat',
            ]
        )->assertStatus(403);
    }

    public function test_upload_endpoint_rejects_disallowed_mime_type(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/uploads', [
            'file' => UploadedFile::fake()->create('script.pdf', 10, 'application/pdf'),
            'folder' => 'misc',
        ])->assertStatus(422);
    }

    public function test_cannot_reject_confirmed_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $order = $this->makeAwaitingDepositOrder($customer);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => $order->deposit_amount_mmk,
            'currency' => 'MMK',
            'status' => Payment::STATUS_CONFIRMED,
            'verified_by_user_id' => $admin->id,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/payments/{$payment->id}/reject", [
            'reason' => 'Invalid proof',
        ])->assertStatus(422);
    }

    public function test_cannot_confirm_pending_proof_without_submission(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $order = $this->makeAwaitingDepositOrder($customer);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => $order->deposit_amount_mmk,
            'currency' => 'MMK',
            'status' => Payment::STATUS_PENDING_PROOF,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm")
            ->assertStatus(422);
    }

    public function test_cannot_upload_deposit_proof_when_existing_payment_is_confirmed(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $order = $this->makeAwaitingDepositOrder($customer);

        Payment::query()->create([
            'order_id' => $order->id,
            'type' => Payment::TYPE_DEPOSIT,
            'amount_mmk' => $order->deposit_amount_mmk,
            'currency' => 'MMK',
            'status' => Payment::STATUS_CONFIRMED,
            'verified_by_user_id' => $admin->id,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/orders/{$order->id}/deposit-proof", [
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertStatus(422);
    }

    private function makeAwaitingDepositOrder(User $customer): Order
    {
        return Order::query()->create([
            'customer_user_id' => $customer->id,
            'quote_id' => null,
            'order_number' => 'O-SEC-'.strtoupper(substr(md5((string) microtime(true)), 0, 10)),
            'status' => 'awaiting_deposit',
            'deposit_amount_mmk' => 100000,
            'deposit_instructions_sent_at' => now(),
            'total_mmk' => 200000,
            'total_usd_snapshot' => 100,
            'exchange_rate_snapshot_id' => null,
            'shipping_notes' => null,
        ]);
    }
}
