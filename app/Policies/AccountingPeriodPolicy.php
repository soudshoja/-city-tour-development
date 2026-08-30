<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C): "Permissions: accounting.period.close, .reopen,
 * .post-soft-closed." `.post-soft-closed` is consulted directly by
 * {@see \App\Services\Accounting\PeriodGuard} (a posting-time bypass, not a controller ability —
 * see that class's own docblock) and therefore has no ability method here. `.close`/`.reopen` gate
 * `App\Http\Controllers\Accounting\PeriodController`'s screen actions; the SAME dual-check
 * (admin/accountant role tier OR the explicit Spatie permission) is re-run inside
 * {@see \App\Services\Accounting\PeriodCloseService} itself for the console command's benefit (a
 * console run has no Policy/Gate context) — this Policy exists so the HTTP layer gets Laravel's
 * ordinary `Gate::authorize()`/`@can` ergonomics without duplicating a different rule.
 */
class AccountingPeriodPolicy
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
            || $user->can('accounting.period.close');
    }

    public function close(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->role_id === Role::ADMIN
            || $user->role_id === Role::COMPANY
            || $user->role_id === Role::ACCOUNTANT
            || $user->can('accounting.period.close');
    }

    public function reopen(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->role_id === Role::ADMIN
            || $user->role_id === Role::COMPANY
            || $user->role_id === Role::ACCOUNTANT
            || $user->can('accounting.period.reopen');
    }
}
