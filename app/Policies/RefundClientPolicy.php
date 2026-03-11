<?php

namespace App\Policies;

use App\Models\RefundClient;
use App\Models\Role;
use App\Models\User;

class RefundClientPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function complete(User $user)
    {
        return $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT;
    }

    public function delete(User $user, RefundClient $refundClient): bool
    {
        if ($user->role_id == Role::AGENT) {
            return $refundClient->agent->id == $user->agent->id;
        }

        return $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT;
    }
}
