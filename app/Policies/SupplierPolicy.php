<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('view supplier');
    }

    public function view(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('view supplier');
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('create supplier');
    }

    public function update(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('update supplier');
    }
}
