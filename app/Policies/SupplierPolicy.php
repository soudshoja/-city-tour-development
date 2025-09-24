<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('accountant')) {
            return $user->can('view supplier');
        }
        return $user->can('view supplier');
    }

    public function view(User $user): bool
    {
        return $user->can('view supplier');
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('accountant')) {
            return $user->can('create supplier');
        }
    }

    public function update(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('accountant')) {
            return $user->can('update supplier');
        }
        
        return false;
    }

    public function store(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('accountant')) {
            return $user->can('update supplier');
        }
        
        return false;
    }
}
