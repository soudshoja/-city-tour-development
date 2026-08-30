<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class PaymentPolicy
{
    use RequiresCompanyModule;

    public function __construct()
    {
        //
    }

    public function viewAny(User $user) : bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->can('view payment');
    }

    public function view(User $user, Payment $payment) : bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->can('view payment')) return true;

        if($user->agent !== null && $payment->agent_id === $user->agent->id) {
            return true;
        }

        return false;
    }
}
