<?php

namespace App\Policies;

use App\Models\AutoBilling;
use App\Models\User;
use App\Models\Role;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;
use Illuminate\Auth\Access\HandlesAuthorization;

class AutoBillingPolicy
{
    use HandlesAuthorization;
    use RequiresCompanyModule;

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('view auto billing');
    }

    public function view(User $user, AutoBilling $autoBilling): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('view auto billing');
    }

    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('create auto billing');
    }

    public function update(User $user, AutoBilling $autoBilling): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('update auto billing');
    }

    public function delete(User $user, AutoBilling $autoBilling): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('delete auto billing');
    }
}
