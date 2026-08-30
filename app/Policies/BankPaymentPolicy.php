<?php

namespace App\Policies;

use App\Models\BankPayment;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * W5.P / W5.X (w5-brief.md §W5.X "Policies: ReceiptVoucherPolicy / BankPaymentPolicy full
 * abilities (viewAny/view/create/update/approve/delete) enforced on every route; reconcile
 * actions require accounting.reconcile permission"). Bound explicitly in AppServiceProvider,
 * mirroring ReceiptVoucherPolicy's own registration (even though Laravel's convention-based policy
 * discovery WOULD find this one on its own -- {@see BankPayment} is literally named "BankPayment" --
 * explicit registration keeps the convention consistent across both voucher policies rather than
 * leaving one implicit and one explicit).
 *
 * Same dual-check convention every other policy in this codebase uses (RefundPolicy/InvoicePolicy/
 * ReceiptVoucherPolicy): Spatie `$user->can(...)` OR the legacy integer `role_id`, never only one.
 */
class BankPaymentPolicy
{
    use RequiresCompanyModule;

    /** "Not yet posted" -- the only state update()/delete() may mutate the ROW itself (rather than
     * reverse+repost/reverse a live posted document). See BankPayment::isPending(). */
    private const MUTABLE_STATUSES = [BankPayment::STATUS_PENDING];

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('view bank payment');
    }

    public function view(User $user, BankPayment $bankPayment): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($user->role_id == Role::AGENT) {
            return $user->branch_id !== null && (int) $user->branch_id === (int) $bankPayment->branch_id;
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
            || $user->can('create bank payment');
    }

    /**
     * Editing the DRAFT row before it is posted. A posted (`approved`) voucher is corrected by
     * reverse()+repost() instead -- BankPaymentController::update() itself refuses to mutate a
     * posted row in place regardless of this gate; this policy method only governs whether the
     * caller may reach update() at all.
     */
    public function update(User $user, BankPayment $bankPayment): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('update bank payment');
    }

    /** pending -> approved (the real posting action). Admin/company/accountant tier only, same as
     * ReceiptVoucherPolicy::approve(). */
    public function approve(User $user, BankPayment $bankPayment): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if (! in_array($bankPayment->status, self::MUTABLE_STATUSES, true)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('approve bank payment');
    }

    /** delete() -- a pending row is dropped outright; an approved row is reverse()'d. Either way
     * this is a financially consequential action, same tier as approve(). */
    public function delete(User $user, BankPayment $bankPayment): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('delete bank payment');
    }

    /**
     * Cheque clear() (w5-brief.md §W5.X "reconcile actions require accounting.reconcile
     * permission") -- moving a cheque out of CHEQUES_ISSUED_NOT_CLEARED is a reconciliation-
     * adjacent act, gated the same way this wave's brief names for the pre-existing reconcile
     * endpoints (mirrors ReceiptVoucherPolicy::reconcile()).
     */
    public function reconcile(User $user, BankPayment $bankPayment): bool
    {
        if (! $this->moduleEnabled($user, Modules::ACCOUNTING)) {
            return false;
        }

        if ($bankPayment->status !== BankPayment::STATUS_APPROVED) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('accounting.reconcile');
    }
}
