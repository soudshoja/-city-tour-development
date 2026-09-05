<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\Reports\EquityChangesReportService;
use App\Services\Accounting\YearEndCloseService;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * ADVERSARIAL VERIFICATION scratch suite for T5/T6 (accounting-builds phase, review-packets/
 * T5-T6-equity.md). Not part of the builder's own suite — written by the independent verifier.
 */
class T5T6AdversarialVerificationTest extends AccountingTestCase
{
    private function service(): YearEndCloseService
    {
        return app(YearEndCloseService::class);
    }

    private function equity(): EquityChangesReportService
    {
        return app(EquityChangesReportService::class);
    }

    private function trialBalance(): TrialBalanceService
    {
        return app(TrialBalanceService::class);
    }

    private function resolver(): AccountResolver
    {
        return app(AccountResolver::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeEngineOnCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function lockAllMonths(Company $company, int $year): void
    {
        for ($m = 1; $m <= 12; $m++) {
            AccountingPeriod::create(['company_id' => $company->id, 'year' => $year, 'month' => $m, 'status' => AccountingPeriod::STATUS_LOCKED]);
        }
    }

    private function makeLine(Transaction $txn, Company $company, Branch $branch, Account $account, float $debit, float $credit, Carbon $date): JournalEntry
    {
        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $account->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => $debit, 'credit' => $credit, 'name' => $account->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => max($debit, $credit),
            'voucher_number' => 'TST', 'type_reference_id' => $company->id,
        ]);
    }

