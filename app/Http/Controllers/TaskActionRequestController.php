<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskActionRequest;
use App\Services\TaskActionRequestNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskActionRequestController extends Controller
{
    /**
     * Landing page — shows request summary + Approve/Deny buttons.
     */
    public function show(string $token)
    {
        $actionRequest = TaskActionRequest::byToken($token)
            ->with(['ownerAgent', 'actorAgent', 'client', 'task', 'originalTask'])
            ->first();

        if (!$actionRequest) {
            return view('task-action-requests.not-found');
        }

        $tickets = Task::whereIn('id', $actionRequest->affectedTaskIds())
            ->select('id', 'ticket_number', 'reference', 'status')
            ->get();

        return view('task-action-requests.show', compact('actionRequest', 'tickets'));
    }

    public function approve(string $token, Request $httpRequest)
    {
        $req = TaskActionRequest::byToken($token)->first();
        if (!$req) {
            return view('task-action-requests.not-found');
        }
        if (!$req->isPending()) {
            return view('task-action-requests.already-decided', ['actionRequest' => $req]);
        }

        // Approve — keep current agent_id (already actor from import time per today's parser fix).
        // Just transition the request to approved.
        $req->approve(
            userId: Auth::id(),
            via: TaskActionRequest::VIA_WEB,
            note: $httpRequest->input('note')
        );

        try {
            app(TaskActionRequestNotifier::class)->notifyOnApprove($req);
        } catch (\Throwable $e) {
            Log::warning('[TaskActionRequest] Approve notify failed: ' . $e->getMessage());
        }

        return view('task-action-requests.thanks', [
            'actionRequest' => $req,
            'decision' => 'approved',
        ]);
    }

    public function deny(string $token, Request $httpRequest)
    {
        $req = TaskActionRequest::byToken($token)->first();
        if (!$req) {
            return view('task-action-requests.not-found');
        }
        if (!$req->isPending()) {
            return view('task-action-requests.already-decided', ['actionRequest' => $req]);
        }

        // Deny — flip agent_id on the affected tasks back to the owner agent.
        $taskIds = $req->affectedTaskIds();
        DB::transaction(function () use ($req, $taskIds, $httpRequest) {
            Task::whereIn('id', $taskIds)->update([
                'agent_id' => $req->owner_agent_id,
                'updated_at' => now(),
            ]);
            $req->deny(
                userId: Auth::id(),
                via: TaskActionRequest::VIA_WEB,
                note: $httpRequest->input('note')
            );
        });

        try {
            app(TaskActionRequestNotifier::class)->notifyOnDeny($req);
        } catch (\Throwable $e) {
            Log::warning('[TaskActionRequest] Deny notify failed: ' . $e->getMessage());
        }

        return view('task-action-requests.thanks', [
            'actionRequest' => $req,
            'decision' => 'denied',
            'reverted_task_ids' => $taskIds,
        ]);
    }
}
