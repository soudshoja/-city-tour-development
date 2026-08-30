<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\ReconciliationRun;
use App\Services\Accounting\ReconciliationAutoMatchService;
use Illuminate\Console\Command;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §9): "Scheduled command
 * accounting:reconcile --auto ... running daily ... it never posts money." This command is a thin
 * CLI wrapper around {@see ReconciliationAutoMatchService} — one {@see ReconciliationRun} row per
 * company per invocation, exactly the run-status panel's own source of truth
 * ({@see \App\Services\Accounting\ReconciliationCenterService::runStatus()}).
 *
 * `--company=` narrows to one tenant, matching {@see AccountingVerify}'s own convention; omitted,
 * every company with the posting engine enabled is run. `--auto` is accepted (and required by the
 * design doc's own literal invocation, `accounting:reconcile --auto`) but this build has no
 * non-auto mode yet — v0 scope is internal proposals only (reconciliation-design.md §6/§7); it is
 * validated, not silently ignored, so a caller who forgets it gets a clear message rather than a
 * silent no-op.
 *
 * The nightly registration itself lives in `routes/console.php`, `withoutOverlapping()` per the
 * brief's own words ("queued job, withoutOverlapping").
 */
class AccountingReconcileAuto extends Command
{
    protected $signature = 'accounting:reconcile
                            {--auto : Required flag — this build only implements the auto-proposal mode}
                            {--company= : Restrict to one company id; omitted runs every posting-engine-enabled company}';

    protected $description = 'Nightly/manual internal auto-reconciliation: proposes matches, never posts money.';

    public function handle(ReconciliationAutoMatchService $service): int
    {
        if (! $this->option('auto')) {
            $this->error('accounting:reconcile requires --auto in this build (v0 is internal-proposals-only).');

            return self::FAILURE;
        }

        $companyId = $this->option('company') !== null ? (int) $this->option('company') : null;

        $companies = $companyId !== null
            ? Company::where('id', $companyId)->get(['id'])
            : Company::where('posting_engine_enabled', true)->get(['id']);

        $totalCreated = 0;
        $failures = 0;

        foreach ($companies as $company) {
            try {
                $run = $service->run((int) $company->id, ReconciliationRun::TRIGGER_NIGHTLY);
                $totalCreated += $run->proposals_created;
                $this->info("company #{$company->id}: {$run->proposals_created} proposal(s) created.");
            } catch (\Throwable $e) {
                $failures++;
                $this->error("company #{$company->id}: run failed — {$e->getMessage()}");
            }
        }

        $this->info("accounting:reconcile --auto — {$companies->count()} company/companies run, {$totalCreated} proposal(s) created total.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
