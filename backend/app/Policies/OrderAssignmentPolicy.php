<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OrderAssignment;
use App\Models\User;

class OrderAssignmentPolicy
{
    public function view(User $user, OrderAssignment $assignment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Artisan && $assignment->artisan_user_id === $user->id;
    }

    public function update(User $user, OrderAssignment $assignment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Artisan && $assignment->artisan_user_id === $user->id;
    }
}
