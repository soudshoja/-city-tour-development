<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class ReportPolicy
{
    use RequiresCompanyModule;

    public function __construct()
    {
        //
    }

    // viewAny gates the generic Reports landing page. What sits behind it
    // (trial balance, P&L, creditors, daily sales, reconcile...) is
    // overwhelmingly the accounting reports hub, so it is gated on
    // `accounting` like the report methods below it.
    public function viewAny(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->hasRole('accountant')) {
            return false;
        }
       return $user->can('view report');
    }

    public function viewPayableSupplier(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->hasRole('accountant')) {
            return false;
        }

        return $user->can('view payable');
    }

    public function viewReconcile(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view reconcile report');
    }

    public function viewProfitLoss(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view profit loss');
    }

    // Settlement reporting is agent-profit-specific, not general
    // accounting — gated on Modules::AGENT_PROFIT instead.
    public function viewSettlement(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        return $user->can('view settlement');
    }

    // AP-3: reports/profit-agent (ReportController::profitAgent() /
    // getProfitAgent()) had no authorization at all — any authenticated
    // user could load every agent's profit figures regardless of company
    // or module. Reuses the existing 'view agent' permission (same one
    // AgentPolicy gates the agent screens on) rather than adding a new
    // permission name for what is the same underlying capability viewed
    // through a report instead of the agent detail page.
    public function viewProfitAgent(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        return $user->can('view agent');
    }

    public function viewCreditors(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->can('view creditors');
    }

    public function viewDailySales(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->can('view daily sales');
    }

    // Task reporting belongs to the Task Uploader module.
    public function viewTaskReport(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->can('view task report');
    }

    // Client reporting belongs to the Customer CRM module.
    public function viewClientReport(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::CRM)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->can('view client report');
    }

    // Payment gateway reporting belongs to the Payment Gateway module.
    public function viewPaymentGatewaysReport(User $user)
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->can('view payment gateways report');
    }
}
