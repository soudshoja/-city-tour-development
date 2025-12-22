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
        return $user->can('manage payment method groups');

        return false;
    }
}
