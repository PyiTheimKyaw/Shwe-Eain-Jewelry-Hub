<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InquiryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_create_inquiry_sets_firebase_thread_id(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Customer,
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/inquiries', [
            'message' => 'Looking for a custom pendant.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.message', 'Looking for a custom pendant.');
        $this->assertNotNull($response->json('data.firebase_thread_id'));
        $this->assertNotEmpty($response->json('data.firebase_thread_id'));
    }
}
