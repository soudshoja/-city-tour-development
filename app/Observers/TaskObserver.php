<?php

namespace App\Observers;

use App\Jobs\SendKwikt2843CreatorReminderJob;
use App\Models\Agent;
use App\Models\Task;

class TaskObserver
{
    /**
     * Fire before a task is inserted. If the task has an agent but NO client,
     * and that agent has an "auto-assign client" configured, stamp it onto the
     * task. This makes non-Amadeus tasks (which arrive clientless from AIR/PDF/
     * email/WhatsApp imports) auto-bill eligible. Never overwrites an existing
     * client. Runs on `creating` so client_id is set on the row itself — no
     * second write, covers every creation path.
     */
    public function creating(Task $task): void
    {
        if (!empty($task->client_id) || empty($task->agent_id)) {
            return;
        }

        try {
            $cid = optional(Agent::find($task->agent_id))->auto_assign_client_id;
            if ($cid) {
                $task->client_id = $cid;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[TaskObserver] auto-assign client failed', [
                'agent_id' => $task->agent_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fire when a task is created. Runs the KWIKT2843-creator reminder
     * INLINE (no queue) so the WhatsApp goes out during the webhook
     * request itself — independent of queue-worker availability.
     *
     * The job's handle() is race-safe (insertOrIgnore against
     * pnr_reminders unique index) and exits silently on send failure
     * after rolling back the reservation, so this won't break the
     * webhook even if Resayil is slow or down.
     */
    public function created(Task $task): void
    {
        $this->propagatePnrToSiblings($task);
        try {
            app(\App\Services\PriceRequestService::class)->enqueueForTask($task);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[TaskObserver] price-request enqueue failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }

        if ($task->created_by !== 'KWIKT2843') {
            return;
        }

        try {
            (new SendKwikt2843CreatorReminderJob($task->id))->handle();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[TaskObserver] Inline KWIKT2843 reminder failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Safety-net: keep gds_reference (PNR) consistent across a ticket's tasks.
     * Amadeus refund AIR records carry no record locator, so a refund that loads
     * before its issue is saved without a PNR (invisible to PNR search). Propagate
     * the PNR by ticket number in BOTH directions using raw DB updates (no model
     * events => no observer recursion).
     */
    private function propagatePnrToSiblings(Task $task): void
    {
        try {
            $pnr = $task->getRawOriginal('gds_reference') ?: $task->gds_reference;
            $ticket = $task->reference ?: $task->original_reference;
            if (!$ticket) { return; }
            $status = $task->status;

            if (in_array($status, ['issued', 'reissued'], true) && !empty($pnr)) {
                \Illuminate\Support\Facades\DB::table('tasks')
                    ->where('company_id', $task->company_id)
                    ->where('supplier_id', $task->supplier_id)
                    ->whereIn('status', ['refund', 'void'])
                    ->where(function ($q) use ($ticket) { $q->where('reference', $ticket)->orWhere('original_reference', $ticket); })
                    ->where(function ($q) { $q->whereNull('gds_reference')->orWhere('gds_reference', ''); })
                    ->update(['gds_reference' => $pnr]);
            } elseif (in_array($status, ['refund', 'void'], true) && empty($pnr)) {
                $src = \Illuminate\Support\Facades\DB::table('tasks')
                    ->where('company_id', $task->company_id)
                    ->where('supplier_id', $task->supplier_id)
                    ->whereIn('status', ['issued', 'reissued', 'void'])
                    ->where(function ($q) use ($ticket) { $q->where('reference', $ticket)->orWhere('original_reference', $ticket); })
                    ->whereNotNull('gds_reference')->where('gds_reference', '!=', '')
                    ->value('gds_reference');
                if ($src) {
                    \Illuminate\Support\Facades\DB::table('tasks')->where('id', $task->id)->update(['gds_reference' => $src]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[TaskObserver] PNR propagation failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }
}
