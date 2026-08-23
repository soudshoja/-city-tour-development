<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Task;
use App\Models\TaskActionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Parses inbound WhatsApp text replies and applies Approve/Deny on the
 * sender's most recent pending TaskActionRequest.
 *
 * Per spec: most-recent-pending wins. If multiple pending requests exist,
 * we still apply to the most recent and the confirmation message tells
 * the user the rest are still waiting.
 *
 * Recognized replies (case-insensitive, leading/trailing whitespace stripped):
 *   "1", "approve", "ok", "yes"  → APPROVE
 *   "2", "deny", "no", "reject"  → DENY
 *
 * Anything else → returns the invalid-reply confirmation string.
 *
 * Returns null if the body wasn't an Approve/Deny intent (so the webhook
 * can pass through to other handlers like the image-uploader path).
 */
class TaskActionRequestWhatsAppHandler
{
    private const APPROVE_TOKENS = ['1', 'approve', 'ok', 'yes', 'a'];
    private const DENY_TOKENS = ['2', 'deny', 'no', 'reject', 'd'];

    public function handle(?string $phone, ?string $body): ?string
    {
        if (!$phone || !$body) return null;

        $intent = $this->classify($body);
        if (!$intent) return null;

        $agent = $this->findAgentByPhone($phone);
        if (!$agent || !$agent->user_id) {
            // Unknown number — silent passthrough (don't leak error info).
            return null;
        }

        $req = TaskActionRequest::mostRecentPendingForUser($agent->user_id)->first();
        if (!$req) {
            return trans('task_action_requests.whatsapp_no_pending');
        }

        // Apply
        if ($intent === 'approve') {
            $req->approve(userId: $agent->user_id, via: TaskActionRequest::VIA_WHATSAPP);
            try {
                app(TaskActionRequestNotifier::class)->notifyOnApprove($req);
            } catch (\Throwable $e) {
                Log::warning('[TaskActionRequest WA] approve notify failed: ' . $e->getMessage());
            }

            return trans('task_action_requests.whatsapp_approved', $this->confirmationVars($req));
        }

        // deny
        $taskIds = $req->affectedTaskIds();
        DB::transaction(function () use ($req, $taskIds, $agent) {
            Task::whereIn('id', $taskIds)->update([
                'agent_id' => $req->owner_agent_id,
                'updated_at' => now(),
            ]);
            $req->deny(userId: $agent->user_id, via: TaskActionRequest::VIA_WHATSAPP);
        });
        try {
            app(TaskActionRequestNotifier::class)->notifyOnDeny($req);
        } catch (\Throwable $e) {
            Log::warning('[TaskActionRequest WA] deny notify failed: ' . $e->getMessage());
        }

        return trans('task_action_requests.whatsapp_denied', $this->confirmationVars($req));
    }

    private function classify(string $body): ?string
    {
        $tok = strtolower(trim($body));
        // Just take first whitespace-separated token to ignore trailing chatter
        $tok = preg_split('/\s+/', $tok, 2)[0] ?? $tok;
        if (in_array($tok, self::APPROVE_TOKENS, true)) return 'approve';
        if (in_array($tok, self::DENY_TOKENS, true)) return 'deny';
        return null;
    }

    private function findAgentByPhone(string $phone): ?Agent
    {
        // Strip leading + and any country code if present; match on the trailing digits.
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) return null;

        // Try exact match first
        $agent = Agent::where('phone_number', $digits)
            ->orWhere('phone_number', '+' . $digits)
            ->first();
        if ($agent) return $agent;

        // Match on the last 8 digits — handles cases where the stored phone number
        // includes the country code as a separate column.
        $tail = substr($digits, -8);
        return Agent::where('phone_number', 'like', '%' . $tail)->first();
    }

    private function confirmationVars(TaskActionRequest $req): array
    {
        $req->loadMissing(['actorAgent', 'ownerAgent', 'client', 'task']);
        $actionLabel = trans("task_action_requests.action_label.{$req->action_type}");
        return [
            'actor' => $req->actorAgent->name ?? '-',
            'owner' => $req->ownerAgent->name ?? '-',
            'client' => $req->client->full_name ?? optional($req->client)->name ?? '-',
            'ticket' => optional($req->task)->ticket_number ?? optional($req->task)->reference ?? '-',
            'action' => $actionLabel,
        ];
    }
}
