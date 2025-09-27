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
            return $user->can('view task');
        }
        
        if($user->hasRole('admin')) return true;

        return $user->can('view task');
    }

    public function viewPrice(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('view task price');
        }

        return $user->can('view task price');
    }

    public function store(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('create task');
        }
        
        return $user->can('create task');
    }

    public function destroy(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        return false;
    }
}
