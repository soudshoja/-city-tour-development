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
        if ($user->hasRole('accountant')) {
            return false;
        }
        return $user->can('view client');
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->hasRole('admin') || $user->role_id == Role::ADMIN) {
            return true;
        } elseif ($user->role_id == Role::COMPANY) {
            $agentsId = $user->company->branches->flatMap->agents->pluck('id')->toArray();
            return in_array($client->agent_id, $agentsId) || !empty(array_intersect($client->agents->pluck('id')->toArray(), $agentsId));
        } elseif ($user->role_id == Role::BRANCH) {
            return $client->agent?->branch_id === optional($user->branch)->id;
        } elseif ($user->role_id == Role::AGENT) {
            $agentId = $user->agent->id;
            return $client->agent_id === $agentId || $client->agents()->whereKey($agentId)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('create client');
    }

    public function edit(User $user, Client $client): bool
    {
        return ($user->role_id == Role::ADMIN ||
            ($user->role_id == Role::COMPANY && $user->company->id === $client->agent->branch->company_id) ||
            ($user->role_id == Role::AGENT && $user->id === $client->agent->user_id));

    }

    public function clientAgent(User $user): bool
    {
        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY || $user->role_id == Role::BRANCH || $user->role_id == Role::AGENT;
    }
    
    public function update(User $user): bool
    {
        return true;
    }

    public function delete(User $user): bool
    {
        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY || $user->role_id == Role::AGENT;
    }

    public function assignAgents(User $user, Client $client): bool
    {
        if($user->role_id == Role::AGENT) {
            return $user->agent->id === $client->agent_id;
        }

        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY;
    }


    public function assignOwnerAgent(User $user)
    {
        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY;
    }
}
