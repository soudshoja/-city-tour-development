<?php

namespace App\Services;

use App\Http\Controllers\ResayilController;
use App\Http\Traits\NotificationTrait;
use App\Models\TaskActionRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Fires the in-app + email + WhatsApp notifications for a TaskActionRequest
 * lifecycle event: created, escalated (2-day reminder), approved, denied,
 * auto-approved.
 *
 * All notification text comes from resources/lang/en/task_action_requests.php.
 */
class TaskActionRequestNotifier
{
    use NotificationTrait;

    /**
     * Fire notifications for a freshly-created request (or use an existing one
     * after bundling). Idempotent — if the request status is auto_approved,
     * notify admin only. If notify_only, fire info-only notification to owner.
     * Otherwise fire approve/deny notification to owner.
     */
    public function notifyOnCreation(TaskActionRequest $req): void
    {
        $req->loadMissing(['ownerAgent', 'actorAgent', 'client', 'task']);

        if ($req->status === TaskActionRequest::STATUS_AUTO_APPROVED) {
            $this->notifyAdminAutoApprove($req);
            return;
        }

        if ($req->notify_only) {
            $this->notifyOwnerInfoOnly($req);
            return;
        }

        $this->notifyOwnerDecisionRequired($req);
    }

    public function notifyEscalation(TaskActionRequest $req): void
    {
        $req->loadMissing(['ownerAgent', 'actorAgent', 'client']);
        $vars = $this->messageVars($req, ['submitted_at' => $req->created_at?->format('Y-m-d H:i')]);

        $tplKey = $this->isBundled($req) ? 'escalation_body_bundled' : 'escalation_body';
        $title = $this->trans('escalation_title', $vars);
        $body  = $this->trans($tplKey, $vars);

        foreach ($this->adminAndAccountantUsers($req) as $user) {
            $this->fireInApp($user->id, $title, $body, 'task_action_request', [
                'request_id' => $req->id, 'request_token' => $req->request_token,
            ]);
            $this->fireEmail($user->email, $title, $body);
        }
        Log::info('[TaskActionRequest] Escalation notified', ['request_id' => $req->id]);
    }

    public function notifyOnApprove(TaskActionRequest $req): void
    {
        $req->loadMissing(['ownerAgent', 'actorAgent', 'client']);
        $vars = $this->messageVars($req);
        $bundled = $this->isBundled($req);

        // Actor — "your action was approved" (in-app + email + WhatsApp)
        $actorUserId = $req->actorAgent?->user_id;
        if ($actorUserId) {
            $aTitle = $this->trans('approve_notify_actor_title', $vars);
            $aBody  = $this->trans($bundled ? 'approve_notify_actor_body_bundled' : 'approve_notify_actor_body', $vars);
            $aWa    = $this->trans($bundled ? 'approve_notify_actor_whatsapp_bundled' : 'approve_notify_actor_whatsapp', $vars);
            $this->fireInApp($actorUserId, $aTitle, $aBody, 'task_action_request', ['request_id' => $req->id]);
            $email = $req->actorAgent->email ?? optional(User::find($actorUserId))->email;
            $this->fireEmail($email, $aTitle, $aBody);
            $this->fireWhatsApp($req->actorAgent, $aWa);
        }

        // Admin + accountant — audit visibility (both approve AND deny per spec)
        $adminTitle = $this->trans('approve_notify_admin_title', $vars);
        $adminBody  = $this->trans($bundled ? 'approve_notify_admin_body_bundled' : 'approve_notify_admin_body', $vars);
        foreach ($this->adminAndAccountantUsers($req) as $user) {
            $this->fireInApp($user->id, $adminTitle, $adminBody, 'task_action_request', ['request_id' => $req->id]);
            $this->fireEmail($user->email, $adminTitle, $adminBody);
        }
    }

