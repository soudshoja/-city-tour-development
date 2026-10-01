<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\YearEndCloseService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * XBRL-X9r (PLAN.md X9r, L15, L25; H2 N-B2): the shared fixture and the direct-SQL oracles for the
 * closed-year tests ({@see X9rClosedYearTest}, {@see X9rPriorPeriodAdjustmentTest}).
 *
 * Real CoaSeeder chart, real SystemAccountsSeeder mapping, real PostingService, real
 * YearEndCloseService, engine ON. Fixture literals (all KWD):
 *
 *   FY2025  Dr 1201 bank 7,000 / Cr 4133 income 7,000 (2025-06-15)
 *           Dr 5222 expense 2,000 / Cr 1201 bank 2,000 (2025-06-20)   -> FY2025 profit 5,000.000
 *           all 12 months locked, YEC posted (RE credited 5,000.000)
 *   audit   Dr 1201 bank 1,000 / Cr 4133 income 1,000 dated 2025-12-15 -> corrected 6,000.000
 *
 * Every oracle is direct SQL written in this file with its own LedgerSource conjunction
 * (`doc_type IS NOT NULL AND posting_date IS NOT NULL`), never a call to the service under test.
 */
abstract class X9rLedgerTestCase extends AccountingTestCase
{
    protected Company $company;

