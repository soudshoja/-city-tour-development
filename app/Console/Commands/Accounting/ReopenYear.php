<?php

declare(strict_types=1);

namespace App\Console\Commands\Accounting;

use App\Exceptions\Accounting\ClosedYearProcedureException;
use App\Exceptions\Accounting\PeriodDependencyBlockedException;
use App\Exceptions\Accounting\PostingException;
use App\Services\Accounting\ClosedYearAdjustmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * XBRL-X9r (PLAN.md X9r, L25 rule 2): reopen a year that is locked and closed by a YEC, so an audit
 * adjustment can be posted INTO that year. The fallback path: the normal path is to keep December
 * `soft_closed` for the audit and post there with the override (L25 rule 1).
 *
 * Gated by `accounting.period.reopen` (the acting `--user=` must hold it) and a mandatory
 * `--reason=`, which is written on every period's reopen row and on the procedure's own
 * `reopen_year` audit row. Atomic: a refusal at any step (for example a reconciled line on the YEC,
 * which the reversal refuses) leaves every period and the YEC exactly as they were. Never forces a
 * reversal and never uses the locked-period bypass.
 *
 * Thin wrapper; the logic is {@see ClosedYearAdjustmentService::reopenYear()}.
 */
class ReopenYear extends Command
{
    protected $signature = 'accounting:reopen-year
                            {company : Company id}
                            {year : The closed fiscal year, e.g. 2025}
                            {--reason= : Why the year is reopened (required; recorded on every audit row)}
                            {--user= : Acting user id; must hold accounting.period.reopen}';

    protected $description = 'Reopen a locked, year-end-closed fiscal year: reopen its December and every later closed period, and reverse its YEC.';

    public function handle(ClosedYearAdjustmentService $service): int
    {
        $companyId = (int) $this->argument('company');
        $year = (int) $this->argument('year');
        $reason = trim((string) $this->option('reason'));
        $userOption = $this->option('user');

        if ($userOption === null || $userOption === '') {
            $this->error('--user= is required (a console run has no authenticated user).');

            return self::FAILURE;
        }

        if ($reason === '') {
            $this->error('--reason= is required to reopen a closed year.');

            return self::FAILURE;
        }

        try {
            $result = $service->reopenYear($companyId, $year, (int) $userOption, $reason);
        } catch (ClosedYearProcedureException|AuthorizationException|PeriodDependencyBlockedException|PostingException|\InvalidArgumentException|ModelNotFoundException $e) {
            // ModelNotFoundException: the last-resort shape of a lost race inside post() (X9R-VERIFY
            // m-3); the close lock makes it unreachable, but the refusal must stay clean if it is not.
            $this->error("Fiscal year {$year} was NOT reopened; nothing was changed. ".$e->getMessage()
                .($e instanceof PostingException ? ' This procedure never forces a reversal: resolve the cause above, then run it again.' : ''));

            return self::FAILURE;
        }

        foreach ($result['reopened'] as $p) {
            $this->line(sprintf('Reopened %04d-%02d (was %s).', $p['year'], $p['month'], $p['status_before']));
        }

        $this->line(sprintf(
            'Reversed YEC #%d with reversal #%d, dated %d-12-31.',
            $result['yec_transaction_id'],
            $result['reversal_transaction_id'],
            $year,
        ));

        $this->info("Fiscal year {$year} is reopened. Next steps:");
        $this->line("  1. Post the adjustments dated in December {$year} (a post dated in an earlier, still-locked month would be moved out of the year).");
        $this->line("  2. Run: php artisan accounting:reclose-year {$companyId} {$year} --user=<id>");

        return self::SUCCESS;
    }
}
