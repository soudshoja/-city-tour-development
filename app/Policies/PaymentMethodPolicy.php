<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class PaymentMethodPolicy
{
    use RequiresCompanyModule;

    public function __construct()
    {
        //
    }

    public function viewPaymentMethodGroup(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->can('view payment method groups');
    }

    public function managePaymentMethodGroup(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->can('manage payment method groups');
    }
}
