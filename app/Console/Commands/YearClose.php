<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\YearEndCloseService;
use Illuminate\Console\Command;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C; doc 11 §P5.2): "accounting:year:close {company} {yyyy}: all 12
 * months locked -> one YEC document closing P&L to 3400 Retained Earnings with
 * allowLockedPeriods=true, idempotent."
 *
 * Thin CLI wrapper — all logic lives in {@see YearEndCloseService}, directly testable against its
 * return value, same convention every other command in this file family follows.
 */
class YearClose extends Command
{
    protected $signature = 'accounting:year:close
                            {company : Company id}
                            {year : Fiscal year, e.g. 2026}
                            {--user= : Acting user id, recorded on the posted YEC document}';

    protected $description = 'Post the year-end closing entry (Income/Expenses swept to Retained Earnings) once every period in the year is locked.';

    public function handle(YearEndCloseService $service): int
    {
        $companyId = (int) $this->argument('company');
        $year = (int) $this->argument('year');
        $userOption = $this->option('user');
        $userId = $userOption !== null ? (int) $userOption : null;

        $result = $service->run($companyId, $year, $userId);

        if (! $result['success']) {
            $this->error("Cannot close fiscal year {$year} for company #{$companyId}:");
            foreach ($result['blocking'] as $reason) {
                $this->line("  - {$reason}");
            }

            return self::FAILURE;
        }

        if ($result['already_closed']) {
            $this->info("Fiscal year {$year} was already closed (transaction #{$result['transaction']->id}).");

            return self::SUCCESS;
        }

        if ($result['transaction'] === null) {
            $this->info("Fiscal year {$year}: no P&L activity to sweep — nothing posted.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Fiscal year %d closed. Net profit/(loss): %s. Transaction #%d.',
            $year,
            number_format((float) $result['net_profit'], 3),
            $result['transaction']->id
        ));

        return self::SUCCESS;
    }
}
