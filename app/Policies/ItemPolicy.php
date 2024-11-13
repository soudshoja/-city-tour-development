<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class ItemPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAny(User $user): bool
    {
    return true;
    }

    public function view(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    public function create(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    public function update(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    public function delete(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }
}
