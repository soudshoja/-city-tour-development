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
    return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function edit(User $user, Client $client): bool
    {
        return ($user->role_id === Role::ADMIN ||
            ($user->role_id === Role::COMPANY && $user->company->id === $client->agent->branch->company_id) ||
            ($user->role_id === Role::AGENT && $user->id === $client->agent->user_id));

    }

    public function clientAgent(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY || $user->role_id === Role::BRANCH || $user->role_id === Role::AGENT;
    }
    
    public function update(User $user, Client $client): bool
    {
        return ($user->role_id === Role::ADMIN ||
            ($user->role_id === Role::COMPANY && $user->company->id === $client->agent->branch->company_id) ||
            ($user->role_id === Role::AGENT && $user->id === $client->agent->user_id));
    }

    public function delete(User $user): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY || $user->role_id === Role::AGENT;
    }
}
