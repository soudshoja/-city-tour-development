<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * P2.5.A (p2_5-brief.md §P2.5.A): "command accounting:periods:init --company= creating open rows
 * from the first journal month to now (idempotent, --dry-run)".
 *
 * Populates {@see AccountingPeriod} rows for one company (or every company, when `--company` is
 * omitted) from that company's earliest posted document date through the current period,
 * inclusive. Every generated row starts `status = open` — this command only ever ADDS rows; it
 * never touches the status of a row that already exists (a re-run must never silently reopen a
 * period an operator already closed/locked — that is exactly the "idempotent" requirement above).
 *
 * Grain (monthly vs. annual) follows `config('accounting.period.length')` — see that config key's
 * own docblock and {@see AccountingPeriod::ANNUAL_MONTH}.
 *
 * "First journal month" is read from `transactions.transaction_date` (the existing document-date
 * column every engine-posted and legacy row alike populates — see PostingService's own BUG-C4
 * note on `DocumentDraft::$docDate`/`journal_entries.transaction_date`/`transactions.
 * transaction_date` all being the same "document date" concept today). A company with no
 * transactions yet (or none with a populated transaction_date) falls back to "this month only" —
 * there is no journal history to open a wider range for, and doing so would manufacture periods
 * for months that never existed for this company.
 */
class AccountingPeriodsInit extends Command
{
    protected $signature = 'accounting:periods:init
                            {--company= : Restrict to one company id; omit to run for every company}
                            {--dry-run : Report what would be created without writing anything}';

    protected $description = 'Create open accounting_periods rows from a company\'s first journal month through the current period (idempotent).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companyOption = $this->option('company');

        if ($companyOption !== null && $companyOption !== '') {
            $companies = Company::where('id', (int) $companyOption)->get();

            if ($companies->isEmpty()) {
                $this->error("No company found with id #{$companyOption}.");

                return self::FAILURE;
            }
        } else {
            $companies = Company::all();
        }

        $length = (string) config('accounting.period.length', 'monthly');
        $isAnnual = $length === 'annual';
        $totalCreated = 0;

        foreach ($companies as $company) {
            $firstDate = Transaction::where('company_id', $company->id)
                ->whereNotNull('transaction_date')
                ->min('transaction_date');

            $now = Carbon::now();
            $start = $firstDate !== null ? Carbon::parse($firstDate) : $now->copy();

            $rows = $isAnnual
                ? $this->annualRows($start, $now)
                : $this->monthlyRows($start, $now);

            $toCreate = [];

            foreach ($rows as $row) {
                $exists = AccountingPeriod::query()
                    ->where('company_id', $company->id)
                    ->where('year', $row['year'])
                    ->where('month', $row['month'])
                    ->exists();

                if (! $exists) {
                    $toCreate[] = $row;
                }
            }

            $rangeLabel = $isAnnual
                ? sprintf('%d -> %d', $start->format('Y'), $now->format('Y'))
                : sprintf('%s -> %s', $start->format('Y-m'), $now->format('Y-m'));

            $this->line(sprintf(
                'Company #%d: %d period row(s) to create (%s)%s.',
                $company->id,
                count($toCreate),
                $rangeLabel,
                $firstDate === null ? ' [no journal history — current period only]' : ''
            ));

            if (! $dryRun) {
                foreach ($toCreate as $row) {
                    AccountingPeriod::create([
                        'company_id' => $company->id,
                        'year' => $row['year'],
                        'month' => $row['month'],
                        'status' => AccountingPeriod::STATUS_OPEN,
                    ]);
                }
            }

            $totalCreated += count($toCreate);
        }

        $this->info(sprintf(
            '%s%d accounting period row(s).',
            $dryRun ? '[dry-run] Would create ' : 'Created ',
            $totalCreated
        ));

        return self::SUCCESS;
    }

    /** @return array<int, array{year: int, month: int}> */
    private function monthlyRows(Carbon $start, Carbon $end): array
    {
        $rows = [];
        $cursor = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $rows[] = ['year' => (int) $cursor->format('Y'), 'month' => (int) $cursor->format('n')];
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $rows;
    }

    /** @return array<int, array{year: int, month: int}> */
    private function annualRows(Carbon $start, Carbon $end): array
    {
        $rows = [];

        for ($year = (int) $start->format('Y'); $year <= (int) $end->format('Y'); $year++) {
            $rows[] = ['year' => $year, 'month' => AccountingPeriod::ANNUAL_MONTH];
        }

        return $rows;
    }
}
