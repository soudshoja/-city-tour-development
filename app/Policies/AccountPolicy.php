<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Account;

class AccountPolicy
{
    public function viewAny(User $user) : bool
    {
        return $user->can('view account');
    }

    public function view(User $user, Account $account) : bool
    {
        return $user->can('view account') || $user->id == $account->user_id;
    }

    public function viewCompanySummary(User $user) : bool
    {
        return $user->can('view company summary');
    }
}
