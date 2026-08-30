<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\RequiresCompanyModule;
use App\Support\Modules;

class TaskPolicy
{
    use RequiresCompanyModule;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAny(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('accountant')) {
            return $user->can('view task');
        }

        if($user->hasRole('admin')) return true;

        return $user->can('view task');
    }

    public function viewPrice(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('view task price');
        }

        return $user->can('view task price');
    }

    public function store(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('create task');
        }

        return $user->can('create task');
    }

    public function destroy(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if($user->hasRole('admin')) return true;

        return false;
    }

    /**
     * W6.S item (3) (w6-brief.md "Consolidation + fixes" -- "TaskPolicy: add update, void,
     * reissue, bulkVoid, switchInvoice abilities; Gate::authorize in every mutating action;
     * admin/accountant default + role-config"). Follows this policy's OWN existing convention
     * (viewAny/viewPrice/store above): admin always true; accountant checked against a named
     * Spatie permission so a company can grant/revoke it without a code change; every other role
     * falls back to the same permission check (matching viewAny/viewPrice/store's own
     * "accountant -> can(); else -> can()" shape rather than inventing a new pattern for these
     * five).
     */
    public function update(User $user, ?Task $task = null): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('update task');
        }

        return $user->can('update task');
    }

    /**
     * VOID / VOID-WITH-FEE (w6-brief.md "## Kinds" 1/3). Registered now so W6.V's controller
     * action(s) and the W6.U task-action buttons have a stable ability to Gate::authorize against
     * from day one -- there is no dedicated void HTTP action in this sub-wave yet (today a task's
     * status flips to 'void' only via update()/toggleStatus(), both already gated by update()
     * above); this ability exists for the sub-wave that adds one.
     */
    public function void(User $user, ?Task $task = null): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('void task');
        }

        return $user->can('void task');
    }

    /**
     * REISSUE / EXCHANGE (w6-brief.md "## Kinds" 4). See void()'s own docblock -- registered ahead
     * of W6.R's dedicated reissue action; switchInvoiceTask() (the current thin, unposted
     * approximation of reissue) is gated by switchInvoice() below, not this ability, until
     * switchInvoiceTask() itself becomes the reissue() wrapper per w6-brief.md §4.
     */
    public function reissue(User $user, ?Task $task = null): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('reissue task');
        }

        return $user->can('reissue task');
    }

    /**
     * BULK VOID (w6-brief.md "## Kinds" 5, `POST /tasks/bulk-void`). Registered ahead of W6.B's
     * route -- no route consumes this ability yet in this sub-wave.
     */
    public function bulkVoid(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('bulk void task');
        }

        return $user->can('bulk void task');
    }

    /**
     * switchInvoiceTask() (w6-brief.md "## Kinds" 4 -- "switchInvoiceTask() becomes a thin wrapper
     * over this flow"). Gates the ALREADY-EXISTING `/tasks/{task}/switch-invoice` route, which had
     * zero authorization before this sub-wave (ct-void-map.md §6/§7 bug 2).
     */
    public function switchInvoice(User $user, ?Task $task = null): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('switch invoice task');
        }

        return $user->can('switch invoice task');
    }

    /**
     * W6.U "Follow-up tab" (owner addition, 2026-08-28): "this tab needs a new viewFollowUp/
     * per-record view ability following the same hasRole('admin') -> true, accountant ->
     * can('...'), else scoped-to-own pattern used elsewhere (e.g. AgentPolicy)". Registered here
     * (W6.S) so the ability exists before W6.U's controller/views land; per-record ownership
     * scoping (agent sees only their own tasks) is the CALLER's query-scoping responsibility
     * (matching how viewAny() above is a coarse yes/no and the actual company/agent scoping
     * happens in TaskController::getTasks()'s own $whoIsUser branching) -- this ability answers
     * "can this user see the follow-up tab at all", not "can this user see this one task".
     */
    public function viewFollowUp(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('view task');
        }

        return $user->can('view task');
    }

    /**
     * W6.U "Follow-up tab" (owner addition, 2026-08-28) -- the PER-RECORD half of viewFollowUp()
     * above: "an agent hitting another agent's task row action (issue/extend/cancel) gets 403".
     * Admin/accountant act on every task (matching viewFollowUp()'s own coarse gate); every other
     * role only on a task they themselves own -- $task->agent_id must resolve to their OWN agent
     * row, never resolved by name/company alone.
     */
    public function manageFollowUp(User $user, Task $task): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('view task');
        }

        return $user->agent !== null && (int) $task->agent_id === (int) $user->agent->id;
    }

    /**
     * W6.U "Task actions" -- void-with-fee / reissue-with-fee approval step
     * (w6-brief.md: "an override input + an approval-required flag ... route to an approve step,
     * gated by policy, before posting"). A distinct, stronger ability from void()/reissue()
     * themselves: the requester of an override and the approver of it are not required to be the
     * same person, but both still default to admin/accountant, same shape as every other ability
     * in this policy.
     */
    public function approveFeeOverride(User $user): bool
    {
        if (! $this->moduleEnabled($user, Modules::TASK_UPLOADER)) {
            return false;
        }

        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('accountant')) {
            return $user->can('approve task fee override');
        }

        return $user->can('approve task fee override');
    }
}
