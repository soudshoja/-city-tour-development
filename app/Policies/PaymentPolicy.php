<?php

namespace App\Policies;

use App\Models\Payment;
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

    public function view(User $user, Payment $payment) : bool
    {
        if($user->can('view payment')) return true;

        if($user->agent !== null && $payment->agent_id === $user->agent->id) {
            return true;
        }   

        return false;
    }
}