    public function notifyOnDeny(TaskActionRequest $req): void
    {
        $req->loadMissing(['ownerAgent', 'actorAgent', 'client']);
        $vars = $this->messageVars($req);
        $bundled = $this->isBundled($req);

        // Actor — in-app + email + WhatsApp
        $actorUserId = $req->actorAgent?->user_id;
        if ($actorUserId) {
            $aTitle = $this->trans('deny_notify_actor_title', $vars);
            $aBody  = $this->trans($bundled ? 'deny_notify_actor_body_bundled' : 'deny_notify_actor_body', $vars);
            $aWa    = $this->trans($bundled ? 'deny_notify_actor_whatsapp_bundled' : 'deny_notify_actor_whatsapp', $vars);
            $this->fireInApp($actorUserId, $aTitle, $aBody, 'task_action_request', ['request_id' => $req->id]);
            $email = $req->actorAgent->email ?? optional(User::find($actorUserId))->email;
            $this->fireEmail($email, $aTitle, $aBody);
            $this->fireWhatsApp($req->actorAgent, $aWa);
        }

        // Admin + accountant
        $adminTitle = $this->trans('deny_notify_admin_title', $vars);
        $adminBody  = $this->trans($bundled ? 'deny_notify_admin_body_bundled' : 'deny_notify_admin_body', $vars);
        foreach ($this->adminAndAccountantUsers($req) as $user) {
            $this->fireInApp($user->id, $adminTitle, $adminBody, 'task_action_request', ['request_id' => $req->id]);
            $this->fireEmail($user->email, $adminTitle, $adminBody);
        }
    }

    // -------------------- private helpers --------------------

    private function notifyOwnerInfoOnly(TaskActionRequest $req): void
    {
        $vars = $this->messageVars($req);
        $bundled = $this->isBundled($req);
        $title = $this->trans('owner_notify_only_title', $vars);
        $body  = $this->trans($bundled ? 'owner_notify_only_body_bundled' : 'owner_notify_only_body', $vars);
        $waBody = $this->trans($bundled ? 'owner_notify_only_whatsapp_bundled' : 'owner_notify_only_whatsapp', $vars);

        $ownerUserId = $req->ownerAgent?->user_id;
        if ($ownerUserId) {
            $this->fireInApp($ownerUserId, $title, $body, 'task_action_request', [
                'request_id' => $req->id, 'request_token' => $req->request_token,
            ]);
            $email = $req->ownerAgent->email ?? optional(User::find($ownerUserId))->email;
            $this->fireEmail($email, $title, $body);
        }
        $this->fireWhatsApp($req->ownerAgent, $waBody);

        // Actor — courtesy "owner has been notified" info message. Actor knows
        // they performed the action, so this is just acknowledgment that the
        // client owner has been informed (no decision required from anyone).
        $actorUserId = $req->actorAgent?->user_id;
        if ($actorUserId) {
            $aTitle = $this->trans('actor_notify_only_title', $vars);
            $aBody  = $this->trans($bundled ? 'actor_notify_only_body_bundled' : 'actor_notify_only_body', $vars);
            $aWa    = $this->trans($bundled ? 'actor_notify_only_whatsapp_bundled' : 'actor_notify_only_whatsapp', $vars);
            $this->fireInApp($actorUserId, $aTitle, $aBody, 'task_action_request', [
                'request_id' => $req->id, 'request_token' => $req->request_token,
            ]);
            $email = $req->actorAgent->email ?? optional(User::find($actorUserId))->email;
            $this->fireEmail($email, $aTitle, $aBody);
            $this->fireWhatsApp($req->actorAgent, $aWa);
        }
    }

    private function notifyOwnerDecisionRequired(TaskActionRequest $req): void
    {
        $vars = $this->messageVars($req, [
            'decision_link' => $this->decisionLink($req),
        ]);
        $bundled = $this->isBundled($req);
        $title = $this->trans($bundled ? 'owner_decision_title_bundled' : 'owner_decision_title', $vars);
        $body  = $this->trans($bundled ? 'owner_decision_body_bundled' : 'owner_decision_body', $vars);
        $waBody = $this->trans($bundled ? 'owner_decision_whatsapp_bundled' : 'owner_decision_whatsapp', $vars);

        $ownerUserId = $req->ownerAgent?->user_id;
        if ($ownerUserId) {
            $this->fireInApp($ownerUserId, $title, $body, 'task_action_request', [
                'request_id' => $req->id,
                'request_token' => $req->request_token,
                'actions' => [
                    'approve_url' => $this->approveUrl($req),
                    'deny_url' => $this->denyUrl($req),
                ],
            ]);
            $email = $req->ownerAgent->email ?? optional(User::find($ownerUserId))->email;
            $emailBody = $body . "\n\nApprove: " . $this->approveUrl($req)
                       . "\nDeny: " . $this->denyUrl($req);
            $this->fireEmail($email, $title, $emailBody);
        }
        $this->fireWhatsApp($req->ownerAgent, $waBody);
    }

