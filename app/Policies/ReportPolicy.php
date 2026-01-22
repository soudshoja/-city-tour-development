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
        if ($user->hasRole('accountant')) {
            return false;
        }
       return $user->can('view report');
    }

    public function viewPayableSupplier(User $user) 
    {
        if ($user->hasRole('accountant')) {
            return false;
        }

        return $user->can('view payable');
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

    public function viewDailySales(User $user)
    {
        if($user->hasRole('admin')) return true;

        return $user->can('view daily sales');
    }

    public function viewTaskReport(User $user)
    {
        if($user->hasRole('admin')) return true;

        return $user->can('view task report');
    }

    public function viewClientReport(User $user)
    {
        if($user->hasRole('admin')) return true;

        return $user->can('view client report');
    }
}
