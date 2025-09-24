<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BranchPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('accountant')) {
            return false;
        }
        return $user->can('view branch');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Branch $branch): bool
    {
        return $user->can('view branch') || $user->company->id === $branch->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create branch');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->can('update branch');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('delete branch');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Branch $branch): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Branch $branch): bool
    {
        //
    }
}
