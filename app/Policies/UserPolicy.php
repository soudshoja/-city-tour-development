<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user)
    {
        return $user->can('read user');
    }

    public function create(User $user)
    {
        return $user->can('create user');
    }
}
