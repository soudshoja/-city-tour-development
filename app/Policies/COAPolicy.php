<?php

namespace App\Policies;

use App\Models\CoaCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class COAPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if($user->hasRole('admin')) return true;
        
        return $user->can('view coa');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CoaCategory $coaCategory): bool
    {
        if($user->hasRole('admin')){
            return true;
        }
        return $user->can('view coa');

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        
        return $user->can('create coa');

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CoaCategory $coaCategory): bool
    {
        return $user->can('update coa');

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CoaCategory $coaCategory): bool
    {
        return $user->can('delete coa');

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CoaCategory $coaCategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CoaCategory $coaCategory): bool
    {
        return false;
    }
}
