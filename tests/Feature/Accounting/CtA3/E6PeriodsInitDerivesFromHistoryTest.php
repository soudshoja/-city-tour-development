<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Task;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 E6 — CT-F40: `accounting:periods:init` used to derive its whole range from
 * `transactions.transaction_date` alone. CT-A2 §2.4 / CT-A1 §1.8: on a real cutover dataset the
 * `transactions` header table had been emptied to 7 surviving rows all dated in the current
 * month, against 26,605 distinct document ids in `journal_entries` — the command created 2
 * periods where the real document history spanned 2024-06 -> 2026-12, and 34 periods had to be
 * inserted by hand.
 *
 * OWNER-SPECIFIED FIX: derive the range from the MIN and MAX of `transactions.transaction_date`,
 * `journal_entries.transaction_date`, `journal_entries.posting_date`, `tasks.issued_date`, and
 * `invoices.invoice_date`, with the end of the range being `max(now, latest document date)`.
 *
 * These tests deliberately do NOT call trackCompanyForInvariants() for the throwaway companies
 * they build: the journal_entries fixtures below are single, unpaired rows written directly via
 * forceCreate purely to exercise date derivation (never posted through PostingService), so they
 * are not meant to — and must not be expected to — satisfy the C1 double-entry/orphan-line
 * invariants a real posting-engine test would assert.
 *
 * @see \App\Console\Commands\AccountingPeriodsInit::deriveDateRange()
 * @see \App\Console\Commands\AccountingPeriodsInit::dateRangeSources()
 */
class E6PeriodsInitDerivesFromHistoryTest extends AccountingTestCase
{
    private function makeCompany(): Company
    {
        return Company::factory()->create();
    }

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

    /** A single, unpaired journal_entries row (transaction_id left NULL) — see class docblock. */
    private function writeJournalEntryDated(Company $company, \DateTimeInterface $date): void
    {
        $account = Account::factory()->create(['company_id' => $company->id]);

        JournalEntry::forceCreate([
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'fixture',
            'description' => 'fixture',
            'transaction_date' => $date,
            'debit' => 10.0,
            'credit' => 0,
        ]);
    }

    private function writeTaskDated(Company $company, \DateTimeInterface $date): void
    {
        Task::factory()->create([
            'company_id' => $company->id,
            'client_id' => null,
            'agent_id' => null,
            'supplier_id' => null,
            'issued_date' => $date,
        ]);
    }

    private function writeInvoiceDated(Company $company, \DateTimeInterface $date): void
    {
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $agent = Agent::factory()->create(['branch_id' => $branch->id]);

        Invoice::factory()->create([
            'agent_id' => $agent->id,
            'invoice_date' => $date,
        ]);
    }

    /**
     * The exact CT-F40 reproduction: `transactions` holds only a row dated in the CURRENT month,
     * but `journal_entries` carries a document dated ~2 years earlier and one dated ~3 months in
     * the FUTURE. Periods must be created back to the journal's earliest month and forward to the
     * future document's month — an exact count, not merely ">2".
     */
    public function test_ct_f40_reproduction_journal_entries_widen_the_range_both_ways(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();

        $this->writeTransactionDated($company, Carbon::create(2026, 6, 5)); // current month only
        $this->writeJournalEntryDated($company, Carbon::create(2024, 6, 1)); // ~2 years earlier
        $this->writeJournalEntryDated($company, Carbon::create(2026, 9, 1)); // ~3 months in the future

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->orderBy('year')->orderBy('month')->get();

        // 2024-06 through 2026-09 inclusive: 7 (2024) + 12 (2025) + 9 (2026) = 28 months.
        $this->assertCount(28, $rows);
        $this->assertSame(2024, $rows->first()->year);
        $this->assertSame(6, $rows->first()->month);
        $this->assertSame(2026, $rows->last()->year);
        $this->assertSame(9, $rows->last()->month);

        Carbon::setTestNow();
    }

    /** `tasks.issued_date` alone (no journal rows, no transactions) drives the range. */
    public function test_tasks_issued_date_alone_drives_the_range(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();
        $this->writeTaskDated($company, Carbon::create(2025, 1, 15));

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->orderBy('year')->orderBy('month')->get();

        // 2025-01 through 2026-06 inclusive: 12 (2025) + 6 (2026) = 18 months.
        $this->assertCount(18, $rows);
        $this->assertSame(2025, $rows->first()->year);
        $this->assertSame(1, $rows->first()->month);
        $this->assertSame(2026, $rows->last()->year);
        $this->assertSame(6, $rows->last()->month);

        Carbon::setTestNow();
    }

    /** `invoices.invoice_date` alone drives the range. */
    public function test_invoices_invoice_date_alone_drives_the_range(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();
        $this->writeInvoiceDated($company, Carbon::create(2025, 3, 1));

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->orderBy('year')->orderBy('month')->get();

        // 2025-03 through 2026-06 inclusive: 10 (2025 Mar-Dec) + 6 (2026 Jan-Jun) = 16 months.
        $this->assertCount(16, $rows);
        $this->assertSame(2025, $rows->first()->year);
        $this->assertSame(3, $rows->first()->month);
        $this->assertSame(2026, $rows->last()->year);
        $this->assertSame(6, $rows->last()->month);

        Carbon::setTestNow();
    }

    /** A company with no history anywhere (no transactions, no journal_entries, no tasks, no invoices) still gets exactly one period (this month). */
    public function test_no_history_anywhere_still_creates_exactly_one_period(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(2026, $rows->first()->year);
        $this->assertSame(6, $rows->first()->month);

        Carbon::setTestNow();
    }

    /** Idempotent: a second run creates 0 new rows and does not reopen a period an operator closed. */
    public function test_is_idempotent_and_never_reopens_a_closed_period(): void
    {
        config(['accounting.period.length' => 'monthly']);
        Carbon::setTestNow(Carbon::create(2026, 6, 10));

        $company = $this->makeCompany();
        $this->writeJournalEntryDated($company, Carbon::create(2026, 3, 1));

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);
        $firstRunCount = AccountingPeriod::where('company_id', $company->id)->count();
        $this->assertSame(4, $firstRunCount, 'Expected March, April, May, June (2026-03 through 2026-06 inclusive).');

        AccountingPeriod::where('company_id', $company->id)->where('month', 3)
            ->update(['status' => AccountingPeriod::STATUS_LOCKED]);

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $rows = AccountingPeriod::where('company_id', $company->id)->get();
        $this->assertSame($firstRunCount, $rows->count(), 'A re-run must not create duplicate rows.');

        $march = $rows->firstWhere('month', 3);
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $march->status, 'A re-run must never reset an existing row\'s status.');

        Carbon::setTestNow();
    }
}
