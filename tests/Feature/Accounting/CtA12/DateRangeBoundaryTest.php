<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA12;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TrialBalanceService;
use App\Support\ReportDateRange;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A12 — the last day of a report range.
 *
 * A report asked for "1 September to 30 September" was built as
 * `whereBetween('transaction_date', [$from, $to])` with `$to = '2026-09-30'` against a **datetime**
 * column. MySQL widens the bare date to `'2026-09-30 00:00:00'`, so everything timed after midnight
 * on the last day was silently dropped.
 *
 * ── The fixture is the boundary, per prove-the-defect-not-the-fix ─────────────────────────────
 * Every case here sits ON an edge. A document at midday proves nothing: it survives both the broken
 * and the fixed form, which is exactly how this defect stayed invisible for so long.
 *
 *   A  2026-09-01 00:00:00   first day, midnight     -> MUST be included  (the LOWER bound, which a
 *                                                       careless fix to the upper bound can break)
 *   B  2026-09-15 12:00:00   mid-range               -> MUST be included  (the control that a
 *                                                       too-narrow build fails)
 *   C  2026-09-30 23:59:00   last day, late          -> MUST be included  (THE DEFECT)
 *   D  2026-10-01 00:00:00   one second past the end -> MUST be EXCLUDED  (the control that a
 *                                                       too-wide "fix" fails)
 *
 * D is not decoration. A fix that widened the upper bound to `$to + 1 day` instead of end-of-day
 * passes A, B and C and is still wrong; only D catches it.
 */
class DateRangeBoundaryTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private const RANGE_FROM = '2026-09-01';

    private const RANGE_TO = '2026-09-30';

    private int $companyId;

    private int $branchId;

    private int $agentId;

    /** @var array<string, int> label => transaction id */
    private array $txn = [];

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $this->branchId = (int) Branch::factory()->create([
            'company_id' => $this->companyId, 'user_id' => $branchOwner->id,
        ])->id;

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $this->agentId = (int) Agent::factory()->create([
            'branch_id' => $this->branchId, 'user_id' => $agentUser->id, 'type_id' => $agentType->id,
        ])->id;

        session(['company_id' => $this->companyId]);
        (new SystemAccountsSeeder)->run();

        // DELIBERATELY NOT trackCompanyForInvariants(). This class seeds documents that do NOT
        // balance, on purpose — an unbalanced document is the exact population
        // findUnbalancedTransactions() exists to surface, and it is the one whose loss at a range
        // boundary is worst (a missing unbalanced document reads as a CLEAN period, not as a
        // missing number). The tearDown invariant suite would assert that population out of
        // existence and leave this class proving nothing. Every other accounting test still tracks;
        // this is the one place where the fixture IS the violation.

        // Four documents, one per boundary instant. Each is deliberately UNBALANCED by 5.000 so
        // that findUnbalancedTransactions() has something to find — the integrity panel on the
        // trial-balance screen is the sharpest instance of this defect, because a document it
        // misses reads as "clean" rather than as a missing number.
        $this->txn['A_first_midnight'] = $this->unbalancedDocument('2026-09-01 00:00:00');
        $this->txn['B_mid'] = $this->unbalancedDocument('2026-09-15 12:00:00');
        $this->txn['C_last_late'] = $this->unbalancedDocument('2026-09-30 23:59:00');
        $this->txn['D_past_end'] = $this->unbalancedDocument('2026-10-01 00:00:00');
    }

    /**
     * A document whose journal lines do not balance, dated at an exact instant.
     *
     * `posting_date` is left NULL on purpose: that is the LEGACY shape, and it is the shape the
     * defect bites. `COALESCE(posting_date, transaction_date)` returns a DATE at midnight when
     * `posting_date` is set (which survives a bare upper bound by luck) and the raw datetime when
     * it is NULL (which does not). Setting it here would make the fixture unable to produce the
     * defect at all.
     */
    private function unbalancedDocument(string $at): int
    {
        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => 100.0, 'description' => 'boundary',
            'reference_type' => 'Invoice', 'reference_number' => 'A12-'.substr(uniqid(), -8),
            'name' => 'boundary', 'transaction_date' => $at,
            'posting_date' => null,
            'total_debit' => 100.0, 'total_credit' => 95.0,
        ]);

        foreach ([[$this->accountByCode('1351')->id, 100.0, 0.0], [$this->accountByCode('4120')->id, 0.0, 95.0]] as [$accountId, $dr, $cr]) {
            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
                'account_id' => $accountId, 'transaction_date' => $at,
                'description' => 'boundary', 'debit' => $dr, 'credit' => $cr,
                'name' => 'party', 'type' => $dr > 0 ? 'receivable' : 'income',
                'currency' => 'KWD', 'exchange_rate' => 1.0, 'amount' => max($dr, $cr),
                'voucher_number' => 'A12',
            ]);
        }

        return (int) $txn->id;
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE HELPER ITSELF
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_bare_date_upper_bound_is_widened_to_the_end_of_that_day(): void
    {
        $this->assertSame('2026-09-30 23:59:59', ReportDateRange::end('2026-09-30')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 00:00:00', ReportDateRange::start('2026-09-01')->format('Y-m-d H:i:s'));
    }

    /**
     * The trap the coordinator flagged: `->endOfDay()` on a bound that already carries a meaningful
     * time OVERWRITES it. A caller asking for "up to noon" must still get noon.
     */
    public function test_an_upper_bound_that_already_carries_a_time_is_left_alone(): void
    {
        $this->assertSame(
            '2026-09-15 12:00:00',
            ReportDateRange::end('2026-09-15 12:00:00')->format('Y-m-d H:i:s'),
            'a deliberate intraday bound must not be silently widened to the whole day'
        );

        $this->assertSame(
            '2026-09-15 12:00:00',
            ReportDateRange::end(Carbon::parse('2026-09-15 12:00:00'))->format('Y-m-d H:i:s')
        );
    }

    /** A Carbon at exactly midnight IS the defect when used as an upper bound — see the class docblock. */
    public function test_a_carbon_at_exactly_midnight_is_treated_as_a_bare_date(): void
    {
        $this->assertSame(
            '2026-09-30 23:59:59',
            ReportDateRange::end(Carbon::parse('2026-09-30'))->format('Y-m-d H:i:s')
        );
    }

    public function test_null_and_empty_pass_through_so_an_optional_filter_stays_optional(): void
    {
        $this->assertNull(ReportDateRange::start(null));
        $this->assertNull(ReportDateRange::end(null));
        $this->assertNull(ReportDateRange::end('  '));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE DEFECT, AT EACH FIXED SITE
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A12-UNBALANCED ─────────────────────────────────────────────────────────
     * The trial-balance screen's integrity panel. `ReportController::trialBalance()` used to pass
     * `Carbon::parse($dateTo)` — midnight — straight into `findUnbalancedTransactions()`, while the
     * totals beside it came from `generate()`, which normalises its own bounds. So the two halves of
     * one screen were filtered to different ranges, and the panel could report a clean day that had
     * an unbalanced document on it.
     *
     * Reverting the bounds to `Carbon::parse()` makes C disappear from this assertion.
     */
    public function test_an_unbalanced_document_late_on_the_last_day_is_still_found(): void
    {
        $found = $this->unbalancedIds(
            ReportDateRange::start(self::RANGE_FROM),
            ReportDateRange::end(self::RANGE_TO)
        );

        $this->assertContains($this->txn['C_last_late'], $found, 'THE DEFECT: 23:59:00 on the last day of the range was dropped');
        $this->assertContains($this->txn['A_first_midnight'], $found, 'the lower bound must not be broken while fixing the upper');
        $this->assertContains($this->txn['B_mid'], $found);
        $this->assertNotContains($this->txn['D_past_end'], $found, 'the fix must not widen the range past its end');
        $this->assertCount(3, $found);
    }

    /**
     * The same query with the OLD bounds, asserted to be broken. This is the defect pinned as a
     * fact rather than described in a comment: if someone "simplifies" ReportDateRange back to a
     * plain parse, this test still passes and the one above fails — so the pair cannot both be
     * green unless the fix is real.
     */
    public function test_the_old_bound_form_demonstrably_loses_the_last_day(): void
    {
        $old = $this->unbalancedIds(Carbon::parse(self::RANGE_FROM), Carbon::parse(self::RANGE_TO));

        $this->assertNotContains(
            $this->txn['C_last_late'],
            $old,
            'fixture precondition: the un-normalised bound must still lose the late document, '
                .'otherwise the test above is vacuous'
        );
        $this->assertContains($this->txn['A_first_midnight'], $old);
        $this->assertCount(2, $old, 'the old form finds only A and B — that is the bug, measured');
    }

    /**
     * ── MUTATION PROOF M-A12-JOURNAL ────────────────────────────────────────────────────────────
     * `JournalEntryController::exportPdf()` and `AccountingController::filterLedgers()` both filter
     * `journal_entries.transaction_date` (datetime) with bare request dates. Asserted on the real
     * column through the real query builder, not on a PHP comparison.
     */
    public function test_journal_lines_late_on_the_last_day_survive_the_range(): void
    {
        $fixed = $this->journalLineCount(
            ReportDateRange::start(self::RANGE_FROM),
            ReportDateRange::end(self::RANGE_TO)
        );
        $old = $this->journalLineCount(self::RANGE_FROM, self::RANGE_TO);

        // Two lines per document; A, B and C are in range.
        $this->assertSame(6, $fixed, 'the fixed bound keeps both lines of the last-day document');
        $this->assertSame(4, $old, 'fixture precondition: the bare bound loses both lines of C');
        $this->assertGreaterThan($old, $fixed, 'the fix must strictly recover lines, not merely differ');
    }

    /**
     * `SupplierController::ledgerByDateRange()` — `tasks.supplier_pay_date` is also a datetime.
     * Same boundary, different table and column, so the fix is proved where it was applied rather
     * than assumed to generalise.
     */
    public function test_a_task_paid_late_on_the_last_day_is_still_in_the_supplier_ledger(): void
    {
        $supplierId = (int) Supplier::factory()->create()->id;

        foreach (['2026-09-01 00:00:00', '2026-09-30 23:59:00', '2026-10-01 00:00:00'] as $at) {
            $id = (int) Task::factory()->create([
                'company_id' => $this->companyId, 'agent_id' => $this->agentId,
                'supplier_id' => $supplierId, 'type' => 'hotel', 'status' => 'issued',
            ])->id;
            DB::table('tasks')->where('id', $id)->update(['supplier_pay_date' => $at]);
        }

        $fixed = DB::table('tasks')->where('supplier_id', $supplierId)
            ->whereBetween('supplier_pay_date', [ReportDateRange::start(self::RANGE_FROM), ReportDateRange::end(self::RANGE_TO)])
            ->count();

        $old = DB::table('tasks')->where('supplier_id', $supplierId)
            ->whereBetween('supplier_pay_date', [self::RANGE_FROM, self::RANGE_TO])
            ->count();

        $this->assertSame(2, $fixed, 'the task paid at 23:59:00 on the 30th belongs in a 1-30 September ledger');
        $this->assertSame(1, $old, 'fixture precondition: the bare bound sees only the first-day task');
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    /** @return int[] */
    private function unbalancedIds(Carbon $from, Carbon $to): array
    {
        return app(TrialBalanceService::class)
            ->findUnbalancedTransactions($this->companyId, $from, $to)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function journalLineCount(string|Carbon|null $from, string|Carbon|null $to): int
    {
        return JournalEntry::where('company_id', $this->companyId)
            ->whereBetween('transaction_date', [$from, $to])
            ->count();
    }
}
