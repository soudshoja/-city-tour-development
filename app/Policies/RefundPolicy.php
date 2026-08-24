<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class RefundPolicy
{
    use RequiresCompanyModule;

    public function __construct()
    {
        //
    }

    public function viewAny(User $user) : bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->can('view refund');
    }
}
