<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChargePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        return $user->can('view charges');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Charge $charge): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        } elseif ($user->hasRole('company') || $user->hasRole('agent')) {
            return $user->can('create charges');
        } elseif ($user->hasRole('accountant')) {
            return false;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can create a system gateway.
     */
    public function createSystemGateway(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ?Charge $charge = null): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        } elseif ($user->hasRole('company') || $user->hasRole('agent')) {
            return $user->can('update charges');
        } elseif ($user->hasRole('accountant')) {
            return false;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can fully edit the gateway (all fields).
     */
    public function updateAll(User $user, Charge $charge): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($charge->is_system_default) {
            return false;
        }

        return $user->hasRole('company') || $user->hasRole('agent');
    }

    /**
     * Determine whether the user can update limited fields (self_charge, description).
     */
    public function updateLimited(User $user, Charge $charge): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($charge->is_system_default && ($user->hasRole('company') || $user->hasRole('agent'))) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Charge $charge): bool
    {
        if (!$charge->can_be_deleted) {
            return false;
        }

        if ($charge->is_system_default && !$user->hasRole('admin')) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        } elseif ($user->hasRole('company') || $user->hasRole('agent')) {
            return $user->can('delete charges');
        } elseif ($user->hasRole('accountant')) {
            return false;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can update API credentials.
     */
    public function updateCredentials(User $user, Charge $charge): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('company')) {
            return true;
        }

        if ($charge->is_system_default) {
            return false;
        }

        return false;
    }

    /**
     * Determine whether the user can toggle active status.
     */
    public function toggleActive(User $user, Charge $charge): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('company') || $user->hasRole('agent');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Charge $charge): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Charge $charge): bool
    {
        return false;
    }
}
