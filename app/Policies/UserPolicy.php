<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user)
    {
        return $user->can('view user');
    }

    public function create(User $user)
    {
        return $user->can('create user');
    }

    public function manageLocks(User $user): bool
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('manage locks');
    }
}
