<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * accounting-builds T10 (Lane G): permissions for the fixed-asset back-office screens
 * (`App\Http\Controllers\Accounting\FixedAssetController`). Follows the SAME dual-check
 * convention every other accounting Policy in this cutover already uses
 * ({@see ReconciliationProposalPolicy}, {@see AccountingPeriodPolicy}) — module entitlement
 * first (`RequiresCompanyModule::moduleEnabled()`), then an admin/company/accountant role tier
 * OR an explicit Spatie permission.
 *
 * `view` gates the register list, create/edit forms (read-only rendering is still a `view`
 * action for the show page — see the controller), and the asset detail page. `manage` gates every
 * state-changing action: create/store, update, capitalise, dispose, and the depreciation run
 * (both dry-run preview and the real post) — a dry-run preview is read-only against the ledger
 * but it is still gated as `manage` because it previews an action only a manager should be able to
 * trigger at all (consistent with `ReconciliationProposalPolicy::manage()` gating `run-now`, which
 * is also non-destructive-until-approved).
 *
 * Auto-discovered against {@see \App\Models\FixedAsset} by Laravel's `{Model}Policy` <->
 * `{Model}` naming convention — the same convention already pairs
 * `ReconciliationProposalPolicy`/`AccountingPeriodPolicy` with their own models, so no explicit
 * registration is needed anywhere.
 */
class FixedAssetPolicy
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
            || $user->can('accounting.fixed_assets.view')
            || $user->can('accounting.fixed_assets.manage');
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
            || $user->can('accounting.fixed_assets.manage');
    }
}
