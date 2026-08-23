<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\WhatsappIngest;
use App\Services\WhatsappPdfIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Matches WhatsApp-ingested PDFs to the tasks app:process-files created for them
 * (by file_name), then attributes + enables + replies. Runs every minute.
 *
 *  - task found, total > 0  → attribute, enable (deterministic → live), confirm
 *  - task found, total <= 0 → Level-2: ask the agent for the price, park
 *  - no task after grace     → duplicate-aware "already loaded" / "saved for review"
 */
class DispatchWhatsappResults extends Command
{
    protected $signature = 'wa:dispatch-results';
    protected $description = 'Match WhatsApp-ingested PDFs to created tasks; attribute, enable, and reply.';

    public function handle(WhatsappPdfIngestService $svc): int
    {
        $rows = WhatsappIngest::where('status', 'received')->orderBy('id')->get();

        foreach ($rows as $row) {
            $task = Task::where('file_name', $row->file_name)->latest('id')->first();

            if (!$task) {
                $this->handleNoTask($row, $svc);
                continue;
            }

            // Level-2: parser produced a task but couldn't read the price.
            if ((float) $task->total <= 0) {
                // No agent to ask (supplier posted in a group) → leave for review.
                if (!$row->agent_id) {
                    $row->update(['status' => 'review', 'task_id' => $task->id,
                        'note' => 'no price and no agent to ask (group post)']);
                    continue;
                }
                Cache::put(
                    'wa_pending_field_' . $row->from_phone,
                    ['task_id' => $task->id, 'field' => 'price', 'agent_id' => $row->agent_id],
                    now()->addMinutes((int) config('wa_pdf_ingest.field_ttl_minutes'))
                );
                $task->agent_id = $row->agent_id;   // attribute now; enable on reply
                $task->save();
                $row->update(['status' => 'awaiting_field', 'task_id' => $task->id]);

                $pax = $task->passenger_name ?? 'this booking';
                $ref = $task->gds_reference ?: $task->reference;
                if ($row->agent) {
                    $svc->reply($row->agent, "Got {$row->supplier_slug} PNR {$ref} for {$pax} — I couldn't read the price. Reply with the amount in KWD.");
                }
                continue;
            }

            // Success: attribute + (deterministic) enable. ProcessAirFiles created it
            // agent-null / disabled; we promote it to live and attributed.
            $task->agent_id = $row->agent_id;
            $task->enabled  = true;
            $task->save();

            $supplier = optional($task->supplier)->name ?? $row->supplier_slug;
            $ref = $task->gds_reference ?: $task->reference;
            $row->update(['status' => 'live', 'task_id' => $task->id, 'pnr' => $ref]);

            if ($row->agent) {
                $svc->reply($row->agent, "✅ Loaded as task #{$task->id} ({$supplier}), assigned to you.");
            }
            Log::info('WaPdfDispatch: live', ['task' => $task->id, 'agent' => $row->agent_id]);
        }

        return self::SUCCESS;
    }

    /**
     * No task matched this file_name. Wait out the grace window, then decide:
     * already-loaded duplicate (reply with the existing task) vs unparseable.
     */
    private function handleNoTask(WhatsappIngest $row, WhatsappPdfIngestService $svc): void
    {
        $age = $row->received_at?->diffInMinutes(now()) ?? 0;
        if ($age < (int) config('wa_pdf_ingest.parse_grace_minutes')) {
            return; // not parsed yet — wait for the next tick
        }

        $dup = $row->pnr
            ? Task::where('company_id', $row->company_id)
                ->where(fn($q) => $q->where('reference', $row->pnr)->orWhere('gds_reference', $row->pnr))
                ->latest('id')->first()
            : null;

        if ($dup) {
            $row->update(['status' => 'duplicate', 'task_id' => $dup->id]);
            if ($row->agent) {
                $svc->reply($row->agent, "ℹ️ That booking is already loaded as task #{$dup->id}.");
            }
            return;
        }

        $row->update(['status' => 'review', 'note' => 'no task after grace window']);
        if ($row->agent) {
            $svc->reply($row->agent, "Couldn't load that PDF — I've saved it for the team to check.");
        }
    }
}
