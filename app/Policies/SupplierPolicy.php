<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Supplier;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class SupplierPolicy
{
    use RequiresCompanyModule;

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('view supplier');
    }

    public function view(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('view supplier');
    }

    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('create supplier');
    }

    public function update(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('update supplier');
    }
}
