<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

/**
 * W4.R bundled fix (w4-brief.md §5 "RefundPolicy full abilities
 * (viewAny/view/create/update/approve/complete/delete); enforce them in every RefundController
 * mutating action" — ct-refund-map.md §6: before this fix, only viewAny() existed and
 * store/update/completeProcess were unguarded entirely).
 *
 * Company-scope check first everywhere (RequiresCompanyModule), same convention as
 * InvoicePolicy/RefundClientPolicy. Role checks follow InvoicePolicy's own dual-check
 * convention (Spatie hasRole() OR the legacy integer role_id) rather than trusting only one.
 *
 * Status-workflow abilities (approve/complete) additionally check the Refund's OWN status so a
 * stale/already-actioned document cannot be re-approved or re-completed through a raced double
 * click — see w4-brief.md §4 "Statuses: draft -> approved -> posted -> completed | rejected".
 */
class RefundPolicy
{
    use RequiresCompanyModule;

    /**
     * "Not yet finalized" in EITHER status vocabulary: the new workflow's 'draft', and the
     * pre-existing legacy statuses store()/handleUnpaidInvoice()/handlePartialRefund() still write
     * on the OFF path ('pending'/'processed') — never the terminal ones ('completed'/'rejected'/
     * 'declined'). Kept deliberately permissive of the legacy values so this bundled fix (adding
     * authorization that never existed before) does not ALSO regress the pre-existing OFF-path
     * edit-before-complete flow, which this wave does not otherwise touch.
     */
    private const MUTABLE_STATUSES = [Refund::STATUS_DRAFT, 'pending', 'processed'];

    /** "Ready to be driven forward to posted/completed" in either vocabulary. */
    private const COMPLETABLE_STATUSES = [Refund::STATUS_APPROVED, Refund::STATUS_POSTED, 'processed'];

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        return $user->can('view refund');
    }

    public function view(User $user, Refund $refund): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        // w4-brief.md §4 "Agent visibility: refund list filterable by agent; agent sees own
        // refunds only (AgentPolicy pattern)".
        if ($user->role_id == Role::AGENT) {
            return $user->agent && (int) $user->agent->id === (int) $refund->agent_id;
        }

        return $user->can('view refund');
    }

    public function create(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if ($user->role_id == Role::COMPANY) {
            return true;
        }

        return $user->can('create refund');
    }

    /**
     * Editing a DRAFT refund's header/lines before it is approved. Never allowed once the
     * document has left draft — a posted/completed/rejected refund is corrected by reversal
     * (a new refund/CRN), never by mutating the original in place, same immutability rule the
     * posting engine enforces on every other document.
     */
    public function update(User $user, Refund $refund): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if (! in_array($refund->status, self::MUTABLE_STATUSES, true)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT
            || $user->can('update refund');
    }

    /**
     * draft -> approved. Admin/company/accountant only — the same staff tier InvoicePolicy's
     * editAfterIssue()/editDates() reserve for financially consequential actions.
     */
    public function approve(User $user, Refund $refund): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if (! in_array($refund->status, self::MUTABLE_STATUSES, true)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT;
    }

    /** draft -> rejected (void draft). Same tier as approve(). */
    public function reject(User $user, Refund $refund): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if (! in_array($refund->status, self::MUTABLE_STATUSES, true)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT;
    }

    /**
     * approved -> posted (the actual ledger posting via RefundPostingService) and
     * posted -> completed (disposition settles / gateway refund completes). Named `complete` to
     * match RefundController::completeProcess()'s existing method name and RefundClientPolicy's
     * own `complete()` ability naming convention — covers BOTH the posted and completed
     * transitions since RefundController::completeProcess() is the single existing entry point
     * for driving a refund forward past 'approved'.
     */
    public function complete(User $user, Refund $refund): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if (! in_array($refund->status, self::COMPLETABLE_STATUSES, true)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('accountant')
            || $user->role_id == Role::ADMIN
            || $user->role_id == Role::COMPANY
            || $user->role_id == Role::ACCOUNTANT;
    }

    /**
     * Never a hard delete of a posted/completed document (the posting engine's own immutability
     * rule — correction is reversal, not deletion). Only a DRAFT (never posted) refund may be
     * deleted, and only by admin/company.
     */
    public function delete(User $user, Refund $refund): bool
    {
        if (! $this->moduleEnabled($user, Modules::PAYMENT_GATEWAY)) {
            return false;
        }

        if ($refund->status !== Refund::STATUS_DRAFT) {
            return false;
        }

        return $user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY;
    }
}
