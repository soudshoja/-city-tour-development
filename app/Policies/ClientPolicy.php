<?php

namespace App\Policies;

use App\Models\Client;
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
        return $user->role === 'admin' || $user->role === 'company' || $user->role === 'agent';
    }

    public function view(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company' || $user->role === 'agent';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company' || $user->role === 'agent';
    }

    public function update(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company' || $user->role === 'agent';
    }

    public function delete(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'company' || $user->role === 'agent';
    }
}