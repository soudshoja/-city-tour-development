<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "Permissions: accounting.reconcile.view (see the grid),
 * accounting.reconcile (approve/match/fix-now)." Same dual-check convention every accounting
 * Policy in this codebase already uses ({@see \App\Policies\AccountingPeriodPolicy},
 * {@see \App\Policies\AccountingAuditLogPolicy}, {@see \App\Services\Accounting\ReconciliationService::assertCanReconcile()}).
 *
 * Auto-discovered against {@see \App\Models\ReconciliationProposal} (Laravel's
 * `{Model}Policy` <-> `{Model}` convention — the same one already pairs
 * AccountingPeriodPolicy/AccountingAuditLogPolicy with their own models) even though most actions
 * this policy gates (`view` the grid, `manage` approve/reject/match/unmatch/fix-now) act over a
 * whole company's reconciliation state, not one proposal row — `Gate::authorize('view',
 * ReconciliationProposal::class)` (the class string, no instance) is the same "authorize against
 * the class, not a specific row" shape `AccountingPeriodPolicy::view()` already established for
 * `AccountingPeriod::class`.
 */
class ReconciliationProposalPolicy
{
    use RequiresCompanyModule;

    public function view(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->role_id === Role::ADMIN
            || $user->role_id === Role::COMPANY
            || $user->role_id === Role::ACCOUNTANT
            || $user->can('accounting.reconcile.view')
            || $user->can('accounting.reconcile');
    }

    public function manage(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->role_id === Role::ADMIN
            || $user->role_id === Role::COMPANY
            || $user->role_id === Role::ACCOUNTANT
            || $user->can('accounting.reconcile');
    }
}
