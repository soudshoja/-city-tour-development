<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C): "command accounting:period:close {company} {yyyy-mm} --soft|
 * --lock with checklist gate ... --reopen --reason= requiring accounting.period.reopen with audit
 * row."
 *
 * Thin CLI wrapper — all real logic lives in {@see PeriodCloseService}/
 * {@see \App\Services\Accounting\PeriodCloseChecklistService}, directly unit/feature-testable
 * against their return values, same convention {@see AccountingVerify}/{@see AccountingPeriodsInit}
 * already establish in this file family.
 *
 * `--user=` is required because a console invocation has no `Auth::user()` — every permission check
 * downstream resolves the acting user explicitly (see PeriodCloseService's own docblock).
 */
class PeriodClose extends Command
{
    protected $signature = 'accounting:period:close
                            {company : Company id}
                            {period : yyyy-mm (or yyyy under accounting.period.length=annual)}
                            {--soft : Soft-close the period}
                            {--lock : Lock the period}
                            {--reopen : Reopen a soft_closed/locked period instead of closing it}
                            {--reason= : Required with --reopen}
                            {--user= : Acting user id (required — a console run has no authenticated user)}';

    protected $description = 'Close (soft/lock) or reopen one accounting period, gated by the P2.5.C checklist.';

    public function handle(PeriodCloseService $service): int
    {
        $companyId = (int) $this->argument('company');
        $userOption = $this->option('user');
        $userId = $userOption !== null ? (int) $userOption : null;

        [$year, $month] = $this->parsePeriod((string) $this->argument('period'));

        if ($year === null) {
            $this->error('Invalid period. Use yyyy-mm (or yyyy under accounting.period.length=annual).');

            return self::FAILURE;
        }

        if ($this->option('reopen')) {
            return $this->handleReopen($service, $companyId, $year, $month, $userId);
        }

        return $this->handleClose($service, $companyId, $year, $month, $userId);
    }

    private function handleClose(PeriodCloseService $service, int $companyId, int $year, int $month, ?int $userId): int
    {
        $soft = (bool) $this->option('soft');
        $lock = (bool) $this->option('lock');

        if ($soft === $lock) {
            $this->error('Specify exactly one of --soft or --lock (or --reopen).');

            return self::FAILURE;
        }

        $target = $soft ? AccountingPeriod::STATUS_SOFT_CLOSED : AccountingPeriod::STATUS_LOCKED;

        try {
            $result = $service->close($companyId, $year, $month, $target, $userId);
        } catch (AuthorizationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $checklist = $result['checklist'];

        if (! $result['applied']) {
            $this->error(sprintf('Cannot close %04d-%02d: %d blocking issue(s):', $year, $month, count($checklist['blocking'])));
            foreach ($checklist['blocking'] as $item) {
                $this->line("  - [{$item['code']}] {$item['message']}");
            }

            return self::FAILURE;
        }

        $this->info(sprintf('Period %04d-%02d is now %s.', $year, $month, $target));

        if ($checklist['warnings'] !== []) {
            $this->warn(sprintf('%d warning(s):', count($checklist['warnings'])));
            foreach ($checklist['warnings'] as $item) {
                $this->line("  - [{$item['code']}] {$item['message']}");
            }
        }

        return self::SUCCESS;
    }

    private function handleReopen(PeriodCloseService $service, int $companyId, int $year, int $month, ?int $userId): int
    {
        $reason = (string) $this->option('reason');

        if ($userId === null) {
            $this->error('--user= is required.');

            return self::FAILURE;
        }

        try {
            $service->reopen($companyId, $year, $month, $userId, $reason);
        } catch (AuthorizationException|\InvalidArgumentException|\App\Exceptions\Accounting\PeriodDependencyBlockedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Period %04d-%02d reopened.', $year, $month));

        return self::SUCCESS;
    }

    /** @return array{0: ?int, 1: int} */
    private function parsePeriod(string $period): array
    {
        $isAnnual = (string) config('accounting.period.length', 'monthly') === 'annual';

        if ($isAnnual && preg_match('/^\d{4}$/', $period) === 1) {
            return [(int) $period, AccountingPeriod::ANNUAL_MONTH];
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m) === 1) {
            $month = (int) $m[2];
            if ($month < 1 || $month > 12) {
                return [null, 0];
            }

            return [(int) $m[1], $month];
        }

        return [null, 0];
    }
}
