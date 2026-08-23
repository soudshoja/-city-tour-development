<?php

namespace App\Jobs;

use App\Http\Controllers\ResayilController;
use App\Models\Agent;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendKwikt2843CreatorReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $taskId)
    {
    }

    public function handle(): void
    {
        $task = Task::find($this->taskId);
        if (!$task) {
            return;
        }

        // Filter: only KWIKT2843 + issued/reissued + has agent + uninvoiced
        if ($task->created_by !== 'KWIKT2843') return;
        if (!in_array($task->status, ['issued', 'reissued'])) return;
        if (!$task->agent_id) return;
        if (empty($task->gds_reference)) return;
        if (\App\Models\InvoiceDetail::where('task_id', $task->id)->exists()) return;

        $agent = Agent::find($task->agent_id);
        if (!$agent || empty($agent->phone_number)) {
            Log::warning('[Kwikt2843CreatorJob] No phone for agent', [
                'task_id' => $task->id, 'agent_id' => $task->agent_id,
            ]);
            return;
        }

        // Race-safe dedup: claim the PNR slot BEFORE sending. The unique index on
        // pnr_reminders(company_id, reference, type) makes this atomic — when
        // multiple tickets for one PNR fire jobs at the same time, only one wins
        // the insertOrIgnore. Losers exit silently. If WhatsApp send fails after
        // we win, we delete the row so the next ticket creation for the same PNR
        // gets another shot. gds_reference is the real GDS PNR (tasks.reference
        // is the ticket number — multiple tickets share one PNR).
        $inserted = DB::table('pnr_reminders')->insertOrIgnore([
            'company_id' => $task->company_id,
            'reference' => $task->gds_reference,
            'type' => 'kwikt2843_creator',
            'agent_id' => $agent->id,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 0) return;

        $locale = strtolower($agent->language ?? '') === 'ar' ? 'ar' : 'en';
        $firstName = $agent->first_name ?: ($agent->name ?: 'agent');
        $message = trans('kwikt2843_creator_reminder.whatsapp', [
            'name' => $firstName,
            'reference' => $task->gds_reference,
        ], $locale);

        $resayil = new ResayilController();
        $response = $resayil->message(
            phone: $agent->phone_number,
            country_code: $agent->country_code ?? '',
            message: $message,
            isDummyNumber: false,
        );

        if (!($response['success'] ?? false)) {
            DB::table('pnr_reminders')
                ->where('company_id', $task->company_id)
                ->where('reference', $task->gds_reference)
                ->where('type', 'kwikt2843_creator')
                ->delete();
            Log::error('[Kwikt2843CreatorJob] WhatsApp failed — reservation rolled back', [
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'response' => $response,
            ]);
            return;
        }

        Log::info('[Kwikt2843CreatorJob] Sent', [
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'reference' => $task->reference,
        ]);
    }
}
