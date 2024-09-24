<?php

namespace App\Policies;

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
        return $user->role === 'admin' || $user->role === 'company';
    }

    public function view(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company';
    }

    public function update(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company';
    }

    public function delete(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company';
    }
}
