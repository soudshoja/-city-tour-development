<?php

declare(strict_types=1);

namespace App\Console\Commands\Accounting;

use App\Exceptions\Accounting\ClosedYearProcedureException;
use App\Exceptions\Accounting\PostingException;
use App\Services\Accounting\ClosedYearAdjustmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;

/**
 * XBRL-X9r (PLAN.md X9r, L25 rule 2): close a year again after `accounting:reopen-year` and the
 * adjustments: lock December, re-run the year-end close (a new YEC under a fresh idempotency key)
 * and restore every later period to the status it had before the reopen, printing each step.
 * Atomic and serialised with the close and the reopen (one lock per company and year); it completes
 * one reopen exactly once, so a second run changes nothing and never replays a finished reopen.
 *
 * Gated by `accounting.period.close` on `--user=`. Thin wrapper; the logic is
 * {@see ClosedYearAdjustmentService::recloseYear()}.
 */
class RecloseYear extends Command
{
    protected $signature = 'accounting:reclose-year
                            {company : Company id}
                            {year : The reopened fiscal year, e.g. 2025}
                            {--user= : Acting user id; must hold accounting.period.close}';

    protected $description = 'Re-close a year reopened by accounting:reopen-year: lock December, re-run the YEC, re-lock the later periods.';

    public function handle(ClosedYearAdjustmentService $service): int
    {
        $companyId = (int) $this->argument('company');
        $year = (int) $this->argument('year');
        $userOption = $this->option('user');

        if ($userOption === null || $userOption === '') {
            $this->error('--user= is required (a console run has no authenticated user).');

            return self::FAILURE;
        }

        try {
            $result = $service->recloseYear($companyId, $year, (int) $userOption);
        } catch (ClosedYearProcedureException|AuthorizationException|PostingException $e) {
            $this->error("Fiscal year {$year} was NOT re-closed; nothing was changed. ".$e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['steps'] as $i => $step) {
            $this->line(sprintf('%d. %s', $i + 1, $step));
        }

        $this->info($result['changed']
            ? "Fiscal year {$year} is closed again."
            : "Fiscal year {$year} was already closed again; nothing changed.");

        return self::SUCCESS;
    }
}
