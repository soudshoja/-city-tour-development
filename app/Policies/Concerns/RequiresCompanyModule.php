<?php

namespace App\Policies\Concerns;

use App\Models\Company;
use App\Models\User;

/**
 * Mixed into any Policy class whose abilities belong to one of the
 * package modules in App\Support\Modules. Each gated policy method calls
 * moduleEnabled($user, Modules::X) as its FIRST check (existing permission
 * logic below it is left completely untouched):
 *
 *     public function viewAny(User $user): bool
 *     {
 *         if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
 *             return false;
 *         }
 *
 *         return $user->can('view coa'); // unchanged
 *     }
 */
trait RequiresCompanyModule
{
    /**
     * Whether $user's current company has $module switched on.
     *
     * "Current company" is resolved via the SAME getCompanyId() helper
     * (app/Helper/helper.php) the rest of the app already uses to find a
     * user's company — this matters because it is the only path that
     * correctly handles a Role::ADMIN user, whose company comes from
     * session('company_id'), not from any relation on the User model.
     * Reusing it (rather than $user->company) is what keeps this gate from
     * silently denying admins who have no owned/branch/agent company row.
     *
     * A user who resolves to NO company at all (no session company_id,
     * and none of the company/branch/agent/accountant relations apply —
     * e.g. an unrecognized role) fails CLOSED: false. There is no company
     * entitlement to check, so there is nothing to grant access to. This
     * only affects the abilities this trait gates — every module defaults
     * ON for a company that legitimately resolves but has no `module.*`
     * rows yet (see Company::hasModule()), so no existing, properly
     * company-scoped user loses access because of this trait.
     */
    protected function moduleEnabled(User $user, string $module): bool
    {
        $companyId = getCompanyId($user);

        if (! $companyId) {
            return false;
        }

        $company = Company::find($companyId);

        return $company !== null && $company->hasModule($module);
    }
}
