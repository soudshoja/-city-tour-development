<?php

namespace App\Policies;

use App\Models\User;

class PaymentPolicy
{
    public function __construct()
    {
        //
    }

    public function viewAny(User $user) : bool
    {
        return $user->can('view payment');
    }
}