    protected Branch $branch;

    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);

        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
        (new SystemAccountsSeeder)->run();

        $this->accountant = User::factory()->create(['role_id' => Role::ACCOUNTANT]);
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->accountant->id]);

        Artisan::call('accounting:engine', ['company' => $this->company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($this->company->id);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    // ── fixture helpers ─────────────────────────────────────────────────────────────────────

    protected function acc(string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
    }

    protected function re(): Account
    {
        return app(AccountResolver::class)->resolve('RETAINED_EARNINGS', $this->company->id);
    }

    protected function line(Account $account, string $side, float $amount): LineDraft
    {
        return new LineDraft(
            purposeCode: '',
            accountId: $account->id,
            side: $side,
            amount: $amount,
            currency: 'KWD',
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'X9R',
        );
    }

    /** @param list<LineDraft> $lines */
    protected function draft(string $docType, string $date, array $lines, string $key, ?string $subType = null): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            docType: $docType,
            subType: $subType,
            docDate: Carbon::parse($date),
            narration: "x9r {$key}",
            lines: $lines,
            idempotencyKey: "x9r:{$key}",
            userId: $this->accountant->id,
        );
    }

    protected function postJv(string $date, Account $debit, Account $credit, float $amount, string $key): Transaction
    {
        return app(PostingService::class)->post(
            $this->draft('JV', $date, [$this->line($debit, 'debit', $amount), $this->line($credit, 'credit', $amount)], $key),
            $this->accountant->id,
        )->transaction;
    }

    /** The corrected audit adjustment of the acceptance criteria: 1,000.000 of income dated 15 Dec 2025. */
    protected function auditAdjustment(): DocumentDraft
    {
        return $this->draft('JV', '2025-12-15', [
            $this->line($this->acc('1201'), 'debit', 1000.0),
            $this->line($this->acc('4133'), 'credit', 1000.0),
        ], 'audit-adjustment');
    }

    protected function setPeriod(int $year, int $month, string $status): void
    {
        AccountingPeriod::query()->updateOrCreate(
            ['company_id' => $this->company->id, 'year' => $year, 'month' => $month],
            ['status' => $status],
        );
    }

    protected function periodStatus(int $year, int $month): string
    {
        return (string) (AccountingPeriod::query()->where('company_id', $this->company->id)
            ->where('year', $year)->where('month', $month)->value('status') ?? 'open (no row)');
    }

    /** FY2025 trading (profit 5,000.000), all 12 months locked, YEC run. Returns the YEC. */
    protected function closedFy2025(): Transaction
    {
        $this->postJv('2025-06-15', $this->acc('1201'), $this->acc('4133'), 7000.0, 'fy25-sale');
        $this->postJv('2025-06-20', $this->acc('5222'), $this->acc('1201'), 2000.0, 'fy25-cost');

        for ($m = 1; $m <= 12; $m++) {
            $this->setPeriod(2025, $m, AccountingPeriod::STATUS_LOCKED);
        }

        $result = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);
        $this->assertTrue($result['success'], 'fixture YEC blocked: '.implode(' | ', $result['blocking']));
        $this->assertFalse($result['already_closed']);
        $this->assertEqualsWithDelta(5000.0, (float) $result['net_profit'], 0.0005, 'fixture FY2025 profit');

        return $result['transaction'];
    }

    // ── oracles: direct SQL with the test's OWN LedgerSource conjunction (L2) ────────────────

    /** Engine rows only: the header carries doc_type AND posting_date (re-written here, never imported). */
    protected function engineLines(): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $this->company->id)
            ->whereNull('je.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereNotNull('t.doc_type')
            ->whereNotNull('t.posting_date');
    }

    /** Σ(credit − debit) in fils over Income + Expenses leaves in [from, to], YEC family and PPA family excluded. */
    protected function oracleProfitFils(string $from, string $to): int
    {
        return (int) $this->engineLines()
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->whereIn('root.name', ['Income', 'Expenses'])
            ->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereNotIn('t.doc_type', ['YEC', 'PPA'])
            ->whereRaw("NOT (t.doc_type = 'REV' AND t.sub_type IN ('YEC', 'PPA'))")
            ->selectRaw('CAST(ROUND(COALESCE(SUM(je.credit - je.debit), 0) * 1000) AS SIGNED) AS f')
            ->value('f');
    }

    /** Σ(credit − debit) in fils of every row on one account dated on or before $asOf (all documents). */
    protected function oracleCreditBalanceFils(Account $account, string $asOf): int
    {
        return (int) $this->engineLines()
            ->where('je.account_id', $account->id)
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $asOf.' 23:59:59')
            ->selectRaw('CAST(ROUND(COALESCE(SUM(je.credit - je.debit), 0) * 1000) AS SIGNED) AS f')
            ->value('f');
    }

    /** Σ over every Income/Expenses leaf, all documents, through $asOf: the profit no YEC has swept. */
    protected function oracleUnsweptFils(string $asOf): int
    {
        return (int) $this->engineLines()
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->whereIn('root.name', ['Income', 'Expenses'])
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $asOf.' 23:59:59')
            ->selectRaw('CAST(ROUND(COALESCE(SUM(je.credit - je.debit), 0) * 1000) AS SIGNED) AS f')
            ->value('f');
    }

    /** Net (credit − debit) in fils of one document's lines on one account. */
    protected function docLineFils(int $transactionId, Account $account): int
    {
        return (int) DB::table('journal_entries')
            ->where('transaction_id', $transactionId)
            ->where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->selectRaw('CAST(ROUND(COALESCE(SUM(credit - debit), 0) * 1000) AS SIGNED) AS f')
            ->value('f');
    }

    /**
     * Runs a command through Artisan::call() (L21, memory test-oracle-exception-type: never a
     * PendingCommand chain, which asserts the exit code before the output). Returns the output
     * with whitespace collapsed (Command::error() wraps at terminal width) and the exit code; the
     * caller asserts the OUTPUT first.
     *
     * @return array{0: string, 1: int}
     */
    protected function runCommand(string $command, array $arguments): array
    {
        $this->withoutMockingConsoleOutput();
        $exit = Artisan::call($command, $arguments);

        return [trim((string) preg_replace('/\s+/', ' ', Artisan::output())), $exit];
    }

    /** The YEC documents dated in $year, oldest first, straight from the table. */
    protected function yecRows(int $year): \Illuminate\Support\Collection
    {
        return DB::table('transactions')
            ->where('company_id', $this->company->id)
            ->where('doc_type', 'YEC')
            ->whereYear('transaction_date', $year)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }

    /** The live (not soft-deleted) reversal of a transaction, straight from the table. */
    protected function reversalOf(int $transactionId): ?object
    {
        return DB::table('transactions')
            ->where('reversal_of_transaction_id', $transactionId)
            ->whereNull('deleted_at')
            ->first();
    }

    /** accounting_audit_log rows of this company for an action, oldest first. */
    protected function auditRows(string $action): \Illuminate\Support\Collection
    {
        return DB::table('accounting_audit_log')
            ->where('company_id', $this->company->id)
            ->where('action', $action)
            ->orderBy('id')
            ->get();
    }

    /** A cheap fingerprint of everything the procedure could write, for the idempotency test. */
    protected function ledgerFingerprint(): array
    {
        return [
            'transactions' => DB::table('transactions')->where('company_id', $this->company->id)->count(),
            'journal_entries' => DB::table('journal_entries')->where('company_id', $this->company->id)->count(),
            'audit_rows' => DB::table('accounting_audit_log')->where('company_id', $this->company->id)->count(),
            'periods' => DB::table('accounting_periods')->where('company_id', $this->company->id)
                ->orderBy('year')->orderBy('month')->get(['year', 'month', 'status'])
                ->map(fn ($p) => "{$p->year}-{$p->month}:{$p->status}")->implode(','),
        ];
    }
}
