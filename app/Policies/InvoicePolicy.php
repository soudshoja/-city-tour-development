<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    use RequiresCompanyModule;

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

       return $user->can('view invoice');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->can('view invoice') || $user->id == $invoice->user_id;
    }

    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->role_id == Role::COMPANY) return true;

        return $user->can('create invoice');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return $user->can('update invoice');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        //
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        //
    }

    public function pickAgent(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY;
    }

    public function accountantEdit(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if($user->role_id == Role::ACCOUNTANT) return true;

        return false;
    }

    /**
     * W3a EDIT PERMISSION GATES (owner decision, 2026-08-27): staff DO edit amounts/lines on an
     * already-issued invoice in real practice, but only admin/accountant may — not free-for-all.
     * Checked BEFORE the reverse_repost edit path is allowed to fire on an already-issued invoice
     * (see InvoiceController's own call site). Admin-configurable in the sense every other
     * role-gated ability in this policy already is: a company can grant the underlying Spatie
     * permission to a broader/narrower set of roles without a code change, same as
     * `accountantEdit()` above and `update()`'s own `user->can('update invoice')` fallback.
     *
     * Follows this policy's own existing dual-check convention (hasRole() OR the legacy
     * role_id === Role::X integer check — see update()/accountantEdit() above and
     * ClientPolicy::viewAny() for the same pattern elsewhere in this codebase) rather than
     * trusting only one of the two, since not every seeded admin/accountant row in this codebase
     * carries both a Spatie role row and the matching role_id today.
     */
    public function editAfterIssue(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || $user->role_id == Role::ADMIN
            || $user->role_id == Role::ACCOUNTANT;
    }

    /**
     * W3a EDIT PERMISSION GATES (owner decision, 2026-08-27): staff DO edit invoice/transaction/
     * journal-entry dates in real practice, but only admin/accountant may. See editAfterIssue()'s
     * own docblock for the shared rationale and role-check convention — kept as a SEPARATE
     * ability (not folded into editAfterIssue()) because a company may reasonably want to grant
     * one without the other (e.g. allow date corrections but not amount edits) once these
     * abilities are wired to distinct Spatie permissions.
     */
    public function editDates(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || $user->role_id == Role::ADMIN
            || $user->role_id == Role::ACCOUNTANT;
    }
}
