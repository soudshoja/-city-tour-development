<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Account;

class AccountPolicy
{
    public function viewAny(User $user) : bool
    {
        return $user->can('read account');
    }

    public function viewCompanySummary(User $user) : bool
    {
        return $user->can('read company summary');
    }
}
