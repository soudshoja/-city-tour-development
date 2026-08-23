<?php

namespace App\Console\Commands;

use App\Models\TaskActionRequest;
use App\Services\TaskActionRequestNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Once-per-firing reminder for pending TaskActionRequests older than 2 days.
 * Notifies admin + accountant so they can decide on the owner's behalf.
 *
 * Each request is escalated at most once: we set escalated_at on the row,
 * and skip rows where it's already populated.
 */
class NotifyStaleTaskActionRequests extends Command
{
    protected $signature = 'task-action-request:notify-stale {--dry-run : Show eligible without sending}';
    protected $description = 'Notify admin/accountant about TaskActionRequests pending >2 days (once per request)';

    public const STALE_AFTER_HOURS = 48;

    public function handle(): int
    {
        $cutoff = Carbon::now()->subHours(self::STALE_AFTER_HOURS);
        $rows = TaskActionRequest::query()
            ->where('status', TaskActionRequest::STATUS_PENDING)
            ->whereNull('escalated_at')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $this->info(sprintf(
            '[notify-stale] Found %d stale request(s) (older than %d hours).',
            $rows->count(),
            self::STALE_AFTER_HOURS
        ));

        if ($this->option('dry-run')) {
            foreach ($rows as $r) {
                $this->line(sprintf(
                    '  - request#%d  client=%s  actor=%s  owner=%s  age=%dh',
                    $r->id, $r->client_id, $r->actor_agent_id, $r->owner_agent_id,
                    Carbon::now()->diffInHours($r->created_at)
                ));
            }
            return self::SUCCESS;
        }

        $notifier = app(TaskActionRequestNotifier::class);
        $sent = 0; $failed = 0;
        foreach ($rows as $r) {
            try {
                $notifier->notifyEscalation($r);
                $r->update(['escalated_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[notify-stale] failed for request#' . $r->id . ': ' . $e->getMessage());
                $this->error("  - request#{$r->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("[notify-stale] done. sent={$sent} failed={$failed}");
        return self::SUCCESS;
    }
}
