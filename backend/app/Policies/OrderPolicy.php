<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Customer && $order->customer_user_id === $user->id) {
            return true;
        }

        if ($user->role === UserRole::Artisan) {
            return $order->assignments()->where('artisan_user_id', $user->id)->exists();
        }

        return false;
    }

    public function manage(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function addProgress(User $user, Order $order): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Artisan) {
            return $order->assignments()->where('artisan_user_id', $user->id)->exists();
        }

        return false;
    }
}
