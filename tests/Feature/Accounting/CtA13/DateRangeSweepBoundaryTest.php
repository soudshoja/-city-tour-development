<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA13;

use App\Http\Controllers\Accounting\ReconciliationController;
use App\Http\Controllers\SupplierController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\BankStatementImport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\Reconciliation\BankStatementMatcher;
use App\Services\Accounting\ReconciliationCenterService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A13 — three more sites of the CT-A12 defect, on screens CT-A12's sweep did not reach.
 *
 * CT-A12 found that a bare `Y-m-d` upper bound against a `datetime` column is
 * `<= '2026-09-30 00:00:00'`, and fixed eleven call sites. It missed three more, all of them for
 * the same two reasons: the bound was not built with Carbon (so a reading sweep looking for
 * `Carbon::parse($to)` walked past it), and the predicate was a `DB::raw()` COALESCE (so the census
 * ratchet's string-literal column regex could not see it either). Both causes are closed —
 * see {@see \Tests\Feature\Accounting\CtA12\DateRangeBoundaryRatchetTest}'s CT-A13 section.
 *
 * ── The fixture is the boundary, per prove-the-defect-not-the-fix ─────────────────────────────
 * Every case here sits ON the edge. A document at MIDDAY proves nothing: it survives the broken
 * form and the fixed form alike, which is exactly how this defect stayed invisible.
 *
 *   2026-09-30 00:00:00   the cutoff day, at midnight   -> included by both forms (the control)
 *   2026-09-30 23:59:00   the cutoff day, late          -> THE DEFECT: lost by the old form only
 *   2026-10-01 00:00:00   the day after                 -> excluded by both (the too-wide control)
 *
 * The third row is not decoration. A "fix" that added a day to the bound instead of widening it to
 * the end of the day passes the first two and is still wrong; only the third catches it.
 *
 * Each test asserts the FIXED path through the real production method, and pins the OLD form's
 * loss beside it as a measured fact. The pair cannot both be green unless the fix is real.
 */
class DateRangeSweepBoundaryTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private const CUTOFF = '2026-09-30';

    private const AT_MIDNIGHT = '2026-09-30 00:00:00';

    private const LATE_ON_THE_CUTOFF_DAY = '2026-09-30 23:59:00';

    private const THE_DAY_AFTER = '2026-10-01 00:00:00';

    private int $companyId;

    private int $branchId;

    private int $agentId;

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
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SITE 1 — SupplierController::getTotalDebitCredit()
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A13-SUPPLIER ───────────────────────────────────────────────────────────
     * The supplier ledger's running total, reached at
     * `/total-ledger/{supplierId}/date/{endDate}` — a bare `Y-m-d` straight off the URL, turned
     * into a bound by `new DateTime($endDate)`. \DateTime is why CT-A12's Carbon-shaped sweep
     * walked past it; `DB::raw('COALESCE(posting_date, transaction_date)')` is why the ratchet did.
     *
     * Revert the fix to `$endDate = new DateTime($endDate);` and `totalDebit` drops by 250.000 —
     * the second assertion below is the same query with that exact old bound, asserted to be short.
     */
    public function test_a_supplier_balance_includes_a_line_posted_late_on_the_cutoff_day(): void
    {
        $supplierId = (int) Supplier::factory()->create()->id;
        $taskId = $this->taskFor($supplierId);

        $this->journalLine($taskId, self::AT_MIDNIGHT, 100.0);
        $this->journalLine($taskId, self::LATE_ON_THE_CUTOFF_DAY, 250.0);
        $this->journalLine($taskId, self::THE_DAY_AFTER, 900.0);

        $payload = (new SupplierController)->getTotalDebitCredit($supplierId, self::CUTOFF)->getData(true);

        $this->assertSame(
            350.0,
            round((float) $payload['totalDebit'], 3),
            'THE DEFECT: the line posted at 23:59:00 on the cutoff day belongs in a balance "as of" '
                .'that day. 100.000 means it was dropped; 1250.000 means the bound was widened past '
                .'the end of the day instead of to it.'
        );

        // The old bound, measured. This is the defect pinned as a fact rather than described.
        $old = (float) JournalEntry::whereIn('task_id', [$taskId])
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', new DateTime(self::CUTOFF))
            ->sum('debit');

        $this->assertSame(
            100.0,
            round($old, 3),
            'fixture precondition: `new DateTime(\'2026-09-30\')` must still lose the 23:59 line, '
                .'otherwise the assertion above is vacuous'
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SITE 2 — BankStatementMatcher::reconciliationReport()
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A13-BANKREC ────────────────────────────────────────────────────────────
     * `ledger_balance` is the bank reconciliation's own side of the comparison, and `difference` is
     * the number the screen exists to drive to zero. Its bound was
     * `$import->statement_to?->toDateString()` — a bare `Y-m-d` by construction — so a receipt
     * banked at 16:40 on the statement's own closing day was outside the statement's own period.
     *
     * The consequence is worse than a wrong total: `difference` is what tells an operator whether
     * the account reconciles, and a missing ledger line makes a reconciled account look out by
     * exactly that line's value.
     */
    public function test_the_bank_reconciliation_balance_includes_a_line_posted_late_on_the_closing_day(): void
    {
        $bankAccount = $this->accountByCode('1201');

        $this->bankLine($bankAccount->id, self::AT_MIDNIGHT, 100.0);
        $this->bankLine($bankAccount->id, self::LATE_ON_THE_CUTOFF_DAY, 250.0);
        $this->bankLine($bankAccount->id, self::THE_DAY_AFTER, 900.0);

        $import = BankStatementImport::create([
            'company_id' => $this->companyId,
            'bank_account_id' => (int) $bankAccount->id,
            'file_name' => 'ct-a13.csv',
            'statement_currency' => 'KWD',
            'statement_from' => '2026-09-01',
            'statement_to' => self::CUTOFF,
            'closing_balance' => 350.0,
            'content_hash' => hash('sha256', 'ct-a13'),
            'column_map' => [],
            'status' => BankStatementImport::STATUS_STAGED,
            'imported_by' => null,
        ]);

        $report = app(BankStatementMatcher::class)->reconciliationReport($import);

        $this->assertSame(
            350.0,
            round($report['ledger_balance'], 3),
            'THE DEFECT: the 23:59:00 line on the statement\'s own closing day was outside the '
                .'statement period'
        );

        $this->assertSame(
            0.0,
            round((float) $report['difference'], 3),
            'an account that reconciles must report a zero difference; the missing line made it '
                .'read as out by exactly that line'
        );

        // The old bound, measured.
        $old = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('account_id', $bankAccount->id)
            ->whereNull('deleted_at')
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $import->statement_to->toDateString())
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $this->assertSame(
            100.0,
            round((float) $old->d - (float) $old->c, 3),
            'fixture precondition: the bare `toDateString()` bound must still lose the 23:59 line'
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SITE 3 — Accounting\ReconciliationController::rowDetail()
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A13-RECON-DRILLDOWN ────────────────────────────────────────────────────
     * This one is a DIVERGENCE, not a loss: one screen, one date, two populations.
     *
     * `resolveGridInput()` parsed the request date to midnight and handed the same instant to three
     * consumers that disagree about it. `ReconciliationCenterService::grid()` normalises internally
     * (`bounds()` applies `startOfDay()`/`endOfDay()`), so the GRID counted the whole day;
     * `unmatchedFor()` and `explainGap()` take the instant literally as a `<=` bound, so the
     * DRILL-DOWN stopped at 00:00:00. Click into a figure and the detail did not add up to it.
     *
     * That is the same shape CT-A12 fixed on the trial balance, where `generate()` normalised and
     * `findUnbalancedTransactions()` did not.
     */
    public function test_the_reconciliation_drill_down_covers_the_same_day_the_grid_does(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $resolve = new ReflectionMethod(ReconciliationController::class, 'resolveGridInput');
        [$companyId, $asOf] = $resolve->invoke(app(ReconciliationController::class), Request::create('/x', 'GET', ['date' => self::CUTOFF]));

        $this->assertSame($this->companyId, $companyId);
        $this->assertSame(
            '2026-09-30 23:59:59',
            Carbon::instance($asOf)->format('Y-m-d H:i:s'),
            'THE DEFECT: the drill-down `as of` was midnight while the grid beside it covered the '
                .'whole day'
        );

        // And the divergence itself, through the service the controller hands that value to.
        $bankAccount = $this->accountByCode('1201');
        $this->bankLine($bankAccount->id, self::AT_MIDNIGHT, 100.0);
        $this->bankLine($bankAccount->id, self::LATE_ON_THE_CUTOFF_DAY, 250.0);

        $centre = app(ReconciliationCenterService::class);
        $accountIds = [(int) $bankAccount->id];

        $fixed = $centre->unmatchedFor($this->companyId, $accountIds, ReconciliationCenterService::GROUP_BANK_CASH, $asOf);
        $old = $centre->unmatchedFor($this->companyId, $accountIds, ReconciliationCenterService::GROUP_BANK_CASH, Carbon::parse(self::CUTOFF));

        $this->assertCount(2, $fixed['items'], 'the drill-down must list both lines dated on the cutoff day');
        $this->assertCount(
            1,
            $old['items'],
            'fixture precondition: the midnight instant must still lose the 23:59 line, otherwise '
                .'this test proves nothing'
        );
    }

    // ── fixtures ────────────────────────────────────────────────────────────────────────────────

    private function taskFor(int $supplierId): int
    {
        return (int) Task::factory()->create([
            'company_id' => $this->companyId, 'agent_id' => $this->agentId,
            'supplier_id' => $supplierId, 'type' => 'hotel', 'status' => 'issued',
        ])->id;
    }

    /**
     * A journal line at an exact instant, with `posting_date` left NULL on purpose.
     *
     * That is the LEGACY shape, and it is the shape the defect bites:
     * `COALESCE(posting_date, transaction_date)` collapses to a DATE at midnight when
     * `posting_date` is set (which survives a bare upper bound by luck) and falls through to the
     * raw datetime when it is NULL (which does not). On LIVE the engine columns are not populated
     * at all, so every row is the second case.
     */
    private function journalLine(int $taskId, string $at, float $debit): void
    {
        $txn = $this->documentAt($at);

        JournalEntry::create([
            'transaction_id' => $txn, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $this->accountByCode('1351')->id, 'transaction_date' => $at,
            'posting_date' => null, 'task_id' => $taskId,
            'description' => 'ct-a13', 'debit' => $debit, 'credit' => 0.0,
            'name' => 'party', 'type' => 'receivable',
            'currency' => 'KWD', 'exchange_rate' => 1.0, 'amount' => $debit,
            'voucher_number' => 'A13',
        ]);
    }

    private function bankLine(int $accountId, string $at, float $debit): void
    {
        $txn = $this->documentAt($at);

        JournalEntry::create([
            'transaction_id' => $txn, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $accountId, 'transaction_date' => $at,
            'posting_date' => null,
            'description' => 'ct-a13 bank', 'debit' => $debit, 'credit' => 0.0,
            'name' => 'party', 'type' => 'receipt',
            'currency' => 'KWD', 'exchange_rate' => 1.0, 'amount' => $debit,
            'voucher_number' => 'A13', 'reconciled' => 0,
        ]);
    }

    private function documentAt(string $at): int
    {
        return (int) Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => 0.0, 'description' => 'ct-a13',
            'reference_type' => 'Receipt', 'reference_number' => 'A13-'.substr(uniqid(), -8),
            'name' => 'ct-a13', 'transaction_date' => $at, 'posting_date' => null,
            'posting_status' => 'posted',
        ])->id;
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }
}
