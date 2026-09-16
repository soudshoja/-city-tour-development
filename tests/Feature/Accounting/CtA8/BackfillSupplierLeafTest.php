<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA8;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A8 — `accounting:backfill-supplier-leaf`.
 *
 * `accounting:backfill-payable-party` derives a payment voucher's party from
 * `accounts.supplier_id`. On the real development chart that column is set on **6 accounts of
 * company 1** and NULL on the ~137 historical payable leaves, so the repair its own dry run
 * measured was **40 lines of 15,659**. This command makes the column derivable, from posted
 * evidence, without ever matching a name.
 *
 * The properties under test are the ones that decide whether a chart-wide attribution write is
 * safe to run on a business's real ledger:
 *
 *   - dry-run is the DEFAULT and writes nothing;
 *   - a leaf whose posted lines all trace to ONE supplier is stamped;
 *   - **zero** suppliers -> REFUSED and reported;
 *   - **more than one** supplier -> REFUSED and reported WITH the set;
 *   - untraceable lines DO NOT VOTE, but a leaf with only untraceable lines is refused;
 *   - an account that already carries `supplier_id` is never overwritten;
 *   - the EXPENSE TWIN of a supplier's payable leaf is never stamped and never consulted;
 *   - NAMES ARE NEVER MATCHED — a mutation that renames every account to its supplier's exact name
 *     changes nothing;
 *   - a per-row before-image is written in the same transaction as the write, `--rollback` puts it
 *     back, and the ownership guard is symmetric in all three directions;
 *   - no money moves.
 */
class BackfillSupplierLeafTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private int $companyId;

    private int $branchId;

    private int $agentId;

    /** A historical per-supplier payable leaf with NO supplier_id — the subject of the repair. */
    private int $cleanLeafId;

    /** A payable leaf carrying two suppliers' posted lines — must be REFUSED. */
    private int $sharedLeafId;

    /** A payable leaf carrying only manual-JV lines (no task) — must be REFUSED. */
    private int $manualOnlyLeafId;

    /** A payable leaf with no lines at all — must be REFUSED. */
    private int $emptyLeafId;

    private int $supplierAId;

    private int $supplierBId;

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

        $this->supplierAId = (int) Supplier::factory()->create()->id;
        $this->supplierBId = (int) Supplier::factory()->create()->id;

        $this->cleanLeafId = (int) $this->mintApLeaf('Historical Creditor One', '2191')->id;
        $this->sharedLeafId = (int) $this->mintApLeaf('Historical Creditor Shared', '2192')->id;
        $this->manualOnlyLeafId = (int) $this->mintApLeaf('Historical Creditor Manual', '2193')->id;
        $this->emptyLeafId = (int) $this->mintApLeaf('Historical Creditor Empty', '2194')->id;
    }

    // ── fixture helpers ─────────────────────────────────────────────────────────────────────────

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    /** @param array<string, mixed> $extra */
    private function mintLeafUnder(string $parentCode, string $name, string $code, array $extra = []): Account
    {
        $parent = $this->accountByCode($parentCode);

        return Account::create($extra + [
            'company_id' => $this->companyId,
            'parent_id' => $parent->id,
            'root_id' => $parent->root_id ?? $parent->id,
            'name' => $name,
            'code' => $code,
            'level' => 3,
            'account_type' => null,
            'report_type' => $parent->report_type,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function mintApLeaf(string $name, string $code, array $extra = []): Account
    {
        return $this->mintLeafUnder('2100', $name, $code, $extra);
    }

    private function makeTask(int $supplierId): int
    {
        return (int) Task::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $this->agentId,
            'supplier_id' => $supplierId,
            'type' => 'flight',
            'status' => 'issued',
            'issued_date' => now()->subDays(20),
        ])->id;
    }

    /**
     * A historical, BALANCED two-legged voucher whose AP leg carries no party — the exact shape a
     * pre-CT-A7-2 payment voucher wrote. `$taskId === null` is the MANUAL JV case: a posted line
     * with no document to trace.
     *
     * `journal_entries.name` is deliberately set to a string that names the WRONG supplier
     * throughout this fixture, so any implementation that reads it is wrong in a way a test can see.
     */
    private function historicalApLine(int $accountId, float $debit, float $credit, ?int $taskId, string $postingStatus = 'posted'): int
    {
        $amount = $debit + $credit;

        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'PV', 'amount' => $amount, 'description' => 'historical voucher',
            'reference_type' => 'Invoice', 'reference_number' => 'A8-'.substr(uniqid(), -8),
            'name' => 'historical voucher', 'transaction_date' => now()->subMonth(),
            'doc_type' => 'PV', 'doc_year' => (int) now()->subMonth()->format('Y'),
            'posting_status' => $postingStatus, 'posting_date' => now()->subMonth(),
            'total_debit' => $amount, 'total_credit' => $amount,
            'idempotency_key' => 'a8:'.uniqid(),
        ]);

        $apLegId = (int) JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $accountId, 'transaction_date' => now()->subMonth(),
            'task_id' => $taskId,
            'description' => 'historical voucher',
            'debit' => $debit, 'credit' => $credit,
            'name' => 'a party name, which must NEVER be matched on',
            'type' => 'payable', 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amount, 'voucher_number' => 'A8',
            'type_reference_id' => null,
        ])->id;

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $this->accountByCode('1201')->id, 'transaction_date' => now()->subMonth(),
            'description' => 'historical voucher (contra)',
            'debit' => $credit, 'credit' => $debit, 'name' => 'bank', 'type' => 'bank',
            'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => $amount, 'voucher_number' => 'A8',
            'type_reference_id' => null,
        ]);

        return $apLegId;
    }

    private function supplierOn(int $accountId): ?int
    {
        $value = DB::table('accounts')->where('id', $accountId)->value('supplier_id');

        return $value === null ? null : (int) $value;
    }

    /** @param array<string, mixed> $options */
    private function leafBackfill(array $options = []): int
    {
        return Artisan::call('accounting:backfill-supplier-leaf', $options + ['--company' => $this->companyId]);
    }

    private function runIdOfLastApply(): string
    {
        return (string) DB::table('coa_linkage_changes')
            ->where('subject_table', 'accounts')
            ->where('column_name', 'supplier_id')
            ->orderByDesc('id')
            ->value('run_id');
    }

    private function moneyFingerprint(): string
    {
        return DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'account_id', 'debit', 'credit', 'transaction_id', 'type_reference_id'])
            ->map(fn ($r) => implode('|', (array) $r))
            ->implode("\n");
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE EVIDENCE RULE
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * DRY RUN IS THE DEFAULT. No flag at all must write nothing.
     */
    public function test_the_default_is_a_dry_run_and_writes_nothing(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $this->assertNull($this->supplierOn($this->cleanLeafId), 'a bare invocation must not write');
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->where('column_name', 'supplier_id')->count(),
            'and must record no before-image'
        );
    }

    /**
     * The repair itself: every posted line on the leaf traces to one supplier, so the leaf gets it.
     */
    public function test_a_leaf_whose_posted_lines_all_trace_to_one_supplier_is_stamped(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->cleanLeafId, 60.0, 0.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($this->cleanLeafId),
            'two documents, one supplier: the evidence names exactly one party'
        );
    }

    /**
     * MORE THAN ONE SUPPLIER -> REFUSED, and the set is reported. This is the pooled control leaves
     * by construction (the real `21209` carries 33 suppliers), and it is also the honest answer for
     * a legacy leaf two suppliers were genuinely posted to.
     */
    public function test_a_leaf_whose_lines_trace_to_two_suppliers_is_refused_and_the_set_is_reported(): void
    {
        $this->historicalApLine($this->sharedLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->sharedLeafId, 0.0, 250.0, $this->makeTask($this->supplierBId));

        $expected = [$this->supplierAId, $this->supplierBId];
        sort($expected);

        // One `expectsOutputToContain` is satisfied by ONE write, so three facts on one console
        // line can only ever prove one of them — which is why the command prints a refusal as
        // three short lines and why this asserts all three.
        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('REFUSED leaf #'.$this->sharedLeafId)
            ->expectsOutputToContain('trace to 2 DIFFERENT suppliers')
            ->expectsOutputToContain('supplier ids: '.implode(', ', $expected))
            ->assertExitCode(0);

        $this->assertNull(
            $this->supplierOn($this->sharedLeafId),
            'a leaf two suppliers were posted to cannot carry one party — a WRONG supplier puts '
            .'another party\'s payments on this one\'s statement'
        );
    }

    /**
     * ZERO SUPPLIERS -> REFUSED. A leaf carrying only manual JVs has posted movement and no
     * document, which is not evidence of one supplier; it is no evidence at all.
     */
    public function test_a_leaf_with_only_manual_jv_lines_is_refused(): void
    {
        $this->historicalApLine($this->manualOnlyLeafId, 0.0, 400.0, null);
        $this->historicalApLine($this->manualOnlyLeafId, 90.0, 0.0, null);

        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('REFUSED leaf #'.$this->manualOnlyLeafId)
            ->expectsOutputToContain('no posted line on it traces to a supplier')
            ->assertExitCode(0);

        $this->assertNull($this->supplierOn($this->manualOnlyLeafId));
    }

    /**
     * An EMPTY leaf is refused for the same reason and must not be confused with a clean one. On
     * the real chart 37 of the 137 candidate leaves carry no line at all.
     */
    public function test_a_leaf_with_no_lines_at_all_is_refused(): void
    {
        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('REFUSED leaf #'.$this->emptyLeafId)
            ->assertExitCode(0);

        $this->assertNull($this->supplierOn($this->emptyLeafId));
    }

    /**
     * UNTRACEABLE LINES DO NOT VOTE. A leaf that carries both a manual JV and documents naming one
     * supplier is still that supplier's — the JV abstains rather than blocking.
     *
     * This is the one place the rule is permissive, so it gets its own test rather than riding on
     * the happy path.
     */
    public function test_an_untraceable_line_does_not_block_a_leaf_whose_other_lines_agree(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->cleanLeafId, 0.0, 7.0, null);

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($this->cleanLeafId),
            'a manual JV abstains; it does not veto'
        );
    }

    /**
     * A DRAFT header is not money and does not vote. Otherwise an abandoned draft naming the wrong
     * supplier would permanently block a leaf whose whole posted history is one party.
     */
    public function test_a_draft_document_does_not_vote(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->cleanLeafId, 0.0, 500.0, $this->makeTask($this->supplierBId), 'draft');

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($this->cleanLeafId),
            'draft and void are not money and carry no evidence about who a payable belongs to'
        );
    }

    /**
     * A VOID header does not vote either — and the assertion is written the other way round from
     * the draft test so that a mutation deleting one status from the list cannot pass both.
     */
    public function test_a_void_document_does_not_vote(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierBId));
        $this->historicalApLine($this->cleanLeafId, 0.0, 500.0, $this->makeTask($this->supplierAId), 'void');

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame($this->supplierBId, $this->supplierOn($this->cleanLeafId));
    }

    /**
     * A REVERSED header DOES vote. A reversal is a new row with the opposite sign, never a delete;
     * dropping reversed headers would lose the original document while keeping the reversing one.
     */
    public function test_a_reversed_document_still_carries_evidence(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId), 'reversed');

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame($this->supplierAId, $this->supplierOn($this->cleanLeafId));
    }

    /**
     * A soft-deleted LINE is not posted evidence.
     */
    public function test_a_soft_deleted_line_does_not_vote(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $deleted = $this->historicalApLine($this->cleanLeafId, 0.0, 500.0, $this->makeTask($this->supplierBId));

        // BOTH legs, not just the AP one: the suite's own per-transaction invariant checker asserts
        // every document balances in tearDown, and soft-deleting one side of a two-legged voucher
        // makes it a 500.000 one-sided document. Deleting the whole document is also the only
        // shape that occurs in real life.
        $txnId = (int) DB::table('journal_entries')->where('id', $deleted)->value('transaction_id');
        DB::table('journal_entries')->where('transaction_id', $txnId)->update(['deleted_at' => now()]);

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame($this->supplierAId, $this->supplierOn($this->cleanLeafId));
    }

    /**
     * A task belonging to ANOTHER company must never name the party on this company's leaf. On the
     * real data this measures zero violations — which is what a guard looks like when it is
     * working, not when it is unnecessary.
     */
    public function test_a_task_from_another_company_is_not_evidence(): void
    {
        $otherCompany = Company::factory()->create();
        $foreignTask = (int) Task::factory()->create([
            'company_id' => $otherCompany->id,
            'agent_id' => $this->agentId,
            'supplier_id' => $this->supplierBId,
            'type' => 'flight', 'status' => 'issued', 'issued_date' => now()->subDays(20),
        ])->id;

        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->cleanLeafId, 0.0, 500.0, $foreignTask);

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($this->cleanLeafId),
            'a cross-tenant task must not vote — if it did, this leaf would be refused as shared'
        );
    }

    /**
     * `tasks.supplier_id` is not a foreign key in this schema, so a task can name a supplier row
     * that is gone. An id with nothing behind it is not a party.
     */
    public function test_a_task_naming_a_supplier_that_does_not_exist_does_not_vote(): void
    {
        $ghostTask = (int) Task::factory()->create([
            'company_id' => $this->companyId, 'agent_id' => $this->agentId,
            'supplier_id' => 987654, 'type' => 'flight', 'status' => 'issued',
            'issued_date' => now()->subDays(20),
        ])->id;

        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->cleanLeafId, 0.0, 500.0, $ghostTask);

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame($this->supplierAId, $this->supplierOn($this->cleanLeafId));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // SCOPE
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * NEVER OVERWRITE. A leaf already carrying a supplier — because an operator set it, or because
     * `SupplierActivationService::activate()` did — is out of scope even when the evidence
     * disagrees. A repair that can overwrite a deliberate assignment is not a repair.
     */
    public function test_a_leaf_that_already_carries_a_supplier_is_never_overwritten(): void
    {
        $already = (int) $this->mintApLeaf('Already Assigned', '2195', ['supplier_id' => $this->supplierBId])->id;
        $this->historicalApLine($already, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame(
            $this->supplierBId,
            $this->supplierOn($already),
            'the evidence names A and the column says B — the column wins, and the repair keeps its hands off'
        );
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->where('subject_id', $already)->count(),
            'and no before-image is recorded for a row that was not written'
        );
    }

    /**
     * A GROUP is not a leaf. Stamping a supplier on a parent would attribute every child's lines to
     * that supplier through `accounting:backfill-payable-party`'s account read.
     */
    public function test_a_group_account_with_children_is_never_stamped(): void
    {
        $group = $this->mintApLeaf('Historical Creditor Group', '2196');
        $child = $this->mintLeafUnder('2196', 'Historical Creditor Child', '21961');

        $this->historicalApLine((int) $group->id, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine((int) $child->id, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertNull($this->supplierOn((int) $group->id), 'a group is not a leaf');
        $this->assertSame($this->supplierAId, $this->supplierOn((int) $child->id), 'but its child is');
    }

    /**
     * Leaf-ness is derived from the CHILDREN, never from `accounts.is_group`, which CT-A1 §1.4
     * measured wrong on 613 accounts of this chart (566 flagged as groups with no children, 47
     * flagged as leaves that have children).
     */
    public function test_leafness_is_derived_from_children_not_from_the_is_group_flag(): void
    {
        DB::table('accounts')->where('id', $this->cleanLeafId)->update(['is_group' => 1]);
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($this->cleanLeafId),
            'is_group = 1 on an account with no children must not take it out of scope'
        );
    }

    /**
     * An account OUTSIDE the Accounts Payable structure is never in scope, however clean its
     * evidence. The boundary is `apSubtreeIds()` — client advances and accrued expenses are not
     * supplier payables.
     */
    public function test_an_account_outside_the_accounts_payable_subtree_is_never_stamped(): void
    {
        $bank = $this->accountByCode('1201');
        $this->historicalApLine((int) $bank->id, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertNull($this->supplierOn((int) $bank->id));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE EXPENSE TWIN (found on the real chart, 2026-09-16)
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Three suppliers on company 1 each own TWO accounts carrying the same `supplier_id`: a payable
     * leaf (`21xx`) and an expense leaf (`51xx`). CT-A7's F5 ruling is that `supplier_id` belongs on
     * the **payable leaf only**.
     *
     * Both twins here carry posted lines from the same supplier's tasks. Only the payable one may
     * be stamped.
     */
    public function test_only_the_payable_twin_is_ever_stamped_never_the_expense_twin(): void
    {
        $payableTwin = (int) $this->mintApLeaf('Twin (Payable)', '2197')->id;
        $expenseTwin = (int) $this->mintLeafUnder('5100', 'Twin (Cost)', '5197')->id;

        $task = $this->makeTask($this->supplierAId);
        $this->historicalApLine($payableTwin, 0.0, 300.0, $task);
        $this->historicalApLine($expenseTwin, 300.0, 0.0, $task);

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame($this->supplierAId, $this->supplierOn($payableTwin), 'the payable twin is stamped');
        $this->assertNull(
            $this->supplierOn($expenseTwin),
            'CT-A7 F5: supplier_id belongs on the PAYABLE leaf only — an expense leaf carrying it '
            .'is what made voucherPartyRef() have to constrain its own fallback to the AP subtree'
        );
    }

    /**
     * The twin is not CONSULTED either. A payable leaf whose supplier's expense twin is already
     * stamped is still derived from its OWN posted lines — "this supplier owns an account
     * somewhere" is not evidence about this account.
     *
     * The expense twin here is stamped with supplier B while the payable twin's own evidence names
     * supplier A, so an implementation that looked at the twin would write the wrong party.
     */
    public function test_an_already_stamped_expense_twin_is_not_consulted(): void
    {
        $payableTwin = (int) $this->mintApLeaf('Twin Two (Payable)', '2198')->id;
        $this->mintLeafUnder('5100', 'Twin Two (Cost)', '5198', ['supplier_id' => $this->supplierBId]);

        $this->historicalApLine($payableTwin, 0.0, 300.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($payableTwin),
            'derived from this leaf\'s own evidence, not from a sibling account of the same supplier'
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // NAMES ARE NEVER MATCHED
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A8-NAME ────────────────────────────────────────────────────────────────
     * The forbidden implementation is "match the account name against the supplier name". This test
     * makes that implementation VISIBLY WRONG rather than merely unused:
     *
     *   - the SHARED leaf is renamed to supplier A's exact name, and carries A's and B's lines.
     *     A name matcher stamps it with A. The evidence rule refuses it.
     *   - the CLEAN leaf is renamed to supplier B's exact name, and carries only A's lines. A name
     *     matcher stamps it with B. The evidence rule stamps it with A.
     *
     * So both assertions below fail — with different wrong values — the moment anything in the
     * derivation reads a name. `journal_entries.name` already names a third wrong thing on every
     * fixture line, so the third spelling of the same mistake is covered too.
     */
    public function test_names_are_never_matched_even_when_they_match_exactly(): void
    {
        $nameA = (string) DB::table('suppliers')->where('id', $this->supplierAId)->value('name');
        $nameB = (string) DB::table('suppliers')->where('id', $this->supplierBId)->value('name');

        DB::table('accounts')->where('id', $this->sharedLeafId)->update(['name' => $nameA]);
        DB::table('accounts')->where('id', $this->cleanLeafId)->update(['name' => $nameB]);

        $this->historicalApLine($this->sharedLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->sharedLeafId, 0.0, 250.0, $this->makeTask($this->supplierBId));
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));

        $this->assertNull(
            $this->supplierOn($this->sharedLeafId),
            'a leaf named EXACTLY after supplier A, carrying two suppliers\' lines, is still REFUSED'
        );
        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($this->cleanLeafId),
            'a leaf named EXACTLY after supplier B, carrying only A\'s lines, is stamped with A'
        );
    }

    /**
     * The source file itself must not reach for a name column in its derivation. A grep-level
     * ratchet, because the behavioural proof above only covers the shapes it happens to build, and
     * this rule is absolute.
     *
     * Deliberately narrow: `accounts.name` and `suppliers.name` are PRINTED in refusal lines so an
     * operator can find the account, which is why the assertion is about query predicates
     * (`where`/`join`/`on` against a name column) rather than about the word "name".
     */
    public function test_the_command_source_contains_no_name_predicate(): void
    {
        $source = file_get_contents(app_path('Console/Commands/BackfillSupplierLeaf.php'));
        $this->assertIsString($source);

        // Strip block and line comments so the docblock's prose about names cannot fire the scan.
        $code = preg_replace('#/\*.*?\*/#s', '', $source);
        $code = preg_replace('#//[^\n]*#', '', (string) $code);

        $offenders = [];

        // `(?:[a-z_]+\.)?name` — the bare column or an alias-qualified one, and NOTHING ELSE. It
        // deliberately does not match `column_name`, which this command genuinely does filter on in
        // its before-image ownership check: that is a metadata column whose value is the literal
        // string 'supplier_id', not a party's name. A ratchet that fires on it would be a ratchet
        // somebody weakens.
        foreach ([
            '#->where\s*\(\s*[\'"](?:[a-z_]+\.)?name[\'"]#i' => 'a where() predicate on a name column',
            '#->whereIn\s*\(\s*[\'"](?:[a-z_]+\.)?name[\'"]#i' => 'a whereIn() predicate on a name column',
            '#->on\s*\(\s*[\'"](?:[a-z_]+\.)?name[\'"]#i' => 'a join on a name column',
            '#->whereColumn\s*\([^)]*[\'"](?:[a-z_]+\.)?name[\'"]#i' => 'a whereColumn() against a name column',
            '#\bLIKE\b#i' => 'a LIKE — no fuzzy matching belongs in an attribution derivation',
        ] as $pattern => $why) {
            if (preg_match($pattern, (string) $code)) {
                $offenders[] = $why;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "BackfillSupplierLeaf must never derive a party from a NAME. Found: \n  - "
            .implode("\n  - ", $offenders)
            ."\nThe export data in this project is pseudonymised precisely so that no one is tempted."
        );
    }

    /**
     * MUTATION PROOF for the ratchet above: the scanner must actually bite. Each of these is a
     * plausible way somebody would reintroduce name matching, and the assertion is that the same
     * patterns the test applies to the real file reject every one of them.
     */
    public function test_the_name_predicate_ratchet_bites_a_synthetic_violation(): void
    {
        $patterns = [
            '#->where\s*\(\s*[\'"](?:[a-z_]+\.)?name[\'"]#i',
            '#->whereIn\s*\(\s*[\'"](?:[a-z_]+\.)?name[\'"]#i',
            '#->on\s*\(\s*[\'"](?:[a-z_]+\.)?name[\'"]#i',
            '#->whereColumn\s*\([^)]*[\'"](?:[a-z_]+\.)?name[\'"]#i',
            '#\bLIKE\b#i',
        ];

        $violations = [
            'where on accounts.name' => '$q->where(\'name\', $supplier->name);',
            'where on a qualified name' => '$q->where(\'a.name\', $supplier->name);',
            'whereIn on name' => '$q->whereIn(\'name\', $supplierNames);',
            'join on name' => '$join->on(\'s.name\', \'=\', \'a.name\');',
            'whereColumn across names' => '$q->whereColumn(\'accounts.name\', \'suppliers.name\');',
            'fuzzy LIKE' => '$q->whereRaw("a.name LIKE CONCAT(s.name, \'%\')");',
        ];

        foreach ($violations as $label => $snippet) {
            $bitten = false;

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $snippet)) {
                    $bitten = true;

                    break;
                }
            }

            $this->assertTrue($bitten, "the name-predicate ratchet does NOT catch: {$label} — {$snippet}");
        }

        // And it must NOT fire on the three things the real file legitimately does. The third is
        // the false positive that a looser pattern produced on the first run of this very test:
        // `column_name` is a metadata column carrying the literal string 'supplier_id'.
        foreach ([
            'printing the account name in a refusal' => '$this->line(sprintf(\'REFUSED %s\', $leaf->name));',
            'selecting the column to print it' => '->get([\'id\', \'code\', \'name\', \'supplier_company_id\']);',
            'the before-image ownership filter' => '$q->where(\'column_name\', self::COLUMN);',
        ] as $label => $snippet) {
            foreach ($patterns as $pattern) {
                $this->assertSame(
                    0,
                    preg_match($pattern, $snippet),
                    "the ratchet must not fire on: {$label} — {$snippet}"
                );
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE BEFORE-IMAGE, THE UNDO, AND OWNERSHIP
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_before_image_is_recorded_for_every_leaf_written_and_for_none_refused(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->sharedLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->sharedLeafId, 0.0, 100.0, $this->makeTask($this->supplierBId));

        $this->leafBackfill(['--apply' => true]);

        $images = DB::table('coa_linkage_changes')->where('column_name', 'supplier_id')->get();

        $this->assertCount(1, $images, 'one image per leaf WRITTEN, none for a refused leaf');
        $this->assertSame($this->cleanLeafId, (int) $images->first()->subject_id);
        $this->assertSame('accounts', $images->first()->subject_table);
        $this->assertNull($images->first()->before_value);
        $this->assertSame((string) $this->supplierAId, $images->first()->after_value);
        $this->assertSame($this->companyId, (int) $images->first()->company_id);
    }

    public function test_a_second_apply_is_a_no_op(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->leafBackfill(['--apply' => true]);
        $after = DB::table('coa_linkage_changes')->where('column_name', 'supplier_id')->count();

        $this->assertSame(0, $this->leafBackfill(['--apply' => true]));
        $this->assertSame(
            $after,
            DB::table('coa_linkage_changes')->where('column_name', 'supplier_id')->count(),
            'a leaf that already carries a supplier is out of scope by definition'
        );
    }

    public function test_rollback_restores_every_leaf_the_run_stamped(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->leafBackfill(['--apply' => true]);
        $runId = $this->runIdOfLastApply();
        $this->assertNotSame('', $runId);

        $this->assertSame(0, Artisan::call('accounting:backfill-supplier-leaf', ['--rollback' => $runId]));

        $this->assertNull($this->supplierOn($this->cleanLeafId));
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->where('column_name', 'supplier_id')->whereNull('rolled_back_at')->count()
        );
    }

    /**
     * A leaf that has MOVED since the run is left alone. On this column the "somebody else" is most
     * likely `SupplierActivationService::activate()` — the code path this whole lane exists to have
     * working — so overwriting it would undo a real activation.
     */
    public function test_rollback_refuses_a_leaf_that_has_moved_since_the_run(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->leafBackfill(['--apply' => true]);
        $runId = $this->runIdOfLastApply();

        DB::table('accounts')->where('id', $this->cleanLeafId)->update(['supplier_id' => $this->supplierBId]);

        $this->artisan('accounting:backfill-supplier-leaf', ['--rollback' => $runId])
            ->expectsOutputToContain('left alone')
            ->assertExitCode(1);

        $this->assertSame($this->supplierBId, $this->supplierOn($this->cleanLeafId));
    }

    public function test_a_repeat_rollback_of_a_fully_undone_run_exits_zero(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->leafBackfill(['--apply' => true]);
        $runId = $this->runIdOfLastApply();

        $this->assertSame(0, Artisan::call('accounting:backfill-supplier-leaf', ['--rollback' => $runId]));
        $this->assertSame(0, Artisan::call('accounting:backfill-supplier-leaf', ['--rollback' => $runId]));
    }

    public function test_an_unrecognised_run_id_exits_non_zero(): void
    {
        $this->artisan('accounting:backfill-supplier-leaf', ['--rollback' => 'NOT-A-RUN-ID'])
            ->expectsOutputToContain('is not a run id this command recorded')
            ->assertExitCode(1);
    }

    /**
     * ── SYMMETRY, DIRECTION 1 ───────────────────────────────────────────────────────────────────
     * `accounting:coa-linkage --rollback` must REFUSE a CT-A8 run id and name this command.
     *
     * This is the direction CT-A8 had to CHANGE. Before it, those rows were `subject_table =
     * 'accounts'`, which that command treats as its own, so its phase 3 reached them, found
     * `supplier_id` is not in `CoaLinkageChange::REVERSIBLE_COLUMNS` and reported
     * `column 'supplier_id' is not reversible` — which is false. It is reversible; it is just not
     * reversible by that command, and an operator was being told their undo was impossible when the
     * right answer was "you typed the wrong command".
     */
    public function test_coa_linkage_rollback_refuses_a_ct_a8_run_and_names_this_command(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->leafBackfill(['--apply' => true]);
        $runId = $this->runIdOfLastApply();

        $this->artisan('accounting:coa-linkage', ['--rollback' => $runId])
            ->expectsOutputToContain('Run contains before-images for: accounts.supplier_id')
            ->expectsOutputToContain('accounting:backfill-supplier-leaf --rollback')
            ->assertExitCode(1);

        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($this->cleanLeafId),
            'and must have restored nothing'
        );
    }

    /**
     * ── MUTATION PROOF M-A8-OWNERSHIP ───────────────────────────────────────────────────────────
     * The proof that the guard above is doing the work, and that it is NOT satisfied by the old
     * `subject_table`-only rule. A before-image with `subject_table = 'accounts'` and a column the
     * linkage command DOES own (`report_type`) must still be accepted by it — otherwise the new
     * guard would be passing this test by refusing everything.
     */
    public function test_the_ownership_guard_still_admits_a_row_the_linkage_command_really_owns(): void
    {
        // before == after, so restoring it is a write of the value already there. `report_type` is
        // NOT NULL on this schema, so a null before-image would abort the undo on a constraint and
        // prove nothing about the ownership rule.
        $reportType = (string) DB::table('accounts')->where('id', $this->cleanLeafId)->value('report_type');

        DB::table('coa_linkage_changes')->insert([
            'run_id' => 'A8-OWNED-BY-LINKAGE',
            'company_id' => $this->companyId,
            'subject_table' => 'accounts',
            'subject_id' => $this->cleanLeafId,
            'column_name' => 'report_type',
            'before_value' => $reportType,
            'after_value' => $reportType,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // It is the linkage command's row, so the linkage command must NOT refuse it as foreign.
        $this->artisan('accounting:coa-linkage', ['--rollback' => 'A8-OWNED-BY-LINKAGE'])
            ->doesntExpectOutputToContain('accounting:backfill-supplier-leaf --rollback');

        // And THIS command must refuse it, naming the linkage command.
        $this->artisan('accounting:backfill-supplier-leaf', ['--rollback' => 'A8-OWNED-BY-LINKAGE'])
            ->expectsOutputToContain('accounts.report_type')
            ->expectsOutputToContain('accounting:coa-linkage --rollback')
            ->assertExitCode(1);
    }

    /**
     * ── SYMMETRY, DIRECTION 3 ───────────────────────────────────────────────────────────────────
     * The two backfills must name each other, not a third command. Before CT-A8,
     * `accounting:backfill-payable-party --rollback` answered ANY foreign run id with
     * "use accounting:coa-linkage" — which for a CT-A8 run id is the wrong command, and following
     * it lands the operator on the refusal in direction 1 rather than on the undo.
     */
    public function test_the_two_backfills_name_each_other(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->leafBackfill(['--apply' => true]);
        $leafRunId = $this->runIdOfLastApply();

        $this->artisan('accounting:backfill-payable-party', ['--rollback' => $leafRunId])
            ->expectsOutputToContain('accounts.supplier_id')
            ->expectsOutputToContain('accounting:backfill-supplier-leaf --rollback')
            ->assertExitCode(1);

        // The other way round: a party run id offered to the leaf command.
        Artisan::call('accounting:backfill-payable-party', ['--company' => $this->companyId, '--apply' => true]);
        $partyRunId = (string) DB::table('coa_linkage_changes')
            ->where('subject_table', 'journal_entries')->orderByDesc('id')->value('run_id');

        $this->assertNotSame('', $partyRunId, 'the party backfill must have written something to offer back');

        $this->artisan('accounting:backfill-supplier-leaf', ['--rollback' => $partyRunId])
            ->expectsOutputToContain('journal_entries.type_reference_id')
            ->expectsOutputToContain('accounting:backfill-payable-party --rollback')
            ->assertExitCode(1);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // OPERABILITY
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * NO MONEY MOVES. Byte-equality of every money column AND of the party column on the ledger —
     * this command writes a chart column, and must not have touched a journal row.
     */
    public function test_the_repair_moves_no_money_and_stamps_no_ledger_line(): void
    {
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->sharedLeafId, 0.0, 100.0, $this->makeTask($this->supplierBId));

        $before = $this->moneyFingerprint();

        $this->leafBackfill(['--apply' => true]);

        $this->assertSame(
            $before,
            $this->moneyFingerprint(),
            'this command writes accounts.supplier_id and nothing else — the ledger is untouched '
            .'until accounting:backfill-payable-party runs'
        );
    }

    /**
     * `--limit` caps a staged rollout, so an operator can stamp a few leaves, look at a screen, and
     * then continue.
     *
     * Asserted on what the command REPORTS CONSIDERING, not on which leaf happens to be stamped:
     * `CoaSeeder` already seeds several empty payable control leaves with lower ids than anything
     * this fixture mints, so "--limit 1 stamps my first leaf" would be an assertion about seeder
     * ordering rather than about `--limit`. The cap is on candidates examined, which is the
     * property an operator is buying.
     */
    public function test_limit_caps_the_leaves_considered(): void
    {
        $second = (int) $this->mintApLeaf('Historical Creditor Two', '2199')->id;

        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($second, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->artisan('accounting:backfill-supplier-leaf', [
            '--company' => $this->companyId, '--apply' => true, '--limit' => 1,
        ])->expectsOutputToContain('1 candidate leaf/leaves considered')->assertExitCode(0);

        // And without it, more than one is examined — otherwise the assertion above would pass on a
        // command that had simply stopped working.
        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId])
            ->doesntExpectOutputToContain('1 candidate leaf/leaves considered')
            ->assertExitCode(0);
    }

    /**
     * A8-4: `--limit` pages in `id` order and the low-id end of a real chart is refusal-heavy
     * (CoaSeeder's own seeded control leaves, which carry no posted lines, sort before anything a
     * fixture or a real supplier ever mints). A small `--limit` can legitimately see zero derivable
     * leaves and, without a hint, read exactly like the command breaking. The extra summary line is
     * gated on BOTH halves of that condition — `--limit` was actually given, AND nothing was
     * derivable — never on either alone.
     */
    public function test_the_limit_paging_hint_appears_only_when_limit_is_used_and_nothing_was_derivable(): void
    {
        $hint = 'considered, 0 derivable — leaves are processed in id order';

        // (1) POSITIVE: --limit given, the leaf(ves) it reaches are refused (no evidence posted to
        // anything yet) — the hint must appear, naming the SAME count the summary line reports.
        $this->artisan('accounting:backfill-supplier-leaf', [
            '--company' => $this->companyId, '--apply' => true, '--limit' => 1,
        ])->expectsOutputToContain('1 candidate leaf/leaves considered, 0 derivable')
            ->expectsOutputToContain('1 '.$hint)
            ->assertExitCode(0);

        // (2) NEGATIVE — no --limit at all: an unbounded run considering everything and still
        // deriving nothing is a real, different finding (genuinely unattributable leaves), not a
        // paging artifact, and must not be misreported as one.
        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId, '--apply' => true])
            ->doesntExpectOutputToContain($hint)
            ->assertExitCode(0);

        // (3) NEGATIVE — --limit given, but this time large enough to reach a leaf with real
        // evidence, so something IS derivable: the hint must not appear just because --limit was
        // present.
        $this->historicalApLine($this->cleanLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->artisan('accounting:backfill-supplier-leaf', [
            '--company' => $this->companyId, '--apply' => true, '--limit' => 1000,
        ])->doesntExpectOutputToContain($hint)
            ->assertExitCode(0);
    }

    /**
     * ── MUTATION PROOF M-A8-PAGING ──────────────────────────────────────────────────────────────
     * REFUSED leaves stay NULL forever, so a loop that re-queried `supplier_id IS NULL` would hand
     * back the same refused batch and spin until the process was killed. Paging is by
     * `id > lastSeenId`.
     *
     * The proof is a batch size of 1 with a REFUSED leaf ahead of a derivable one in id order: a
     * re-querying loop never reaches the second leaf (and never terminates), so this test both
     * times out and fails on the assertion if the paging regresses.
     */
    public function test_a_refused_leaf_does_not_stall_the_paging(): void
    {
        // sharedLeafId (refused, two suppliers) has a LOWER id than this one, by construction of
        // setUp() — asserted rather than assumed, because the whole proof rests on the order.
        $later = (int) $this->mintApLeaf('Historical Creditor Later', '21991')->id;
        $this->assertGreaterThan($this->sharedLeafId, $later);

        $this->historicalApLine($this->sharedLeafId, 0.0, 100.0, $this->makeTask($this->supplierAId));
        $this->historicalApLine($this->sharedLeafId, 0.0, 100.0, $this->makeTask($this->supplierBId));
        $this->historicalApLine($later, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->assertSame(0, $this->leafBackfill(['--apply' => true, '--batch-size' => 1]));

        $this->assertNull($this->supplierOn($this->sharedLeafId), 'still refused');
        $this->assertSame(
            $this->supplierAId,
            $this->supplierOn($later),
            'a refused leaf must not stop the walk reaching the ones after it'
        );
    }

    public function test_dry_run_and_apply_together_are_refused(): void
    {
        $this->assertSame(1, $this->leafBackfill(['--apply' => true, '--dry-run' => true]));
    }

    public function test_rollback_cannot_be_combined_with_apply(): void
    {
        $this->assertSame(1, Artisan::call('accounting:backfill-supplier-leaf', [
            '--rollback' => 'X', '--apply' => true,
        ]));
    }

    /**
     * A leaf already attributable through `supplier_companies` needs nothing from this command —
     * `accounting:backfill-payable-party` already derives its party by that path — so it is not
     * written. When the pivot and the evidence DISAGREE, that is a chart fact an operator has to
     * look at, and it is reported rather than repaired in either direction.
     */
    public function test_a_pivot_linked_leaf_whose_evidence_disagrees_is_reported_not_repaired(): void
    {
        SupplierCompany::create([
            'supplier_id' => $this->supplierBId, 'company_id' => $this->companyId, 'is_active' => true,
        ]);
        $pivot = SupplierCompany::where('supplier_id', $this->supplierBId)
            ->where('company_id', $this->companyId)->firstOrFail();

        $leaf = (int) $this->mintApLeaf('Pivot Linked', '21992', ['supplier_company_id' => $pivot->id])->id;
        $this->historicalApLine($leaf, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('CONTRADICTION leaf #'.$leaf)
            ->assertExitCode(0);

        $this->assertNull(
            $this->supplierOn($leaf),
            'the pivot already answers the party question for this leaf; overriding it silently '
            .'would replace a link somebody made with one this command inferred'
        );
    }

    /**
     * And when they AGREE, it is simply out of scope — no write, no noise.
     */
    public function test_a_pivot_linked_leaf_whose_evidence_agrees_is_silently_out_of_scope(): void
    {
        SupplierCompany::create([
            'supplier_id' => $this->supplierAId, 'company_id' => $this->companyId, 'is_active' => true,
        ]);
        $pivot = SupplierCompany::where('supplier_id', $this->supplierAId)
            ->where('company_id', $this->companyId)->firstOrFail();

        $leaf = (int) $this->mintApLeaf('Pivot Agreed', '21993', ['supplier_company_id' => $pivot->id])->id;
        $this->historicalApLine($leaf, 0.0, 100.0, $this->makeTask($this->supplierAId));

        $this->artisan('accounting:backfill-supplier-leaf', ['--company' => $this->companyId, '--apply' => true])
            ->doesntExpectOutputToContain('CONTRADICTION leaf #'.$leaf)
            ->assertExitCode(0);

        $this->assertNull($this->supplierOn($leaf), 'already attributable — nothing to unlock');
    }
}
