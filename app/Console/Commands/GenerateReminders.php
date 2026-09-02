<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\ReminderGeneratorDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "One command reminder:generate --kind=all|<kind>, scheduled via
 * routes/console.php with withoutOverlapping()->onOneServer()". CREATE-only, same split
 * {@see \App\Console\Commands\GenerateHoldDeadlineReminders} already established --
 * {@see \App\Console\Commands\SendReminders} ("process:reminder") keeps owning the SEND step,
 * unchanged by this command's existence.
 *
 * See {@see \App\Services\Reminders\ReminderGeneratorDispatcher} for which kinds this actually
 * sweeps under `--kind=all` and why `commission_unearned`/`task_unassigned`/`task_uninvoiced`/
 * `custom` are not among them.
 */
class GenerateReminders extends Command
{
    protected $signature = 'reminder:generate {--kind=all : all, or one reminder_kind} {--company= : Limit to one company id}';

    protected $description = 'Generate pending reminder rows for overdue invoices, statement balances, ticketing deadlines, and uninvoiced payment links (idempotent, create-only).';

    public function handle(ReminderGeneratorDispatcher $dispatcher): int
    {
        $kind = (string) $this->option('kind');
        $companyId = $this->option('company') !== null ? (int) $this->option('company') : null;

        try {
            $results = $dispatcher->run($kind, $companyId);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $totalCreated = 0;
        $totalSkipped = 0;
        $rows = [];
        foreach ($results as $resultKind => $counts) {
            $rows[] = [$resultKind, $counts['created'], $counts['skipped']];
            $totalCreated += $counts['created'];
            $totalSkipped += $counts['skipped'];
        }

        $this->table(['Kind', 'Created', 'Skipped'], $rows);
        $this->info("Total: {$totalCreated} created, {$totalSkipped} skipped.");

        Log::info('reminder.generate_completed', ['kind' => $kind, 'company_id' => $companyId, 'results' => $results]);

        return 0;
    }
}
