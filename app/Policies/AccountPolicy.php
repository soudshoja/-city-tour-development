<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Account;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class AccountPolicy
{
    use RequiresCompanyModule;

    public function viewAny(User $user) : bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view account');
    }

    public function view(User $user, Account $account) : bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view account') || $user->id == $account->user_id;
    }

    public function create(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('create account');
    }

    public function viewCompanySummary(User $user) : bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view company summary');
    }
}