    private function notifyAdminAutoApprove(TaskActionRequest $req): void
    {
        $vars = $this->messageVars($req);
        $title = $this->trans('auto_approve_admin_title', $vars);
        $body  = $this->trans('auto_approve_admin_body', $vars);

        foreach ($this->adminAndAccountantUsers($req) as $user) {
            $this->fireInApp($user->id, $title, $body, 'task_action_request', ['request_id' => $req->id]);
            $this->fireEmail($user->email, $title, $body);
        }
    }

    private function isBundled(TaskActionRequest $req): bool
    {
        return is_array($req->bundled_task_ids) && count($req->bundled_task_ids) > 0;
    }

    private function messageVars(TaskActionRequest $req, array $extra = []): array
    {
        $actionLabel = trans("task_action_requests.action_label.{$req->action_type}");
        $tickets = collect($this->ticketNumbers($req))->filter()->values();

        return array_merge([
            'actor' => $req->actorAgent->name ?? '(unknown actor)',
            'owner' => $req->ownerAgent->name ?? '(unknown owner)',
            'ticket' => optional($req->task)->ticket_number ?? optional($req->task)->reference ?? '-',
            'client' => $req->client->full_name ?? optional($req->client)->name ?? '-',
            'action' => $actionLabel,
            'tickets' => $tickets->implode(', '),
            'tickets_count' => $tickets->count(),
        ], $extra);
    }

    private function ticketNumbers(TaskActionRequest $req): array
    {
        $ids = $req->affectedTaskIds();
        return \App\Models\Task::whereIn('id', $ids)->pluck('ticket_number', 'id')->toArray();
    }

    private function trans(string $key, array $vars): string
    {
        return trans("task_action_requests.{$key}", $vars);
    }

    private function decisionLink(TaskActionRequest $req): string
    {
        return URL::route('task-action-request.show', ['token' => $req->request_token]);
    }

    private function approveUrl(TaskActionRequest $req): string
    {
        return URL::route('task-action-request.approve', ['token' => $req->request_token]);
    }

    private function denyUrl(TaskActionRequest $req): string
    {
        return URL::route('task-action-request.deny', ['token' => $req->request_token]);
    }

    private function fireInApp(int $userId, string $title, string $message, string $type, array $payload = []): void
    {
        try {
            $this->storeNotification([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TaskActionRequest] in-app notification failed: ' . $e->getMessage());
        }
    }

    private function fireEmail(?string $to, string $title, string $body): void
    {
        if (empty($to)) return;
        try {
            Mail::raw($body, function ($m) use ($to, $title) {
                $m->to($to)->subject($title);
            });
        } catch (\Throwable $e) {
            Log::warning("[TaskActionRequest] email to {$to} failed: " . $e->getMessage());
        }
    }

    private function fireWhatsApp(?Agent $agent, string $message): void
    {
        if (!$agent || empty($agent->phone_number)) return;
        try {
            // In PRODUCTION we explicitly bypass the dummy-number redirect: the
            // owner agent needs to actually receive the request on their phone
            // for the inbound 1/Approve, 2/Deny reply path to work.
            //
            // In non-production envs (dev1, local, staging) we MUST honor the
            // dummy-number safety — otherwise cloned databases with real agent
            // phone numbers spam real WhatsApps from every TAR created during
            // PDF re-processing. Pre-2026-05-26 this was unconditionally `false`
            // and dev1 was paging real agents.
            $isDummyNumber = app()->environment() !== 'production';

            (new ResayilController())->message(
                $agent->phone_number,
                $agent->country_code ?? '+965',
                $message,
                null,
                null,
                null,
                $isDummyNumber
            );
        } catch (\Throwable $e) {
            Log::warning('[TaskActionRequest] WhatsApp send failed: ' . $e->getMessage());
        }
    }

    /**
     * Find admin + accountant users for the company associated with the request.
     */
    private function adminAndAccountantUsers(TaskActionRequest $req): \Illuminate\Support\Collection
    {
        $task = $req->originalTask ?? $req->task;
        $companyId = $task->company_id ?? null;

        $roles = [Role::ADMIN, Role::ACCOUNTANT];
        $q = User::whereIn('role_id', $roles)->whereNotNull('email');
        // Scope to same company when possible
        if ($companyId && \Illuminate\Support\Facades\Schema::hasColumn('users', 'company_id')) {
            $q->where(function ($x) use ($companyId) {
                $x->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }
        return $q->get();
    }
}
