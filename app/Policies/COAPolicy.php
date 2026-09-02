<?php

namespace App\Policies;

use App\Models\CoaCategory;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;
use Illuminate\Auth\Access\Response;

class COAPolicy
{
    use RequiresCompanyModule;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->can('view coa');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CoaCategory $coaCategory): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if($user->hasRole('admin')){
            return true;
        }
        return $user->can('view coa');

    }

    /**
     * Determine whether the user can create models.
     *
     * COA UI lane (2026-08-31): added the same hasRole('admin') bypass viewAny()/view() above
     * already use — this method (like update()/delete() below) never had one, a pre-existing
     * inconsistency within this same policy class that only surfaced once something actually
     * called these abilities (they were previously dead code, never invoked anywhere in the app).
     * Also drops the unreachable `return false;` that followed the real return in every one of
     * these three methods.
     */
    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('create coa');
    }

    /**
     * Determine whether the user can update the model.
     *
     * COA UI lane (2026-08-31): $coaCategory made nullable/optional. This ability was never
     * previously invoked anywhere in the codebase with a real CoaCategory instance (grep-verified
     * before this change), and CoaController's new updateCode()/toggleDisabled() actions operate on
     * Account rows, not a CoaCategory model — there is no instance to pass. Laravel's
     * Gate::authorize('update', CoaCategory::class) (a bare class-string ability check, the same
     * pattern index()'s own Gate::authorize('viewAny', CoaCategory::class) already uses) calls the
     * policy method with ONLY $user when the method's other parameters are optional; a required
     * second parameter made this a hard ArgumentCountError, not merely a permission denial.
     */
    public function update(User $user, ?CoaCategory $coaCategory = null): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('update coa');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * COA UI lane (2026-08-31): same $coaCategory nullable/optional fix as update() above, for the
     * same reason -- CoaController::dstry() has no CoaCategory instance to pass.
     */
    public function delete(User $user, ?CoaCategory $coaCategory = null): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('delete coa');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CoaCategory $coaCategory): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CoaCategory $coaCategory): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return false;
    }
}
