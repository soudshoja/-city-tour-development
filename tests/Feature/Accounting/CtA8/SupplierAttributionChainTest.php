<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA8;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A8 — THE WHOLE CHAIN, on a fixture shaped like the real data.
 *
 * The business symptom, in the owner's terms: *filter the unpaid-AP screen by a supplier and you
 * see everything you have ever been billed and none of what you have paid, so the balance on screen
 * is gross of every payment ever made to them.*
 *
 * Neither half of the repair fixes that on its own:
 *
 *   - `accounting:backfill-payable-party` stamps the ledger line's party from
 *     `accounts.supplier_id` — and on the real chart that column is NULL on ~137 of the 143 payable
 *     leaves of company 1, so it stamps 40 lines of 15,659 and the screen does not move;
 *   - `accounting:backfill-supplier-leaf` (CT-A8) fills that column from posted evidence — but it
 *     writes nothing at all to `journal_entries`, so on its own the screen does not move either.
 *
 * This file runs them IN THE DEPLOY ORDER against a chart carrying the four shapes the real one
 * does, and asserts the number on the screen:
 *
 *   1. a historical supplier leaf with NO `supplier_id`, carrying an invoice and a later payment,
 *      both with NULL `type_reference_id`     -> repaired end to end, and the screen NETS;
 *   2. a leaf carrying TWO suppliers' lines   -> refused by the leaf command, and therefore still
 *      refused by the party command, and still invisible to the supplier filter;
 *   3. a leaf carrying only manual JVs        -> refused by both;
 *   4. the money on the screen is the same before and after for the UNFILTERED view — the repair
 *      changes what the filter can see, never what the company owes.
 */
class SupplierAttributionChainTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private const INVOICED = 1000.0;

    private const PAID = 400.0;

    private int $companyId;

    private int $branchId;

    private int $agentId;

    private int $supplierAId;

    private int $supplierBId;

    private int $historicalLeafId;

    private int $sharedLeafId;

    private int $manualLeafId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->companyId, 'user_id' => $branchOwner->id]);
        $this->branchId = (int) $branch->id;

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $this->agentId = (int) Agent::factory()->create([
            'branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id,
        ])->id;

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);
        (new SystemAccountsSeeder)->run();

        // The screens read through `LedgerSource::restrict()`, which shows ENGINE documents when
        // the engine is on for the company and LEGACY ones when it is off. Company 1 on the real
        // development database has it ON (`companies.posting_engine_enabled = 1`, measured
        // 2026-09-16), and the documents this fixture posts carry `doc_type` + `posting_date`, so
        // this reproduces the side of the ledger the owner is actually looking at.
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);

        $this->supplierAId = (int) Supplier::factory()->create()->id;
        $this->supplierBId = (int) Supplier::factory()->create()->id;

        // Shape 1 — the historical per-supplier leaf. Minted the way the pre-CT-A7-2 chart minted
        // them: NO supplier_id, NO supplier_company_id. This is the ~137-leaf shape.
        $this->historicalLeafId = (int) $this->mintApLeaf('Historical Creditor A', '2191')->id;
        $this->sharedLeafId = (int) $this->mintApLeaf('Historical Creditor Shared', '2192')->id;
        $this->manualLeafId = (int) $this->mintApLeaf('Historical Creditor Manual', '2193')->id;
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    private function mintApLeaf(string $name, string $code): Account
    {
        $ap = $this->accountByCode('2100');

        return Account::create([
            'company_id' => $this->companyId,
            'parent_id' => $ap->id,
            'root_id' => $ap->root_id ?? $ap->id,
            'name' => $name,
            'code' => $code,
            'level' => 3,
            'account_type' => null,
            'report_type' => $ap->report_type,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);
    }

    private function makeTask(int $supplierId): int
    {
        return (int) Task::factory()->create([
            'company_id' => $this->companyId, 'agent_id' => $this->agentId,
            'supplier_id' => $supplierId, 'type' => 'flight', 'status' => 'issued',
            'issued_date' => now()->subDays(30),
        ])->id;
    }

    /**
     * One historical two-legged document on a payable leaf, with a NULL party on the AP leg —
     * exactly what a pre-CT-A7-2 write left behind. `$credit > 0` is a supplier invoice (the
     * payable goes up); `$debit > 0` is a payment voucher (the payable comes down).
     */
    private function document(int $accountId, float $debit, float $credit, ?int $taskId): int
    {
        $amount = $debit + $credit;

        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => $credit > 0 ? 'INV' : 'PV',
            'amount' => $amount, 'description' => 'historical document',
            'reference_type' => 'Invoice', 'reference_number' => 'A8E-'.substr(uniqid(), -8),
            'name' => 'historical document', 'transaction_date' => now()->subDays(20),
            'doc_type' => $credit > 0 ? 'INV' : 'PV', 'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted', 'posting_date' => now()->subDays(20),
            'total_debit' => $amount, 'total_credit' => $amount,
            'idempotency_key' => 'a8e:'.uniqid(),
        ]);

        $apLeg = (int) JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $accountId, 'transaction_date' => now()->subDays(20),
            'task_id' => $taskId,
            'description' => 'historical document',
            'debit' => $debit, 'credit' => $credit,
            'name' => 'a display string that must never be matched on',
            'type' => 'payable', 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amount, 'voucher_number' => 'A8E',
            'type_reference_id' => null,
            'reconciled' => 0,
        ])->id;

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $this->accountByCode($credit > 0 ? '5100' : '1201')->id,
            'transaction_date' => now()->subDays(20),
            'description' => 'historical document (contra)',
            'debit' => $credit, 'credit' => $debit, 'name' => 'contra',
            'type' => $credit > 0 ? 'expense' : 'bank',
            'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amount, 'voucher_number' => 'A8E',
            'type_reference_id' => null, 'reconciled' => 0,
        ]);

        return $apLeg;
    }

    private function companyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::where('id', $this->companyId)->update(['user_id' => $user->id]);

        return $user;
    }

    /** @param array<string, mixed> $query */
    private function screen(array $query = []): TestResponse
    {
        $response = $this->actingAs($this->companyUser())
            ->get(route('reports.unpaid-report', $query + ['account_id' => 'all']));

        $response->assertOk();

        return $response;
    }

    private function payableBalanceFor(?int $supplierId): float
    {
        $query = $supplierId === null ? [] : ['supplier_id' => $supplierId];

        return round((float) $this->screen($query)->viewData('payableBalance'), 3);
    }

    private function runRepair(): void
    {
        // THE DEPLOY ORDER. Leaf first, party second.
        $this->assertSame(0, Artisan::call('accounting:backfill-supplier-leaf', [
            '--company' => $this->companyId, '--apply' => true,
        ]));
        $this->assertSame(0, Artisan::call('accounting:backfill-payable-party', [
            '--company' => $this->companyId, '--apply' => true,
        ]));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * THE HEADLINE. A supplier's invoice and their later payment, both with a NULL party.
     *
     * Before: the supplier filter matches NOTHING, so the screen reports 0.000 for a supplier the
     * company genuinely owes 600.000 — the ledger and the screen disagree by the whole balance.
     *
     * After the two commands, in order: 600.000, because the payment is now visible to the filter
     * and NETS against the invoice.
     *
     * Expected values are written as arithmetic on the fixture's own inputs
     * (`INVOICED - PAID`), never read back out of a constant the command also reads.
     */
    public function test_the_supplier_filter_nets_payments_against_invoices_after_the_full_repair(): void
    {
        $this->document($this->historicalLeafId, 0.0, self::INVOICED, $this->makeTask($this->supplierAId));
        $this->document($this->historicalLeafId, self::PAID, 0.0, $this->makeTask($this->supplierAId));

        $this->assertSame(
            0.0,
            $this->payableBalanceFor($this->supplierAId),
            'BEFORE: every line carries a NULL party, so the supplier filter matches nothing and the '
            .'screen reports zero owed to a supplier the company owes '.(self::INVOICED - self::PAID)
        );

        $this->runRepair();

        $this->assertSame(
            round(self::INVOICED - self::PAID, 3),
            $this->payableBalanceFor($this->supplierAId),
            'AFTER: the payment is visible to the filter and nets against the invoice — this is the '
            .'number the owner was shown gross of everything ever paid'
        );
    }

    /**
     * The repair is a NO-OP on the unfiltered screen. It changes what the filter can SEE, never what
     * the company OWES — and that has to be true for the shared and manual leaves too, which is why
     * all three are on the chart for this assertion.
     */
    public function test_the_unfiltered_payable_balance_is_identical_before_and_after(): void
    {
        $this->document($this->historicalLeafId, 0.0, self::INVOICED, $this->makeTask($this->supplierAId));
        $this->document($this->historicalLeafId, self::PAID, 0.0, $this->makeTask($this->supplierAId));
        $this->document($this->sharedLeafId, 0.0, 250.0, $this->makeTask($this->supplierAId));
        $this->document($this->sharedLeafId, 0.0, 130.0, $this->makeTask($this->supplierBId));
        $this->document($this->manualLeafId, 0.0, 70.0, null);

        $before = $this->payableBalanceFor(null);

        // Derived from the fixture, not copied from the screen: if the screen were wrong BEFORE,
        // comparing it only to itself afterwards would prove nothing.
        $this->assertSame(
            round(self::INVOICED - self::PAID + 250.0 + 130.0 + 70.0, 3),
            $before,
            'the fixture owes exactly this before anything runs'
        );

        $this->runRepair();

        $this->assertSame($before, $this->payableBalanceFor(null), 'no money moved');
    }

    /**
     * Shape 2 — the leaf two suppliers were posted to. The leaf command refuses it, so the party
     * command has nothing to read and refuses it in turn, so its money stays out of BOTH suppliers'
     * filtered views. That is the correct outcome, not a shortfall: attributing it either way would
     * put one supplier's invoices on the other's statement.
     */
    public function test_a_two_supplier_leaf_is_refused_by_both_commands_and_stays_unattributed(): void
    {
        $shared = [
            $this->document($this->sharedLeafId, 0.0, 250.0, $this->makeTask($this->supplierAId)),
            $this->document($this->sharedLeafId, 0.0, 130.0, $this->makeTask($this->supplierBId)),
        ];

        $this->runRepair();

        $this->assertNull(
            DB::table('accounts')->where('id', $this->sharedLeafId)->value('supplier_id'),
            'the leaf itself is refused'
        );

        foreach ($shared as $lineId) {
            $this->assertNull(
                DB::table('journal_entries')->where('id', $lineId)->value('type_reference_id'),
                'and so is every line on it — the party command refuses what the leaf does not name'
            );
        }

        $this->assertSame(0.0, $this->payableBalanceFor($this->supplierAId));
        $this->assertSame(0.0, $this->payableBalanceFor($this->supplierBId));
    }

    /**
     * Shape 3 — the leaf carrying only manual JVs. No document, no party, refused by both. Again
     * the right answer: a JV nobody attributed is not attributable.
     */
    public function test_a_manual_jv_only_leaf_is_refused_by_both_commands(): void
    {
        $line = $this->document($this->manualLeafId, 0.0, 70.0, null);

        $this->runRepair();

        $this->assertNull(DB::table('accounts')->where('id', $this->manualLeafId)->value('supplier_id'));
        $this->assertNull(DB::table('journal_entries')->where('id', $line)->value('type_reference_id'));
    }

    /**
     * NEITHER HALF IS SUFFICIENT ALONE — the finding that created this lane, reproduced as a test.
     *
     * Running only `accounting:backfill-payable-party` on this chart is the dev-site measurement in
     * miniature: it refuses everything and the screen does not move.
     */
    public function test_the_party_backfill_alone_changes_nothing_on_this_chart(): void
    {
        $this->document($this->historicalLeafId, 0.0, self::INVOICED, $this->makeTask($this->supplierAId));
        $this->document($this->historicalLeafId, self::PAID, 0.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, Artisan::call('accounting:backfill-payable-party', [
            '--company' => $this->companyId, '--apply' => true,
        ]));

        $this->assertSame(
            0.0,
            $this->payableBalanceFor($this->supplierAId),
            'this is the 40-of-15,659 dev-site measurement: the command is right and the column it '
            .'reads is empty'
        );

        // And the leaf half alone is equally insufficient — it writes no ledger row at all.
        $this->assertSame(0, Artisan::call('accounting:backfill-supplier-leaf', [
            '--company' => $this->companyId, '--apply' => true,
        ]));

        $this->assertSame(
            $this->supplierAId,
            (int) DB::table('accounts')->where('id', $this->historicalLeafId)->value('supplier_id'),
            'the column is filled now'
        );
        $this->assertSame(
            0.0,
            $this->payableBalanceFor($this->supplierAId),
            'but the screen still reads zero, because nothing has stamped the LINES yet — which is '
            .'why the deploy order runs the party backfill after this one and not instead of it'
        );

        Artisan::call('accounting:backfill-payable-party', ['--company' => $this->companyId, '--apply' => true]);

        $this->assertSame(round(self::INVOICED - self::PAID, 3), $this->payableBalanceFor($this->supplierAId));
    }

    /**
     * THE UNDO, end to end and IN THE RIGHT ORDER. Rolling the party run back first and the leaf run
     * second returns the chart and the ledger to exactly the state they were in.
     */
    public function test_the_whole_repair_can_be_undone(): void
    {
        $this->document($this->historicalLeafId, 0.0, self::INVOICED, $this->makeTask($this->supplierAId));
        $this->document($this->historicalLeafId, self::PAID, 0.0, $this->makeTask($this->supplierAId));

        $this->runRepair();
        $this->assertSame(round(self::INVOICED - self::PAID, 3), $this->payableBalanceFor($this->supplierAId));

        $leafRun = (string) DB::table('coa_linkage_changes')
            ->where('subject_table', 'accounts')->where('column_name', 'supplier_id')->value('run_id');
        $partyRun = (string) DB::table('coa_linkage_changes')
            ->where('subject_table', 'journal_entries')->value('run_id');

        $this->assertNotSame('', $leafRun);
        $this->assertNotSame('', $partyRun);
        $this->assertNotSame($leafRun, $partyRun, 'two commands, two run ids — one undo each');

        $this->assertSame(0, Artisan::call('accounting:backfill-payable-party', ['--rollback' => $partyRun]));
        $this->assertSame(0, Artisan::call('accounting:backfill-supplier-leaf', ['--rollback' => $leafRun]));

        $this->assertNull(DB::table('accounts')->where('id', $this->historicalLeafId)->value('supplier_id'));
        $this->assertSame(
            0.0,
            $this->payableBalanceFor($this->supplierAId),
            'back to the state the repair started from, screen included'
        );
    }

    /**
     * Re-running the whole repair is a no-op, so an operator who is unsure whether it completed can
     * simply run it again — the property that makes a staged rollout safe.
     */
    public function test_re_running_the_whole_repair_changes_nothing_and_adds_no_before_images(): void
    {
        $this->document($this->historicalLeafId, 0.0, self::INVOICED, $this->makeTask($this->supplierAId));
        $this->document($this->historicalLeafId, self::PAID, 0.0, $this->makeTask($this->supplierAId));

        $this->runRepair();

        $images = DB::table('coa_linkage_changes')->count();
        $balance = $this->payableBalanceFor($this->supplierAId);

        $this->runRepair();

        $this->assertSame($images, DB::table('coa_linkage_changes')->count());
        $this->assertSame($balance, $this->payableBalanceFor($this->supplierAId));
    }
}
