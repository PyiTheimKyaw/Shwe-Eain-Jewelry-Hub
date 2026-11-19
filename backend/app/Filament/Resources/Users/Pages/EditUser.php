<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\ArtisanProfile;
use App\Services\AdminAuditLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (): void {
                    app(AdminAuditLogService::class)->log(
                        action: 'admin.user.deleted',
                        actor: Auth::user(),
                        target: $this->record,
                        category: 'admin',
                        context: [
                            'email' => $this->record->email,
                        ],
                    );
                }),
        ];
    }

    protected function afterSave(): void
    {
        $user = $this->record->fresh();
        if (filled($this->data['password'] ?? null)) {
            $user->update(['force_password_reset' => false]);
            $user->tokens()->delete();
            $user = $user->fresh();
        }

        if ($user->role === UserRole::Artisan) {
            ArtisanProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty' => $this->data['artisanProfile']['specialty'] ?? null,
                    'is_active' => (bool) ($this->data['artisanProfile']['is_active'] ?? true),
                ]
            );
        } else {
            ArtisanProfile::query()->where('user_id', $user->id)->delete();
        }

        app(AdminAuditLogService::class)->log(
            action: 'admin.user.updated',
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
