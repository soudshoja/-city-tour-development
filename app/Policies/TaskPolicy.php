<?php

namespace App\Policies;

use App\Models\User;

class TaskPolicy
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
        if ($user->hasRole('accountant')) {
            return false;
        }
        
        if($user->hasRole('admin')) return true;

        return $user->can('view task');
    }

    public function viewPrice(User $user): bool
    {
        if($user->roles('admin')) return true;

        return $user->can('view task price');
    }

    public function store(User $user): bool
    {
        if($user->roles('admin')) return true;

        return $user->can('create task');
    }

    public function destroy(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        return false;
    }
}
