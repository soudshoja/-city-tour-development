<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.A (p2_5-brief.md §P2.5.A): "command accounting:periods:init --company= creating open rows
 * from the first journal month to now (idempotent, --dry-run)".
 */
class AccountingPeriodsInitCommandTest extends AccountingTestCase
{
    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    /** Writes a plain transactions row with the given transaction_date — the column this command
     *  reads for "first journal month". Not a real posted document (no journal_entries lines),
     *  which is fine: this command never reads journal_entries, and AccountingInvariants only
     *  checks balance for companies with real postings — this row alone does not trip it since
     *  no journal_entries exist for it either way, but we still route through the company's own
     *  invariant tracking for consistency with the rest of the suite. */
    private function writeTransactionDated(Company $company, \DateTimeInterface $date): void
    {
        Transaction::forceCreate([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => 10.0,
            'description' => 'fixture',
            'reference_type' => 'Invoice',
            'transaction_date' => $date,
        ]);
    }

    public function test_creates_current_month_only_when_company_has_no_journal_history(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(2026, $rows->first()->year);
        $this->assertSame(6, $rows->first()->month);
        $this->assertSame(AccountingPeriod::STATUS_OPEN, $rows->first()->status);

        Carbon::setTestNow();
    }

    public function test_creates_one_row_per_month_from_first_journal_month_through_now(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();
        $this->writeTransactionDated($company, Carbon::create(2026, 3, 5));

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->orderBy('month')->get();
        $this->assertCount(4, $rows, 'Expected March, April, May, June (2026-03 through 2026-06 inclusive).');
        $this->assertSame([3, 4, 5, 6], $rows->pluck('month')->all());
        $this->assertTrue($rows->every(fn ($r) => $r->year === 2026 && $r->status === AccountingPeriod::STATUS_OPEN));

        Carbon::setTestNow();
    }

    public function test_dry_run_reports_but_writes_nothing(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();
        $this->writeTransactionDated($company, Carbon::create(2026, 4, 1));

        Artisan::call('accounting:periods:init', ['--company' => $company->id, '--dry-run' => true]);

        $this->assertSame(0, AccountingPeriod::where('company_id', $company->id)->count());

        Carbon::setTestNow();
    }

    public function test_is_idempotent_and_never_resets_an_existing_rows_status(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();
        $this->writeTransactionDated($company, Carbon::create(2026, 4, 1));

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);
        $this->assertSame(3, AccountingPeriod::where('company_id', $company->id)->count()); // Apr, May, Jun

        // Operator closes April by hand (simulating the close command, which is out of this
        // sub-wave's scope) -- a re-run must not reopen it.
        AccountingPeriod::where('company_id', $company->id)->where('month', 4)
            ->update(['status' => AccountingPeriod::STATUS_LOCKED]);

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->get();
        $this->assertSame(3, $rows->count(), 'A re-run must not create duplicate rows.');
        $april = $rows->firstWhere('month', 4);
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $april->status, 'A re-run must never reset an existing row\'s status.');

        Carbon::setTestNow();
    }

    public function test_company_filter_restricts_to_one_company(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();

        Artisan::call('accounting:periods:init', ['--company' => $companyA->id]);

        $this->assertGreaterThan(0, AccountingPeriod::where('company_id', $companyA->id)->count());
        $this->assertSame(0, AccountingPeriod::where('company_id', $companyB->id)->count());

        Carbon::setTestNow();
    }

    public function test_omitting_company_option_runs_for_every_company(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();

        Artisan::call('accounting:periods:init');

        $this->assertGreaterThan(0, AccountingPeriod::where('company_id', $companyA->id)->count());
        $this->assertGreaterThan(0, AccountingPeriod::where('company_id', $companyB->id)->count());

        Carbon::setTestNow();
    }

    public function test_annual_length_creates_one_row_per_year_with_the_sentinel_month(): void
    {
        config(['accounting.period.length' => 'annual']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();
        $this->writeTransactionDated($company, Carbon::create(2024, 11, 1));

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->orderBy('year')->get();
        $this->assertCount(3, $rows, 'Expected 2024, 2025, 2026.');
        $this->assertSame([2024, 2025, 2026], $rows->pluck('year')->all());
        $this->assertTrue($rows->every(fn ($r) => $r->month === AccountingPeriod::ANNUAL_MONTH));

        Carbon::setTestNow();
    }
}
