<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductImage;
use App\Models\User;

class ProductImagePolicy
{
    public function delete(User $user, ProductImage $productImage): bool
    {
        return $user->role === UserRole::Admin;
    }
}
