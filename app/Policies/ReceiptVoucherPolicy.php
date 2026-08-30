<?php

namespace App\Policies;

use App\Models\InvoiceReceipt;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * W5.R / W5.X (w5-brief.md §W5.X "Policies: ReceiptVoucherPolicy / BankPaymentPolicy full
 * abilities (viewAny/view/create/update/approve/delete) enforced on every route; reconcile
 * actions require accounting.reconcile permission"). Bound to {@see InvoiceReceipt} explicitly in
 * AppServiceProvider (RV has no model whose class name is literally "ReceiptVoucher" -- see that
 * provider's own comment).
 *
 * Same dual-check convention every other policy in this codebase uses (RefundPolicy/InvoicePolicy):
 * Spatie `$user->can(...)` OR the legacy integer `role_id`, never only one -- this codebase's roles
 * are not migrated onto Spatie permissions uniformly, and trusting only one check silently locks
 * out whichever half of the user base the OTHER convention still governs.
 */
class ReceiptVoucherPolicy
{
    use RequiresCompanyModule;

    /** "Not yet posted" -- the only states update()/delete() may mutate the ROW itself (rather
     * than reverse+repost/reverse a live posted document). See InvoiceReceipt::isPending(). */
    private const MUTABLE_STATUSES = [InvoiceReceipt::STATUS_PENDING];

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT, Role::AGENT], true)
            || $user->can('view receipt voucher');
    }

    public function view(User $user, InvoiceReceipt $invoiceReceipt): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        // Agent visibility: an agent may only view a receipt voucher tied to their own branch --
        // matches the pre-existing index() branch scoping (Role::AGENT -> where('branch_id', ...)).
        if ($user->role_id == Role::AGENT) {
            return $user->branch_id !== null && (int) $user->branch_id === (int) $invoiceReceipt->branch_id;
        }

        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->role_id == Role::COMPANY) {
            return true;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::ACCOUNTANT], true)
            || $user->can('create receipt voucher');
    }

    /**
     * Editing the DRAFT row before it is posted. A posted (`approved`) voucher is corrected by
     * reverse()+repost() instead -- ReceiptVoucherController::update() itself refuses to mutate a
     * posted row in place regardless of this gate; this policy method only governs whether the
     * caller may reach update() at all.
     */
    public function update(User $user, InvoiceReceipt $invoiceReceipt): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('update receipt voucher');
    }

    /** pending -> approved (the real posting action). Admin/company/accountant tier only, same as
     * RefundPolicy::approve(). */
    public function approve(User $user, InvoiceReceipt $invoiceReceipt): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if (! in_array($invoiceReceipt->status, self::MUTABLE_STATUSES, true)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('approve receipt voucher');
    }

    /** delete() -- a pending row is dropped outright; an approved row is reverse()'d. Either way
     * this is a financially consequential action, same tier as approve(). */
    public function delete(User $user, InvoiceReceipt $invoiceReceipt): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('delete receipt voucher');
    }

    /**
     * Cheque clear()/bounce() (w5-brief.md §W5.X "reconcile actions require accounting.reconcile
     * permission") -- moving money out of the CHEQUES_IN_HAND float leaf is a reconciliation-
     * adjacent act, gated the same way this wave's brief names for the pre-existing reconcile
     * endpoints.
     */
    public function reconcile(User $user, InvoiceReceipt $invoiceReceipt): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($invoiceReceipt->status !== InvoiceReceipt::STATUS_APPROVED) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('accounting.reconcile');
    }
}
