<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user)
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    public function settingCompanyInvoice(User $user)
    {
        return $user->hasPermissionTo('setting company invoice');
    }

    public function viewAgentCharges(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('view agent charges');
    }

    public function manageAgentCharges(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('manage agent charges');
    }

    public function bulkManageAgentCharges(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('manage agent charges') && !$user->agent;
    }

    public function viewAgentLoss(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('view agent loss');
    }

    public function manageAgentLoss(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('manage agent loss');
    }

    public function bulkManageAgentLoss(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('manage agent loss') && !$user->agent;
    }

    public function viewNotifications(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('view notification');
    }

    public function manageNotifications(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('manage notification');
    }

    public function bulkManageNotifications(User $user)
    {
        if ($user->hasRole('admin')) return true;

        return $user->can('manage notification') && !$user->agent;
    }
}
