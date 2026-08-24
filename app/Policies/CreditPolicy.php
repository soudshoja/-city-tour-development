<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class CreditPolicy
{
    use RequiresCompanyModule;

    public function __construct()
    {
        //
    }

    public function viewAny(User $user) : bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view credit');
    }
}
