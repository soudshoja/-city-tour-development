<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F): "permission accounting.audit.view (admin/accountant default)."
 * Same dual-check convention every accounting Policy in this codebase already uses
 * ({@see AccountingPeriodPolicy}, {@see \App\Services\Accounting\ReconciliationService::assertCanReconcile()}).
 */
class AccountingAuditLogPolicy
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
            || $user->can('accounting.audit.view');
    }
}
