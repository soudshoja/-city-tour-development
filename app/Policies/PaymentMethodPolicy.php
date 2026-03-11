<?php

namespace App\Policies;

use App\Models\User;

class PaymentMethodPolicy
{
    public function __construct()
    {
        //
    }

    public function managePaymentMethodGroup(User $user): bool
    {
        if($user->hasRole('admin')) return true;

        return $user->can('manage payment method groups');

        return false;
    }
}