    private function postPlAndAr(Company $company, Branch $branch, int $year, float $income, float $expense): void
    {
        $incomeAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4133')->firstOrFail();
        $expenseAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5222')->firstOrFail();
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $date = Carbon::create($year, 6, 15);

        $total = $income + $expense;
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $total, 'description' => 'PL fixture', 'reference_type' => 'Invoice',
            'reference_number' => 'PL-'.uniqid(), 'name' => 'PL fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => $year, 'posting_status' => 'posted',
            'total_debit' => $total, 'total_credit' => $total, 'idempotency_key' => uniqid('pl:'),
        ]);

        $this->makeLine($txn, $company, $branch, $ar, $income, 0, $date);
        $this->makeLine($txn, $company, $branch, $incomeAccount, 0, $income, $date);
        $this->makeLine($txn, $company, $branch, $expenseAccount, $expense, 0, $date);
        $this->makeLine($txn, $company, $branch, $ar, 0, $expense, $date);
    }

    private function postDividendPayment(Company $company, Branch $branch, int $year, float $amount, ?Carbon $date = null): Account
    {
        $dividends = $this->resolver()->resolve('DIVIDENDS_PAID', $company->id);
        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $date ??= Carbon::create($year, 9, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Dividend payment fixture', 'reference_type' => 'Payment',
            'reference_number' => 'DIV-'.uniqid(), 'name' => 'Dividend payment fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => $year, 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('div:'),
        ]);

        $this->makeLine($txn, $company, $branch, $dividends, $amount, 0, $date);
        $this->makeLine($txn, $company, $branch, $bank, 0, $amount, $date);

        return $dividends;
    }

    // ── (1) Dividend in a LOSS year — sign checks ──────────────────────────────────────────────

    public function test_dividend_sweep_in_a_loss_making_year(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        // Loss: expense (300) > income (100) => net_profit = -200.
        $this->postPlAndAr($company, $branch, 2026, income: 100, expense: 300);
        $dividendsAccount = $this->postDividendPayment($company, $branch, 2026, 50);

        $result = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($result['success']);
        $this->assertEqualsWithDelta(-200.0, $result['net_profit'], 0.001, 'Loss year net_profit must be negative and untouched by the dividend sweep.');

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $result['transaction']->id)->get();
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.001);

        $dividendSweepLine = $lines->firstWhere('account_id', $dividendsAccount->id);
        $this->assertNotNull($dividendSweepLine);
        $this->assertEqualsWithDelta(50.0, (float) $dividendSweepLine->credit, 0.001, 'Dividend sweep must still credit 3200 by 50 regardless of the P&L sign.');

        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);
        $reLines = $lines->where('account_id', $retainedEarnings->id);
        $reNet = (float) $reLines->sum('credit') - (float) $reLines->sum('debit');
        // -200 (loss) - 50 (dividend) = -250: RE goes MORE negative, not less.
        $this->assertEqualsWithDelta(-250.0, $reNet, 0.001, 'A loss-making year that still pays a dividend must make Retained Earnings MORE negative (loss + dividend both reduce RE).');
    }

    // ── (2) Zero dividends — no sweep line at all (not a 0-amount line) ───────────────────────

    public function test_zero_dividends_produces_no_dividend_sweep_lines(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        // No dividend payment posted at all this year.

        $result = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($result['success']);

        $dividendsAccount = $this->resolver()->resolve('DIVIDENDS_PAID', $company->id);
        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $result['transaction']->id)->get();
        $dividendLines = $lines->where('account_id', $dividendsAccount->id);
        $this->assertCount(0, $dividendLines, 'A year with zero dividend movement must post NO lines on 3200 at all -- not a 0.00 line.');

        // Exactly 3 lines total: Income leaf, Expense leaf, Retained Earnings (net-profit only).
        $this->assertCount(3, $lines);
    }

    // ── (3) 3200 with an accidental CREDIT balance (refund of dividends) — sign generality ────

    public function test_dividend_account_with_a_credit_balance_is_swept_generically(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);

        $dividends = $this->resolver()->resolve('DIVIDENDS_PAID', $company->id);
        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $date = Carbon::create(2026, 9, 1);
        // A REFUND of a previously paid dividend: Dr bank / Cr 3200 -- an accidental CREDIT
        // balance on a normally debit-normal leaf.
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 60, 'description' => 'Dividend refund fixture', 'reference_type' => 'Payment',
            'reference_number' => 'DIVR-'.uniqid(), 'name' => 'Dividend refund fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'posted',
            'total_debit' => 60, 'total_credit' => 60, 'idempotency_key' => uniqid('divr:'),
        ]);
        $this->makeLine($txn, $company, $branch, $bank, 60, 0, $date);
        $this->makeLine($txn, $company, $branch, $dividends, 0, 60, $date);

        $result = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($result['success']);
        $this->assertEqualsWithDelta(300.0, $result['net_profit'], 0.001);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $result['transaction']->id)->get();
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.001, 'Document must still balance for a credit-balance dividend leaf.');

        $dividendSweepLine = $lines->firstWhere('account_id', $dividends->id);
        $this->assertNotNull($dividendSweepLine);
        // Mirrored: a net CREDIT balance is zeroed with a DEBIT sweep line (not a credit).
        $this->assertEqualsWithDelta(60.0, (float) $dividendSweepLine->debit, 0.001, 'A credit-balance 3200 leaf must be zeroed with a DEBIT sweep line, not assumed debit-normal.');
        $this->assertEqualsWithDelta(0.0, (float) $dividendSweepLine->credit, 0.001);

        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);
        $reLines = $lines->where('account_id', $retainedEarnings->id);
        $reNet = (float) $reLines->sum('credit') - (float) $reLines->sum('debit');
        // 300 (profit) + 60 (dividend refund credited back) = 360.
        $this->assertEqualsWithDelta(360.0, $reNet, 0.001, 'A dividend refund (credit balance on 3200) must INCREASE Retained Earnings, not decrease it.');
    }

    // ── (4) Two companies closing the SAME year — scoping ──────────────────────────────────────

    public function test_two_companies_close_the_same_year_independently(): void
    {
        [$companyA, $branchA] = $this->makeEngineOnCompany();
        [$companyB, $branchB] = $this->makeEngineOnCompany();

        $this->lockAllMonths($companyA, 2026);
        $this->lockAllMonths($companyB, 2026);

        $this->postPlAndAr($companyA, $branchA, 2026, income: 500, expense: 200);
        $this->postDividendPayment($companyA, $branchA, 2026, 100);

        $this->postPlAndAr($companyB, $branchB, 2026, income: 900, expense: 100);
        $this->postDividendPayment($companyB, $branchB, 2026, 700);

        $resultA = $this->service()->run($companyA->id, 2026, null);
        $resultB = $this->service()->run($companyB->id, 2026, null);

        $this->assertTrue($resultA['success']);
        $this->assertTrue($resultB['success']);
        $this->assertEqualsWithDelta(300.0, $resultA['net_profit'], 0.001);
        $this->assertEqualsWithDelta(800.0, $resultB['net_profit'], 0.001);
        $this->assertNotSame($resultA['transaction']->id, $resultB['transaction']->id);

        $linesA = JournalEntry::withoutGlobalScopes()->where('transaction_id', $resultA['transaction']->id)->get();
        $linesB = JournalEntry::withoutGlobalScopes()->where('transaction_id', $resultB['transaction']->id)->get();
        $this->assertEqualsWithDelta((float) $linesA->sum('debit'), (float) $linesA->sum('credit'), 0.001);
        $this->assertEqualsWithDelta((float) $linesB->sum('debit'), (float) $linesB->sum('credit'), 0.001);

        // No cross-company leakage: company A's YEC lines only touch company A's accounts.
        $dividendsA = $this->resolver()->resolve('DIVIDENDS_PAID', $companyA->id);
        $dividendsB = $this->resolver()->resolve('DIVIDENDS_PAID', $companyB->id);
        $this->assertNull($linesA->firstWhere('account_id', $dividendsB->id));
        $this->assertNull($linesB->firstWhere('account_id', $dividendsA->id));
    }

    // ── (5) Late dividend posted into an already-closed year: PeriodGuard shift behaviour ─────

    public function test_late_dividend_posted_after_close_shifts_into_next_open_year_and_reclose_is_still_a_no_op(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);

        $close2026 = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($close2026['success']);
        $this->assertEqualsWithDelta(300.0, $close2026['net_profit'], 0.001);

        // A dividend dated INSIDE 2026 (Dec 20), posted AFTER 2026 has already been closed (all
        // months locked). Posted via the real engine seam (PostingService), not a raw fixture, so
        // PeriodGuard's shift-on-locked-period behaviour actually fires.
        $dividends = $this->resolver()->resolve('DIVIDENDS_PAID', $company->id);
        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        $draft = new \App\Services\Accounting\DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: Carbon::create(2026, 12, 20),
            narration: 'Late dividend payment, posted after 2026 close',
            lines: [
                new \App\Services\Accounting\LineDraft(purposeCode: '', accountId: $dividends->id, side: 'debit', amount: 40.0, currency: 'KWD', originalAmount: 40.0, exchangeRate: 1.0, transactionType: 'JV', description: 'late dividend'),
                new \App\Services\Accounting\LineDraft(purposeCode: '', accountId: $bankAccount->id, side: 'credit', amount: 40.0, currency: 'KWD', originalAmount: 40.0, exchangeRate: 1.0, transactionType: 'JV', description: 'late dividend'),
            ],
            idempotencyKey: 'late-div:'.$company->id.':'.uniqid(),
            userId: null,
            allowLockedPeriods: false,
        );

        $posted = app(\App\Services\Accounting\PostingService::class)->post($draft, null);
        $this->assertNotNull($posted->transaction);

        $line = JournalEntry::withoutGlobalScopes()->where('transaction_id', $posted->transaction->id)->where('account_id', $dividends->id)->first();
        $this->assertNotNull($line);
        $this->assertSame(2026, (int) Carbon::parse($line->transaction_date)->format('Y'), 'transaction_date (docDate) must remain 2026, the date it was actually dated for.');
        $this->assertSame(2027, (int) Carbon::parse($line->posting_date)->format('Y'), 'posting_date must be SHIFTED to 2027 (the next open period) since 2026 is locked and no override was requested.');

        // Re-closing 2026 must still be a pure no-op — the late dividend landed in 2027's
        // posting_date bucket, so 2026's own YEC query (COALESCE(posting_date, transaction_date))
        // never sees it.
        $reclose2026 = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($reclose2026['already_closed']);
        $this->assertSame($close2026['transaction']->id, $reclose2026['transaction']->id);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('doc_type', 'YEC')->whereYear('transaction_date', 2026)->count());

        // The 2027 statement (unclosed) must show the late dividend as 2027's own dividend
        // movement -- proving the shift is picked up correctly downstream by the read layer too.
        $statement2027 = $this->equity()->generate($company->id, 2027);
        $this->assertEqualsWithDelta(40.0, $statement2027['dividends_paid_this_year'], 0.001, 'The late dividend, shifted to 2027 posting_date, must appear as 2027 dividend movement in the equity statement, not disappear or double-count into 2026.');
    }

    // ── (6) T6: capital injection + profit + dividends + OBE residual, full reconciliation ────

    public function test_full_component_reconciliation_with_capital_profit_dividends_and_obe(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();

        // Opening Balance Equity residual, posted BEFORE the fiscal year even starts (2025), so
        // it shows up purely as OPENING equity for 2026, not movement.
        $obe = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '3300')->firstOrFail();
        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $preDate = Carbon::create(2025, 6, 1);
        $preTxn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 250, 'description' => 'OBE residual fixture', 'reference_type' => 'Payment',
            'reference_number' => 'OBE-'.uniqid(), 'name' => 'OBE residual fixture', 'transaction_date' => $preDate, 'posting_date' => $preDate,
            'doc_type' => 'JV', 'doc_year' => 2025, 'posting_status' => 'posted',
            'total_debit' => 250, 'total_credit' => 250, 'idempotency_key' => uniqid('obe:'),
        ]);
        $this->makeLine($preTxn, $company, $branch, $bank, 250, 0, $preDate);
        $this->makeLine($preTxn, $company, $branch, $obe, 0, 250, $preDate);

        $this->lockAllMonths($company, 2026);
        $capital = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '3100')->firstOrFail();
        $capDate = Carbon::create(2026, 2, 1);
        $capTxn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 1200, 'description' => 'Capital injection', 'reference_type' => 'Payment',
            'reference_number' => 'CAP-'.uniqid(), 'name' => 'Capital injection', 'transaction_date' => $capDate, 'posting_date' => $capDate,
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'posted',
            'total_debit' => 1200, 'total_credit' => 1200, 'idempotency_key' => uniqid('cap:'),
        ]);
        $this->makeLine($capTxn, $company, $branch, $bank, 1200, 0, $capDate);
        $this->makeLine($capTxn, $company, $branch, $capital, 0, 1200, $capDate);

        $this->postPlAndAr($company, $branch, 2026, income: 700, expense: 250);
        $this->postDividendPayment($company, $branch, 2026, 120);

        $close = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($close['success']);

        $statement = $this->equity()->generate($company->id, 2026);
        $this->assertEqualsWithDelta(250.0, $statement['components']['opening_balance_equity']['opening'], 0.001);
        $this->assertEqualsWithDelta(1200.0, $statement['components']['capital']['movement'], 0.001);
        $this->assertEqualsWithDelta(450.0, $statement['net_profit'], 0.001);
        $this->assertEqualsWithDelta(120.0, $statement['dividends_paid_this_year'], 0.001);

        $this->assertTrue($statement['checks']['ties_to_next_year_opening'], 'Full multi-component year must reconcile to the fils post-close.');
        $this->assertEqualsWithDelta(0.0, $statement['checks']['difference'], 0.001);

        // Cross-check against the balance sheet's own equity total via TrialBalanceService
        // directly (Equity root subtotal), independent of the report service's own arithmetic.
        $nextYearOpening = $this->trialBalance()->getOpeningBalances($company->id, Carbon::create(2027, 1, 1));
        $equityLeafCodes = ['3100', '3200', '3300', '3400'];
        $bsEquityTotal = 0.0;
        foreach ($equityLeafCodes as $code) {
            $acct = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', $code)->firstOrFail();
            $row = $nextYearOpening[$acct->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
            $bsEquityTotal += (float) $row['opening_credit'] - (float) $row['opening_debit'];
        }
        $this->assertEqualsWithDelta($bsEquityTotal, $statement['closing_equity_total'], 0.001, 'Statement closing equity total must reconcile to the fils against a directly-computed balance-sheet equity total (independent derivation from raw ledger, bypassing the report service entirely).');
    }
}
