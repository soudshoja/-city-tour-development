<?php

namespace Tests\Support;

use App\Services\TrialBalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * C1 — the invariant test suite (Accounting Gap/11-technical-implementation-plan.md
 * §"Cross-cutting foundations", C1).
 *
 * A trait of reusable ledger-health assertions, deliberately independent of
 * PostingService's own internals so it can catch mistakes PostingService
 * itself might make. Mix this into any TestCase and call the assertions
 * explicitly, or extend AccountingTestCase (tests/Support/AccountingTestCase.php)
 * which wires assertAccountingInvariants() into tearDown() automatically —
 * "after any operation exercised by any test in the accounting suite, the
 * acting company's trial balance still balances," whether or not the test
 * author remembered to assert it themselves.
 *
 * All queries run against whatever connection DB::table() resolves to at
 * test time (config('database.default'), which phpunit.xml pins to
 * 'mysql_testing' during the test run) — never a hardcoded connection name,
 * so these assertions are safe to reuse from any test regardless of which
 * connection its models bind to.
 */
trait AccountingInvariants
{
    /**
     * assertLedgerBalanced — per-transaction Σdebit = Σcredit via a raw
     * query, independent of TrialBalanceService. Every journal_entries
     * group sharing a transaction_id must balance to within the
     * PostingService header tolerance (< 0.0005 — file 11 R2/BUG-C1,
     * pipeline step 4), matching the file 11 C1 helper
     * `assertTransactionBalanced` generalised to "every group for this
     * company" when no single $transactionId is given.
     *
     * Deliberately excludes rows with transaction_id IS NULL (orphans) —
     * those are covered separately by assertNoOrphanLines(), and legacy
     * orphans are explicitly tolerated in P1 (NOT NULL enforcement is P3).
     */
    protected function assertLedgerBalanced(int $companyId, ?int $transactionId = null): void
    {
        $query = DB::table('journal_entries')
            ->select('transaction_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotNull('transaction_id')
            ->groupBy('transaction_id');

        if ($transactionId !== null) {
            $query->where('transaction_id', $transactionId);
        }

        $groups = $query->get();

        foreach ($groups as $group) {
            $diff = abs((float) $group->total_debit - (float) $group->total_credit);

            Assert::assertLessThan(
                0.0005,
                $diff,
                "Transaction #{$group->transaction_id} is unbalanced for company {$companyId}: "
                    ."debit={$group->total_debit} credit={$group->total_credit} (diff={$diff})."
            );
        }
    }

    /**
     * assertNoOrphanLines — no journal_entries row with transaction_id NULL
     * for this company. The column itself stays nullable through P1 (prod
     * already has orphans; NOT NULL enforcement is a P3 backfill step), but
     * the engine must always set it on every line it writes — so any orphan
     * appearing *during a test* is a PostingService defect, not tolerated
     * legacy debt.
     */
    protected function assertNoOrphanLines(int $companyId): void
    {
        $orphanCount = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNull('transaction_id')
            ->count();

        Assert::assertSame(
            0,
            $orphanCount,
            "Found {$orphanCount} orphaned journal_entries (transaction_id IS NULL) for company {$companyId}."
        );
    }

    /**
     * assertNoNegativeAmounts — no negative debit/credit/amount on any
     * non-deleted journal_entries row for this company (blueprint §3 / MF-11
     * rule b: amount >= 0, enforced per-line by PostingService as
     * NonNegativeAmountException).
     */
    protected function assertNoNegativeAmounts(int $companyId): void
    {
        $negativeCount = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('debit', '<', 0)
                    ->orWhere('credit', '<', 0)
                    ->orWhere('amount', '<', 0);
            })
            ->count();

        Assert::assertSame(
            0,
            $negativeCount,
            "Found {$negativeCount} journal_entries rows with a negative debit/credit/amount for company {$companyId}."
        );
    }

    /**
     * assertTenantConsistency — every journal_entries row for this company
     * points at an account that itself belongs to the same company
     * (T7.1 / check 7.1's cross-tenant-line shape).
     */
    protected function assertTenantConsistency(int $companyId): void
    {
        $crossTenantCount = DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereColumn('je.company_id', '!=', 'a.company_id')
            ->count();

        Assert::assertSame(
            0,
            $crossTenantCount,
            "Found {$crossTenantCount} journal_entries rows for company {$companyId} whose account belongs to a "
                .'different company.'
        );
    }

    /**
     * assertCompanyLedgerBalanced — the aggregate trial-balance check, reusing
     * the verified-correct TrialBalanceService::generate(...)['totals']['is_balanced']
     * (app/Services/TrialBalanceService.php:57). Its threshold is
     * TrialBalanceService's own < 0.001 — intentionally looser than the
     * 0.0005 per-transaction check in assertLedgerBalanced() above. The two
     * are independently specified at different granularities (per-document
     * vs. company-aggregate) and file 11 explicitly directs against
     * homogenizing them.
     */
    protected function assertCompanyLedgerBalanced(int $companyId, ?Carbon $from = null, ?Carbon $to = null): void
    {
        $from = $from ?? Carbon::create(2000, 1, 1);
        $to = $to ?? Carbon::now()->addYears(10);

        $result = app(TrialBalanceService::class)->generate($companyId, $from, $to);

        Assert::assertTrue(
            (bool) ($result['totals']['is_balanced'] ?? false),
            "Trial balance for company {$companyId} does not balance: ".json_encode($result['totals'] ?? [])
        );
    }

    /**
     * Convenience wrapper running the full C1 invariant suite in one call —
     * every acceptance test should call this (or rely on AccountingTestCase's
     * tearDown hook) after exercising PostingService.
     */
    protected function assertAccountingInvariants(int $companyId): void
    {
        $this->assertLedgerBalanced($companyId);
        $this->assertNoOrphanLines($companyId);
        $this->assertNoNegativeAmounts($companyId);
        $this->assertTenantConsistency($companyId);
        $this->assertCompanyLedgerBalanced($companyId);
    }
}
