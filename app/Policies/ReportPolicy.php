<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function __construct()
    {
        //
    }

    public function viewAny(User $user)
    {
       return $user->can('view report');
    }

    public function viewReconcile(User $user)
    {
        return $user->can('view reconcile report');
    }

    public function viewProfitLoss(User $user)
    {
        return $user->can('view profit loss');
    }

    public function viewSettlement(User $user)
    {
        return $user->can('view settlement');
    }

    public function viewCreditors(User $user)
    {
        return $user->can('view creditors');
    }
}
