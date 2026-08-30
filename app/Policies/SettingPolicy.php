<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class SettingPolicy
{
    use RequiresCompanyModule;

    public function viewAny(User $user)
    {
        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    /**
     * W4.U (w4-brief.md "W4.U -- UI" §a). Gates the new "Accounting" settings tab (posting-engine
     * company options this wave introduced: invoice_overpay_cancel_policy, fee schedule,
     * unclaimed_writeback_months, commissionable_fee_types, posting_basis, bearer matrix,
     * notification toggles). Gated behind Modules::ACCOUNTING — same module the ledger/COA/reports
     * live behind — since these options only mean anything once a company can see accounting at
     * all; admin/company/accountant tier, mirroring viewAgentCharges()'s own shape.
     */
    public function viewAccountingSettings(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->role_id === Role::ADMIN
            || $user->role_id === Role::COMPANY
            || $user->role_id === Role::ACCOUNTANT
            || $user->can('view accounting settings');
    }

    /** W4.U — persisting the Accounting tab's form. Same tier as manageAgentCharges(). */
    public function manageAccountingSettings(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->role_id === Role::ADMIN
            || $user->role_id === Role::COMPANY
            || $user->role_id === Role::ACCOUNTANT
            || $user->can('manage accounting settings');
    }

    public function settingCompanyInvoice(User $user)
    {
        return $user->hasPermissionTo('setting company invoice');
    }

    // The 6 methods below are agent-profit-specific (agent charge/loss
    // settings). settingCompanyInvoice above and the 3 notification
    // methods below stay ungated — they are general company settings, not
    // part of the Agent Profit Calculation module.

    public function viewAgentCharges(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->can('view agent charges');
    }

    public function manageAgentCharges(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->can('manage agent charges');
    }

    public function bulkManageAgentCharges(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->can('manage agent charges') && !$user->agent;
    }

    public function viewAgentLoss(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->can('view agent loss');
    }

    public function manageAgentLoss(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        return $user->can('manage agent loss');
    }

    public function bulkManageAgentLoss(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

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
