<?php

namespace App\Policies;

use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view supplier');
    }

    public function view(User $user): bool
    {
        return $user->can('view supplier');
    }
}
