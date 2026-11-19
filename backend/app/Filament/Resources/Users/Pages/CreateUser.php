<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\ArtisanProfile;
use App\Services\AdminAuditLogService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->record;
        if ($user->role === UserRole::Artisan) {
            ArtisanProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty' => $this->data['artisanProfile']['specialty'] ?? null,
                    'is_active' => (bool) ($this->data['artisanProfile']['is_active'] ?? true),
                ]
            );
        }

        app(AdminAuditLogService::class)->log(
            action: 'admin.user.created',
            actor: Auth::user(),
            target: $user,
            category: 'admin',
            context: [
                'role' => (string) $user->role->value,
                'email' => $user->email,
            ],
        );
    }
}
