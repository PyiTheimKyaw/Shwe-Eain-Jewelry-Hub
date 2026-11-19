<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(['email' => 'admin@jewelryhub.test'], [
            'name' => 'Admin',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        User::query()->updateOrCreate(['email' => 'customer@example.com'], [
            'name' => 'Test Customer',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Customer,
        ]);

        User::query()->updateOrCreate(['email' => 'artisan@jewelryhub.test'], [
            'name' => 'Test Artisan',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Artisan,
        ]);

        $this->call(PricingSnapshotSeeder::class);
        $this->call(CatalogSeeder::class);
        $this->call(CommandCenterDemoSeeder::class);
    }
}
