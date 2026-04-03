<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Customer && $quote->customer_user_id === $user->id;
    }

    public function manage(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function accept(User $user, Quote $quote): bool
    {
        if ($user->role !== UserRole::Customer || $quote->customer_user_id !== $user->id) {
            return false;
        }

        return $quote->status === 'sent';
    }

    public function decline(User $user, Quote $quote): bool
    {
        return $this->accept($user, $quote);
    }
}
