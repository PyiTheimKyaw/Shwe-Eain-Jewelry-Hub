<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PaymentProfile;
use App\Models\User;

class PaymentProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, PaymentProfile $paymentProfile): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, PaymentProfile $paymentProfile): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, PaymentProfile $paymentProfile): bool
    {
        return $user->role === UserRole::Admin;
    }
}
