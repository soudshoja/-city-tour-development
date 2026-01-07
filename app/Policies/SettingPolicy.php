<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user)
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }
}