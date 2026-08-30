<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\RevenueRecognitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6, IFRS 15): "scheduled job accounting:recognize-
 * revenue releases on travel/check-in date (Dr Deferred / Cr Revenue; Dr Cost / Cr Prepaid) with
 * idempotency key recognize:{task_id}".
 *
 * Thin CLI wrapper — all real logic lives in {@see RevenueRecognitionService}, directly
 * unit/feature-testable against its own return value, same convention {@see PeriodClose}/
 * {@see AccountingVerify} already establish in this file family.
 *
 * Not yet wired into `routes/console.php`'s schedule — see this sub-wave's own report for why
 * (scheduling this alongside the reminder/period-close jobs already registered there is a routing
 * change judged out of this narrow lane's footprint; run manually or via an external cron until a
 * future wave adds it, the same documented gap this codebase already carries for several other
 * one-off Artisan commands).
 */
class RecognizeRevenue extends Command
{
    protected $signature = 'accounting:recognize-revenue
                            {--company= : Company id (default: every posting-engine-enabled company)}
                            {--date= : Release everything due on or before this date (default: today)}
                            {--dry-run : Preview which tasks would be released without posting anything}
                            {--user= : Acting user id (optional — system/queue-safe when omitted)}';

    protected $description = 'Release deferred revenue/cost for every at_travel service whose travel/check-in date has arrived.';

    public function handle(RevenueRecognitionService $service): int
    {
        $companyOption = $this->option('company');
        $companyId = $companyOption !== null ? (int) $companyOption : null;

        $dateOption = $this->option('date');
        $asOf = $dateOption !== null ? Carbon::parse($dateOption) : Carbon::today();

        $userOption = $this->option('user');
        $userId = $userOption !== null ? (int) $userOption : null;

        $dryRun = (bool) $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Revenue recognition release — as of '.$asOf->toDateString());
        $this->info('═══════════════════════════════════════════════════════');
        if ($dryRun) {
            $this->warn('  DRY RUN — no documents will be posted.');
        }

        $summary = $service->run($companyId, $asOf, $dryRun, $userId);

        $releasedCount = count($summary['released']);
        $errorCount = count($summary['errors']);

        $this->newLine();
        $this->info(sprintf(
            '  %d task(s) processed, %d %s, %d failed.',
            $summary['processed'],
            $releasedCount,
            $dryRun ? 'would be released' : 'released',
            $errorCount
        ));

        if ($errorCount > 0) {
            foreach ($summary['errors'] as $taskId => $message) {
                $this->error("    task #{$taskId}: {$message}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
