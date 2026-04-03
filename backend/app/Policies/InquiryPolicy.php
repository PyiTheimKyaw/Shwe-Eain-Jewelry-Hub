<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;

class InquiryPolicy
{
    public function view(User $user, Inquiry $inquiry): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Customer && $inquiry->customer_user_id === $user->id;
    }

    public function update(User $user, Inquiry $inquiry): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Publish a quote into the inquiry thread (admin chat console).
     */
    public function publishQuote(User $user, Inquiry $inquiry): bool
    {
        return $user->role === UserRole::Admin;
    }
}
