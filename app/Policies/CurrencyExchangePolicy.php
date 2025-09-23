<?php

namespace App\Policies;

use App\Models\User;

class CurrencyExchangePolicy
{
    public function __construct()
    {
        //
    }

    public function viewAny(User $user)
    {
       return $user->can('view currency exchange');
    }
}
