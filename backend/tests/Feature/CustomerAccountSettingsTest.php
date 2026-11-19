<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_password(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Customer,
            'password' => 'OldPassword1!',
        ]);

        $token = $user->createToken('t')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'OldPassword1!',
                'password' => 'NewPassword2!',
                'password_confirmation' => 'NewPassword2!',
            ]);

        $res->assertOk()->assertJsonPath('message', 'Password updated.');

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword2!', $user->password));
    }

    public function test_customer_can_close_account(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Customer,
            'password' => 'Secret123!',
            'email' => 'customer@example.com',
        ]);

        $token = $user->createToken('t')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth/account', [
                'current_password' => 'Secret123!',
            ]);

        $res->assertOk()->assertJsonPath('message', 'Your account has been closed.');

        $user->refresh();
        $this->assertSame('closed', $user->account_status);
        $this->assertNotSame('customer@example.com', $user->email);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'Secret123!',
        ]);
        $login->assertStatus(422);
    }
}
