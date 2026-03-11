<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class SystemSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role_id === Role::ADMIN;
    }

    public function manageEmailTester(User $user): bool
    {
        return $user->role_id === Role::ADMIN;
    }
}
