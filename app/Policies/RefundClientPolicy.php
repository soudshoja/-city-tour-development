<?php

namespace App\Policies;

use App\Models\RefundClient;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class RefundClientPolicy
{
    use RequiresCompanyModule;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function complete(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT;
    }

    public function delete(User $user, RefundClient $refundClient): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if ($user->role_id == Role::AGENT) {
            return $refundClient->agent->id == $user->agent->id;
        }

        return $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT;
    }
}
