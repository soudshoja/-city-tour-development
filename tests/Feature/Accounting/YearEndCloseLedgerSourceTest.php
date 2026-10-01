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
use App\Services\Accounting\YearEndCloseService;
use App\Services\Accounting\BalanceSheetService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * XBRL-X1, verifier finding M-1: the year-end close must sweep the SAME ledger source the reports
 * read. On an engine-ON company that still carries legacy rows (City Travelers company 1), the
 * close used to sum engine and legacy rows together, so it moved legacy profit into Retained
 * Earnings that no report had counted as profit. After X1 the balance sheet foots by construction,
 * so that misallocation was silent: Retained Earnings 560 and a "not yet closed" line of 240,
 * where the engine ledger says 500 and 300.
 *
 * Real CoaSeeder chart, real SystemAccountsSeeder mapping, real {@see YearEndCloseService::run()},
 * engine ON. Every expectation is a pinned literal worked from the fixture:
 *
 *   FY2025 engine   Dr AR 700 / Cr 4133 Service Fee Income 700; Dr 5222 Bank Charges 200 / Cr AR 200
 *                   -> engine FY2025 profit 500
 *   FY2025 legacy   one row shape per test (doc_type NULL, posting_date NULL): must NOT be read
 *   FY2026 engine   Dr AR 450 / Cr 4133 450; Dr 5222 150 / Cr AR 150 -> FY2026 profit 300
 */
