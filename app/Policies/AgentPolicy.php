<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AgentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view agent');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Agent $agent): bool
    {
        if($user->can('view agent')) return true;

        if($user->branch) {
            return $user->branch->id === $agent->branch_id;
        }

        if($user->company) {

            $branchesId = $user->company->branches->pluck('id')->toArray();
            return in_array($agent->branch_id, $branchesId);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create agent');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Agent $agent): bool
    { 
        return $user->role_id === Role::ADMIN || ( $user->role_id === Role::COMPANY && $user->company_id === $agent->branch()->company()->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Agent $agent): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Agent $agent): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Agent $agent): bool
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }
}
