<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Customer && $payment->order->customer_user_id === $user->id) {
            return true;
        }

        return false;
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->role === UserRole::Admin;
    }
}
