<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClientPolicy
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
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY || $user->role_id === Role::AGENT;
    }

    public function view(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY || $user->role_id === Role::AGENT;
    }

    public function create(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY || $user->role_id === Role::AGENT;
    }

    public function update(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY || $user->role_id === Role::AGENT;
    }

    public function delete(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY || $user->role_id === Role::AGENT;
    }
}
