<?php

namespace App\Policies;

use App\Models\User;

class RolePolicy
{
    public function __construct()
    {
        //
    }

    public function viewAny(User $user) : bool
    {
        if($user->hasRole('admin')) return true;

        return $user->can('view role');
    }

    public function view(User $user) : bool
    {
        if($user->hasRole('admin')) return true;

        return $user->can('view role');
    }
    
    public function create(User $user) : bool
    {
        if($user->hasRole('admin')) return true;

        return $user->can('create role');
    }

    public function update(User $user) : bool
    {
        if($user->hasRole('admin')) return true;

        return $user->can('update role');
    }

    public function delete(User $user) : bool
    {
        if($user->hasRole('admin')) return true;

        return $user->can('delete role');
    }
}
