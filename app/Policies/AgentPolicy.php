<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;
use Illuminate\Auth\Access\Response;

class AgentPolicy
{
    use RequiresCompanyModule;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        if ($user->hasRole('accountant')) {
            return false;
        }
        return $user->can('view agent');
    }

    /**
     * Determine whether the user can view the model.
     *
     * AP-1 fix: the previous implementation resolved the caller's company
     * via $user->branch / $user->company only, which never resolves for a
     * Role::ADMIN (their company comes from session('company_id'), not any
     * relation on User — see User::company()'s own doc). That silently
     * denied every admin rather than scoping them, and — because this
     * ability was never actually invoked anywhere before AP-1 wired it into
     * AgentController — was never caught. Rewritten on the same
     * getCompanyId()-based same-company-or-unscoped-admin pattern already
     * used by CreditController::assertSameCompanyOrUnscopedAdmin() /
     * ReceiptVoucherController's identical copy, so this ability agrees
     * with every other cross-tenant check in the app and actually denies a
     * user of a different company.
     */
    public function view(User $user, Agent $agent): bool
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        $agentCompanyId = $agent->branch?->company_id;

        if (! $agentCompanyId) {
            return false;
        }

        $companyId = getCompanyId($user);

        if ($user->role_id === Role::ADMIN) {
            // Unscoped admin (no company selected) is the one legitimate
            // cross-company case.
            return ! $companyId || (int) $companyId === (int) $agentCompanyId;
        }

        return (int) $companyId === (int) $agentCompanyId;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        return $user->can('create agent');
    }

    /**
     * Determine whether the user can update the model.
     *
     * AP-1 fix: `$agent->branch()->company()->id` called `->company()` on
     * the BelongsTo *relation query builder* returned by `$agent->branch()`
     * (note the parens — not the loaded `branch` relation), which has no
     * such method and threw a BadMethodCallException on every call for a
     * Role::COMPANY user. `$user->company_id` is also not a real column —
     * User has no `company_id` attribute (company is resolved via a
     * computed Attribute, see User::company()) — so it always evaluated to
     * null even had the first half not thrown first. Net effect: this
     * ability has never actually worked for a Role::COMPANY user (fatal
     * error), and for Role::ADMIN it returned true unconditionally with NO
     * company check at all — an admin scoped to one company could edit any
     * other company's agent's salary. Rewritten on the same
     * getCompanyId()-based pattern as view() above, restricted to the same
     * ADMIN/COMPANY roles the original intended.
     */
    public function update(User $user, Agent $agent): bool
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        if (! in_array($user->role_id, [Role::ADMIN, Role::COMPANY], true)) {
            return false;
        }

        $agentCompanyId = $agent->branch?->company_id;

        if (! $agentCompanyId) {
            return false;
        }

        $companyId = getCompanyId($user);

        if ($user->role_id === Role::ADMIN) {
            return ! $companyId || (int) $companyId === (int) $agentCompanyId;
        }

        return (int) $companyId === (int) $agentCompanyId;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Agent $agent): bool
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Agent $agent): bool
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Agent $agent): bool
    {
        if (! $this->moduleEnabled($user, Modules::AGENT_PROFIT)) {
            return false;
        }

        return $user->role_id === Role::ADMIN || $user->role_id === Role::COMPANY;
    }
}
