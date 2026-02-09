<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class LockManagementPolicy
{
    use HandlesAuthorization;

    public function manage(User $user): bool
    {
        if ($user->hasRole('admin')) return true;

        return $user->hasPermissionTo('manage locks');
    }
}