class YearEndCloseLedgerSourceTest extends AccountingTestCase
{
    private Company $company;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);

        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $owner->id]);

        Artisan::call('accounting:engine', ['company' => $this->company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($this->company->id);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function acc(string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
    }

    private function resolve(string $purpose): Account
    {
        return app(AccountResolver::class)->resolve($purpose, $this->company->id);
    }

    /**
     * One document. $legacy = the legacy header shape (doc_type NULL, posting_date NULL on header
     * and lines), which LedgerSource excludes while the engine is ON.
     *
     * @param  list<array{0: Account, 1: float, 2: float}>  $lines
     */
    private function document(string $date, array $lines, bool $legacy = false): void
    {
        $d = Carbon::parse($date);
        $total = array_sum(array_column($lines, 1));

        $txn = Transaction::forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'entity_id' => $this->company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $total, 'description' => 'x1-yec',
            'reference_type' => 'Invoice', 'reference_number' => 'X1Y-'.uniqid(), 'name' => 'x1-yec',
            'transaction_date' => $d, 'posting_date' => $legacy ? null : $d,
            'doc_type' => $legacy ? null : 'JV', 'doc_year' => $d->year, 'posting_status' => 'posted',
            'total_debit' => $total, 'total_credit' => $total, 'idempotency_key' => uniqid('x1y:'),
        ]);

        foreach ($lines as [$account, $dr, $cr]) {
            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
                'account_id' => $account->id, 'transaction_date' => $d, 'posting_date' => $legacy ? null : $d,
                'description' => 'x1-yec', 'debit' => $dr, 'credit' => $cr, 'name' => $account->name,
                'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => max($dr, $cr),
                'voucher_number' => 'X1Y', 'type_reference_id' => $this->company->id,
            ]);
        }
    }

    private function profit(string $date, float $income, float $expense, bool $legacy = false): void
    {
        $ar = $this->resolve('RECEIVABLE_CONTROL');
        $lines = [[$ar, $income, 0.0], [$this->acc('4133'), 0.0, $income]];

        if ($expense > 0) {
            $lines[] = [$this->acc('5222'), $expense, 0.0];
            $lines[] = [$ar, 0.0, $expense];
        }

        $this->document($date, $lines, $legacy);
    }

    private function engineWorld(): void
    {
        $this->profit('2025-06-15', 700.0, 200.0);
    }

    /** @return array{success: bool, blocking: list<string>, net_profit: ?float, transaction: ?Transaction} */
    private function close(int $year): array
    {
        for ($m = 1; $m <= 12; $m++) {
            AccountingPeriod::create([
                'company_id' => $this->company->id, 'year' => $year, 'month' => $m,
                'status' => AccountingPeriod::STATUS_LOCKED,
            ]);
        }

        return app(YearEndCloseService::class)->run($this->company->id, $year);
    }

    /** Net (credit - debit) of the YEC document's lines on one account. */
    private function yecLine(Transaction $yec, Account $account): float
    {
        return round((float) DB::table('journal_entries')
            ->where('transaction_id', $yec->id)
            ->where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) AS net')
            ->value('net'), 3);
    }

    private function equityLine(array $bs, int $accountId): float
    {
        foreach ($bs['sections']['Equity']['groups'] as $group) {
            foreach ($group['accounts'] as $account) {
                if ($account->id === $accountId) {
                    return (float) $account->balance;
                }
            }
        }

        return 0.0;
    }

    /**
     * The verifier's case. A legacy sale of 60.000 on the income leaf in FY2025 must stay out of
     * the sweep: the close sweeps the engine's 500.000, no more.
     */
    public function test_the_close_sweeps_engine_rows_only(): void
    {
        $this->engineWorld();
        $this->profit('2025-07-01', 60.0, 0.0, legacy: true);

        $result = $this->close(2025);
        $this->assertTrue($result['success'], 'YEC blocked: '.implode(' | ', $result['blocking']));

        $this->assertEqualsWithDelta(
            500.0,
            (float) $result['net_profit'],
            0.0005,
            'YEC net_profit = '.number_format((float) $result['net_profit'], 3).', expected the engine 500.000 '
                .'(560.000 = a legacy row was swept too; 60.000 = the engine rows were dropped)'
        );
        $yec = $result['transaction'];
        $this->assertEqualsWithDelta(-700.0, $this->yecLine($yec, $this->acc('4133')), 0.0005, 'income leaf swept by the engine 700 only');
        $this->assertEqualsWithDelta(200.0, $this->yecLine($yec, $this->acc('5222')), 0.0005, 'expense leaf swept by 200');
        $this->assertEqualsWithDelta(500.0, $this->yecLine($yec, $this->resolve('RETAINED_EARNINGS')), 0.0005, 'Retained Earnings credited 500');

        $this->profit('2026-06-15', 450.0, 150.0);
        $bs = (new BalanceSheetService)->generate($this->company->id, Carbon::parse('2026-12-31'));

        $this->assertEqualsWithDelta(300.0, (float) $bs['net_profit'], 0.0005, 'not-yet-closed line = FY2026 engine profit');
        $this->assertEqualsWithDelta(500.0, $this->equityLine($bs, $this->resolve('RETAINED_EARNINGS')->id), 0.0005, 'Retained Earnings on the sheet');
        $this->assertTrue($bs['totals']['is_balanced']);
    }

    /** A legacy dividend of 40.000 on Dividends Paid in FY2025 must not be swept either. */
    public function test_a_legacy_dividend_is_not_swept(): void
    {
        $this->engineWorld();
        $this->document('2025-09-01', [
            [$this->resolve('DIVIDENDS_PAID'), 40.0, 0.0],
            [$this->resolve('RECEIVABLE_CONTROL'), 0.0, 40.0],
        ], legacy: true);

        $result = $this->close(2025);
        $this->assertTrue($result['success'], 'YEC blocked: '.implode(' | ', $result['blocking']));

        $yec = $result['transaction'];
        $this->assertEqualsWithDelta(0.0, $this->yecLine($yec, $this->resolve('DIVIDENDS_PAID')), 0.0005, 'the YEC swept a LEGACY dividend off Dividends Paid');
        $this->assertEqualsWithDelta(500.0, $this->yecLine($yec, $this->resolve('RETAINED_EARNINGS')), 0.0005, 'Retained Earnings net = 500, no dividend debit');
    }

    /**
     * A legacy residue on the Airline Memo Control (1952) is invisible to every engine-ON report,
     * so it must not block the close. (The ENGINE-row block is pinned by
     * YearEndCloseServiceTest::test_refuses_when_airline_memo_control_is_non_zero.)
     */
    public function test_a_legacy_memo_residue_does_not_block_the_close(): void
    {
        $liabilities = Account::withoutGlobalScopes()->where('company_id', $this->company->id)
            ->whereNull('parent_id')->where('name', 'Liabilities')->firstOrFail();
        $memo = Account::create([
            'company_id' => $this->company->id, 'code' => '1952', 'name' => 'Airline Memo Control', 'level' => 2,
            'is_group' => false, 'currency' => 'KWD', 'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);

        $this->engineWorld();
        $this->document('2025-10-01', [
            [$memo, 0.0, 25.0],
            [$this->resolve('RECEIVABLE_CONTROL'), 25.0, 0.0],
        ], legacy: true);

        $result = $this->close(2025);

        $this->assertSame([], $result['blocking'], 'a LEGACY memo row blocked the close');
        $this->assertTrue($result['success']);
        $this->assertEqualsWithDelta(500.0, (float) $result['net_profit'], 0.0005);
    }
}
