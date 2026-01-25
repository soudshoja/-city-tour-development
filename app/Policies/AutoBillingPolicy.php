<?php

namespace App\Policies;

use App\Models\AutoBilling;
use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AutoBillingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('view auto billing');
    }

    public function view(User $user, AutoBilling $autoBilling): bool
    {
        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('view auto billing');
    }

    public function create(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('create auto billing');
    }

    public function update(User $user, AutoBilling $autoBilling): bool
    {
        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('update auto billing');
    }

    public function delete(User $user, AutoBilling $autoBilling): bool
    {
        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('delete auto billing');
    }
}