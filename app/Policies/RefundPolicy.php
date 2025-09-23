<?php

namespace App\Policies;

use App\Models\User;

class RefundPolicy
{
    public function __construct()
    {
        //
    }

    public function viewAny(User $user) : bool
    {
        return $user->can('view refund');
    }
}
