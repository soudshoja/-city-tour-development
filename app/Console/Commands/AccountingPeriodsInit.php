<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.A (p2_5-brief.md §P2.5.A): "command accounting:periods:init --company= creating open rows
 * from the first journal month to now (idempotent, --dry-run)".
 *
 * Populates {@see AccountingPeriod} rows for one company (or every company, when `--company` is
 * omitted) from that company's earliest document date through its LATEST document date (or now,
 * whichever is later), inclusive. Every generated row starts `status = open` — this command only
 * ever ADDS rows; it never touches the status of a row that already exists (a re-run must never
 * silently reopen a period an operator already closed/locked — that is exactly the "idempotent"
 * requirement above).
 *
 * Grain (monthly vs. annual) follows `config('accounting.period.length')` — see that config key's
 * own docblock and {@see AccountingPeriod::ANNUAL_MONTH}.
 *
 * CT-A3 E6 (CT-F40): the range used to be read from `transactions.transaction_date` alone. On a
 * real cutover dataset that table can be nearly empty (surviving rows dated only in the current
 * month) while the actual document history lives in `journal_entries`/`tasks`/`invoices` spanning
 * years either side of "now" — a `transactions`-only derivation silently produced a two-period
 * range where 34 periods were needed, and a backfill dated outside that range dies on the period
 * guard. {@see dateRangeSources()} now derives the MIN and MAX from every one of those tables that
 * actually exists on this schema (`Schema::hasTable()`/`hasColumn()` guarded, so a company on a
 * partial schema never fatals), ignoring soft-deleted rows where the table has one, and the END of
 * the range is `max(now, latest document date)` so a document dated into the future (real dev data
 * carries documents dated months ahead — CT-A1 §1.3) still gets an open period. A company with no
 * history anywhere in any source still falls back to "this month only" — there is no history to
 * open a wider range for, and doing so would manufacture periods for months that never existed for
 * this company. `--dry-run` runs this exact same derivation and range-building code path; it only
 * skips the final `AccountingPeriod::create()` writes.
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
            $now = Carbon::now();
            [$firstDate, $minSourceLabel, $lastDate, $maxSourceLabel] = $this->deriveDateRange((int) $company->id, $now);

            $start = $firstDate !== null ? Carbon::parse($firstDate) : $now->copy();
            $end = $lastDate !== null && $lastDate->greaterThan($now) ? $lastDate : $now->copy();

            $rows = $isAnnual
                ? $this->annualRows($start, $end)
                : $this->monthlyRows($start, $end);

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
                ? sprintf('%d -> %d', $start->format('Y'), $end->format('Y'))
                : sprintf('%s -> %s', $start->format('Y-m'), $end->format('Y-m'));

            $this->line(sprintf(
                'Company #%d: %d period row(s) to create (%s)%s.',
                $company->id,
                count($toCreate),
                $rangeLabel,
                $firstDate === null ? ' [no history in any source — current period only]' : ''
            ));

            $this->line(sprintf(
                'Company #%d: range start from %s, range end from %s.',
                $company->id,
                $minSourceLabel,
                $maxSourceLabel
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

    /**
     * CT-A3 E6 (CT-F40) — derives the (min, max) document-date range for one company from every
     * source {@see dateRangeSources()} finds, and folds `now` into the max side so a document
     * dated into the future never falls outside the range this command opens.
     *
     * @return array{0: ?Carbon, 1: string, 2: ?Carbon, 3: string} [firstDate, minSourceLabel,
     *                                                             lastDate, maxSourceLabel] —
     *                                                             firstDate is null only when no
     *                                                             source had any row at all for
     *                                                             this company; lastDate is never
     *                                                             null (it is always at least
     *                                                             $now).
     */
    private function deriveDateRange(int $companyId, Carbon $now): array
    {
        $minDate = null;
        $minLabel = 'no history in any source (falling back to the current period)';
        $maxDate = $now->copy();
        $maxLabel = 'current date (now)';

        foreach ($this->dateRangeSources($companyId) as $source) {
            if ($source['min'] !== null) {
                $candidate = Carbon::parse($source['min']);

                if ($minDate === null || $candidate->lessThan($minDate)) {
                    $minDate = $candidate;
                    $minLabel = $source['label'];
                }
            }

            if ($source['max'] !== null) {
                $candidate = Carbon::parse($source['max']);

                if ($candidate->greaterThan($maxDate)) {
                    $maxDate = $candidate;
                    $maxLabel = $source['label'];
                }
            }
        }

        return [$minDate, $minLabel, $maxDate, $maxLabel];
    }

    /**
     * CT-A3 E6 (CT-F40) — one (min, max, label) row per document-date source this command knows
     * how to read, `Schema::hasTable()`/`hasColumn()` guarded so a company on a partial schema (a
     * table/column this build hasn't migrated yet) is silently skipped rather than fataling.
     * Soft-deleted rows are excluded wherever the source table has a `deleted_at` column.
     *
     * `invoices` carries no `company_id` column of its own — it is scoped to a company via
     * `invoices.agent_id -> agents.branch_id -> branches.company_id`, the exact join
     * `ReportController::getProfitAgentSum()` already uses for the same table.
     *
     * @return list<array{label: string, min: ?string, max: ?string}>
     */
    private function dateRangeSources(int $companyId): array
    {
        $sources = [];

        $simple = [
            ['table' => 'transactions', 'column' => 'transaction_date', 'label' => 'transactions.transaction_date'],
            ['table' => 'journal_entries', 'column' => 'transaction_date', 'label' => 'journal_entries.transaction_date'],
            ['table' => 'journal_entries', 'column' => 'posting_date', 'label' => 'journal_entries.posting_date'],
            ['table' => 'tasks', 'column' => 'issued_date', 'label' => 'tasks.issued_date'],
        ];

        foreach ($simple as $spec) {
            [$min, $max] = $this->minMaxForCompanyScopedTable($spec['table'], $spec['column'], $companyId);

            if ($min !== null || $max !== null) {
                $sources[] = ['label' => $spec['label'], 'min' => $min, 'max' => $max];
            }
        }

        if (
            Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'invoice_date') && Schema::hasColumn('invoices', 'agent_id')
            && Schema::hasTable('agents') && Schema::hasColumn('agents', 'branch_id')
            && Schema::hasTable('branches') && Schema::hasColumn('branches', 'company_id')
        ) {
            $branchIds = DB::table('branches')->where('company_id', $companyId)->pluck('id');

            if ($branchIds->isNotEmpty()) {
                $query = DB::table('invoices')
                    ->join('agents', 'invoices.agent_id', '=', 'agents.id')
                    ->whereIn('agents.branch_id', $branchIds)
                    ->whereNotNull('invoices.invoice_date');

                if (Schema::hasColumn('invoices', 'deleted_at')) {
                    $query->whereNull('invoices.deleted_at');
                }

                $row = $query->selectRaw('MIN(invoices.invoice_date) as min_date, MAX(invoices.invoice_date) as max_date')->first();

                if ($row !== null && ($row->min_date !== null || $row->max_date !== null)) {
                    $sources[] = ['label' => 'invoices.invoice_date', 'min' => $row->min_date, 'max' => $row->max_date];
                }
            }
        }

        return $sources;
    }

    /** @return array{0: ?string, 1: ?string} [min, max] */
    private function minMaxForCompanyScopedTable(string $table, string $column, int $companyId): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, 'company_id')) {
            return [null, null];
        }

        $query = DB::table($table)
            ->where('company_id', $companyId)
            ->whereNotNull($column);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $row = $query->selectRaw("MIN({$column}) as min_date, MAX({$column}) as max_date")->first();

        if ($row === null) {
            return [null, null];
        }

        return [$row->min_date, $row->max_date];
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
