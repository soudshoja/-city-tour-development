<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decides when a refund/void/reissue task imported from an AIR file (or any
 * other source) needs an owner-acknowledgment request to be created.
 *
 * Rules:
 * 1. Task status must be refund, void, or reissued.
 * 2. Task must have an original_task_id (i.e., it's a child action).
 * 3. Actor (task.agent_id) must differ from owner (originalTask.agent_id).
 * 4. If owner agent record is missing or has no user_id → auto-approve.
 * 5. If actor IS in client_agents pivot for the client → notify_only=true
 *    (owner gets info notification; no Approve/Deny needed).
 * 6. Otherwise → status=pending (Approve/Deny required).
 *
 * Bundling: when multiple tasks arrive in the same processing window for the
 * same (client, action, actor) — common with multi-passenger AIR files —
 * we attach subsequent task ids to bundled_task_ids on the existing request
 * instead of creating new rows. The window is BUNDLE_MINUTES.
 */
class TaskActionRequestService
{
    /** Minutes within which a new task is considered part of an existing pending request. */
    public const BUNDLE_MINUTES = 30;

    /** Map Task::status to action_type enum on the request table. */
    private const STATUS_TO_ACTION = [
        'refund' => TaskActionRequest::ACTION_REFUND,
        'void' => TaskActionRequest::ACTION_VOID,
        'reissued' => TaskActionRequest::ACTION_REISSUE,
    ];

    /**
     * Evaluate a freshly-saved task and create/extend a TaskActionRequest if applicable.
     * Returns the request row created or extended, or null if no request was needed.
     */
    public function maybeCreateRequestFor(Task $task): ?TaskActionRequest
    {
        // Kill-switch: cross-agent acknowledgment is disabled on citycomm
        // (back-office staff process refunds/voids/reissues, so it mis-fires).
        if (!config('task_action_requests.enabled', true)) {
            return null;
        }

        // Gate 1: status must be a child action
        if (!array_key_exists($task->status, self::STATUS_TO_ACTION)) {
            return null;
        }
        $actionType = self::STATUS_TO_ACTION[$task->status];

        // Gate 2: must have a parent task
        if (empty($task->original_task_id)) {
            return null;
        }

        $originalTask = Task::find($task->original_task_id);
        if (!$originalTask) {
            return null;
        }

        $actorAgentId = $task->agent_id;

        // Owner is the CLIENT OWNER (not the original task issuer). The original
        // task's agent might be an Assigned Agent who serviced the booking on
        // the client owner's behalf. The acknowledgment workflow is about who
        // owns the client relationship, so resolve from clients.agent_id first.
        $clientId = $task->client_id ?? $originalTask->client_id;
        $ownerAgentId = null;
        if ($clientId) {
            $ownerAgentId = \DB::table('clients')->where('id', $clientId)->value('agent_id');
        }
        if (!$ownerAgentId) {
            $ownerAgentId = $originalTask->agent_id;
        }

        // Gate 3: same agent — nothing to acknowledge
        if (!$actorAgentId || !$ownerAgentId || $actorAgentId === $ownerAgentId) {
            return null;
        }

        // Owner inactive (record gone or has no user_id) → auto-approve
        $ownerAgent = \App\Models\Agent::find($ownerAgentId);
        $ownerInactive = !$ownerAgent || empty($ownerAgent->user_id);

        // notify-only when actor is already an Assigned Agent of this client
        $notifyOnly = false;
        if ($clientId) {
            $notifyOnly = DB::table('client_agents')
                ->where('client_id', $clientId)
                ->where('agent_id', $actorAgentId)
                ->exists();
        }

        // Try to bundle into an existing pending request from the same processing window
        $existing = TaskActionRequest::query()
            ->where('client_id', $clientId)
            ->where('actor_agent_id', $actorAgentId)
            ->where('owner_agent_id', $ownerAgentId)
            ->where('action_type', $actionType)
            ->where('status', $ownerInactive ? TaskActionRequest::STATUS_AUTO_APPROVED : TaskActionRequest::STATUS_PENDING)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::BUNDLE_MINUTES))
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            // Append this task to the bundle (skip if already there).
            $bundled = $existing->bundled_task_ids ?? [];
            if ($existing->task_id !== $task->id && !in_array($task->id, $bundled, true)) {
                $bundled[] = $task->id;
                $existing->bundled_task_ids = array_values(array_unique(array_map('intval', $bundled)));
                $existing->save();
                Log::info('[TaskActionRequest] Bundled task into existing request', [
                    'request_id' => $existing->id,
                    'task_id' => $task->id,
                    'bundled_count' => count($existing->bundled_task_ids),
                ]);
            }
            return $existing;
        }

        // Create new request
        $status = $ownerInactive
            ? TaskActionRequest::STATUS_AUTO_APPROVED
            : TaskActionRequest::STATUS_PENDING;

        $request = TaskActionRequest::create([
            'request_token' => TaskActionRequest::generateToken(),
            'task_id' => $task->id,
            'original_task_id' => $task->original_task_id,
            'client_id' => $clientId,
            'owner_agent_id' => $ownerAgentId,
            'actor_agent_id' => $actorAgentId,
            'action_type' => $actionType,
            'bundled_task_ids' => [],
            'notify_only' => $notifyOnly,
            'status' => $status,
            'processed_at' => $ownerInactive ? now() : null,
            'processed_via' => $ownerInactive ? TaskActionRequest::VIA_CRON : null,
            'process_note' => $ownerInactive ? 'Auto-approved: owner agent inactive or has no user account.' : null,
        ]);

        Log::info('[TaskActionRequest] Created request', [
            'request_id' => $request->id,
            'token' => $request->request_token,
            'task_id' => $task->id,
            'action_type' => $actionType,
            'owner_agent_id' => $ownerAgentId,
            'actor_agent_id' => $actorAgentId,
            'status' => $status,
            'notify_only' => $notifyOnly,
        ]);

        // Fire notifications only on NEW request creation (not on bundling).
        try {
            app(TaskActionRequestNotifier::class)->notifyOnCreation($request);
        } catch (\Throwable $e) {
            Log::warning('[TaskActionRequest] Notifier failed on creation: ' . $e->getMessage());
        }

        return $request;
    }
}
