<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) UI sub-scope: the reminders settings tab + reminder log screen.
 * Same dual-check convention every other P2.5 screen policy in this codebase uses (see
 * {@see AccountingPeriodPolicy}'s own docblock) -- admin/company/accountant role tier OR an
 * explicit Spatie permission, so a company can later delegate either ability to a narrower role
 * without code changes.
 */
class ReminderPolicy
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
            || $user->can('accounting.reminders.view')
            || $user->can('accounting.reminders.manage');
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
            || $user->can('accounting.reminders.manage');
    }
}
