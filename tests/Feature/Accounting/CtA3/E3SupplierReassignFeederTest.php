<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Http\Controllers\TaskController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\SupplierReassignDraftBuilder;
use App\Services\Accounting\TaskIssuancePayableService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 E3 — CT-F39: the "Update For Whom to Pay" flow had no engine feeder, and its legacy
 * implementation was one-sided.
 *
 * CT-A1 §0 item 2 measured the damage on the City Travelers dev data: **1,511 documents carrying
 * KWD 220,908.987 of credits against KWD 477.800 of debits.** A repair script neutralised 1,435 of
 * them in July 2026 by parking the difference in Equity `3900 Suspense`; the flow was never fixed
 * and produced 685 more documents in 2026. CT-A2 §5 row 14: the engine had NO counterpart, so a
 * cutover would silently stop a live operation rather than fix it.
 *
 * `TaskController::updateJournalPaymentMethod()` now routes through `PostingSeam::isEnabledFor()`:
 * engine ON posts one balanced JV/PAYEE_REASSIGN document and the legacy one-sided writer never
 * runs; engine OFF is byte-for-byte unchanged.
 */
class E3SupplierReassignFeederTest extends AccountingTestCase
{
    private Company $company;

    private Agent $agent;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10));

        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($this->company->id);
        Artisan::call('accounting:engine', ['company' => $this->company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->company->id]);

        $branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'Sales']);
        $this->agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A leaf under 2110 Creditors — the legacy per-supplier payable shape. */
    private function creditorLeaf(string $name, string $code): Account
    {
        $creditors = Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->company->id)->where('name', 'Creditors')->firstOrFail();

        return Account::factory()->create([
            'company_id' => $this->company->id,
            'parent_id' => $creditors->id,
            'root_id' => $creditors->root_id,
            'name' => $name,
            'code' => $code,
            'level' => 4,
            'is_group' => 0,
            'disabled' => 0,
        ]);
    }

    /** An accrued task with its payable sitting on $on — the state the reassignment moves. */
    private function accruedTask(Supplier $supplier, Account $on, float $total = 400.0): Task
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => $total,
            'reference' => 'CTA3-E3-'.uniqid(),
            'issued_date' => Carbon::create(2026, 6, 10),
        ]);

        $unbilled = Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->company->id)->where('code', '1430')->firstOrFail();

        $txn = Transaction::forceCreate([
            'company_id' => $this->company->id,
            'entity_id' => $this->company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => $total,
            'description' => 'fixture accrual',
            'reference_type' => 'Payment',
            'transaction_date' => Carbon::create(2026, 6, 10),
        ]);

        foreach ([[$unbilled->id, $total, 0.0], [$on->id, 0.0, $total]] as [$accountId, $dr, $cr]) {
            JournalEntry::forceCreate([
                'transaction_id' => $txn->id,
                'company_id' => $this->company->id,
                'account_id' => $accountId,
                'task_id' => $task->id,
                'transaction_date' => Carbon::create(2026, 6, 10),
                'description' => 'fixture accrual',
                'debit' => $dr,
                'credit' => $cr,
                'type' => 'payable',
                'type_reference_id' => $supplier->id,
                'name' => $supplier->name,
            ]);
        }

        return $task;
    }

    private function reassignDocuments(): \Illuminate\Support\Collection
    {
        return Transaction::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('sub_type', 'PAYEE_REASSIGN')
            ->orderBy('id')
            ->get();
    }

    private function callFlow(Task $task, Account $destination): array
    {
        $task->payment_method_account_id = $destination->id;
        $task->save();

        return app(TaskController::class)
            ->updateJournalPaymentMethod($task->fresh(), (int) $destination->id)
            ->getData(true);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_engine_on_posts_one_balanced_two_sided_reclassification(): void
    {
        config(['accounting.engine.enabled' => true]);

        $oldSupplier = Supplier::factory()->create();
        $newSupplier = Supplier::factory()->create();
        $from = $this->creditorLeaf('CTA3 Old Payable', '211901');
        $to = $this->creditorLeaf('CTA3 New Payable', '211902');

        $task = $this->accruedTask($oldSupplier, $from, total: 400.0);
        $task->supplier_id = $newSupplier->id;
        $task->save();

        $response = $this->callFlow($task, $to);

        $this->assertSame('success', $response['status']);
        $this->assertNotNull($response['data']['transaction_id']);

        $docs = $this->reassignDocuments();
        $this->assertCount(1, $docs);

        $lines = DB::table('journal_entries')->where('transaction_id', $docs->first()->id)->get();

        $this->assertCount(2, $lines, 'Exactly two legs: Dr old payable party / Cr new payable party.');
        $this->assertEqualsWithDelta(
            0.0,
            (float) $lines->sum('debit') - (float) $lines->sum('credit'),
            0.0005,
            'CT-F39: the legacy flow wrote 220,908.987 of credits against 477.800 of debits. The '
            .'engine document must balance.'
        );

        $debit = $lines->firstWhere('debit', '>', 0);
        $credit = $lines->firstWhere('credit', '>', 0);

        $this->assertSame($from->id, (int) $debit->account_id, 'Dr the account that was carrying the payable.');
        $this->assertSame($to->id, (int) $credit->account_id, 'Cr the newly chosen payable account.');
        $this->assertEqualsWithDelta(400.0, (float) $debit->debit, 0.0005);
        $this->assertEqualsWithDelta(400.0, (float) $credit->credit, 0.0005);

        $this->assertSame(
            $oldSupplier->id,
            (int) $debit->type_reference_id,
            'The released leg keeps the OLD supplier as its party.'
        );
        $this->assertSame(
            $newSupplier->id,
            (int) $credit->type_reference_id,
            'The new leg carries the NEW supplier, so the AP sub-ledger stays answerable.'
        );

        $this->assertSame('JV', $docs->first()->doc_type);
        $this->assertSame('PAYEE_REASSIGN', $docs->first()->sub_type);
        $this->assertStringStartsWith('task:'.$task->id.':supplier-reassign:', (string) $docs->first()->idempotency_key);
    }

    public function test_the_task_payable_actually_ends_up_on_the_new_account(): void
    {
        config(['accounting.engine.enabled' => true]);

        $supplier = Supplier::factory()->create();
        $from = $this->creditorLeaf('CTA3 Old Payable', '211903');
        $to = $this->creditorLeaf('CTA3 New Payable', '211904');

        $task = $this->accruedTask($supplier, $from, total: 400.0);
        $this->callFlow($task, $to);

        $netOn = fn (Account $a) => (float) DB::table('journal_entries')
            ->where('company_id', $this->company->id)
            ->where('task_id', $task->id)
            ->where('account_id', $a->id)
            ->sum(DB::raw('credit - debit'));

        $this->assertEqualsWithDelta(0.0, $netOn($from), 0.0005, 'The old account is released in full.');
        $this->assertEqualsWithDelta(400.0, $netOn($to), 0.0005, 'The new account now carries the whole payable.');
    }

    public function test_the_legacy_one_sided_writer_is_unreachable_when_the_engine_is_on(): void
    {
        config(['accounting.engine.enabled' => true]);

        $supplier = Supplier::factory()->create();
        $from = $this->creditorLeaf('CTA3 Old Payable', '211905');
        $to = $this->creditorLeaf('CTA3 New Payable', '211906');

        $task = $this->accruedTask($supplier, $from, total: 400.0);
        $before = Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->count();

        $this->callFlow($task, $to);

        $this->assertSame(
            $before + 1,
            Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->count(),
            'The engine path must post EXACTLY one document. The legacy writer also creates its own '
            .'Transaction row, so two would mean both paths ran.'
        );

        $legacy = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('description', 'like', 'Update payment account for:%')
            ->count();

        $this->assertSame(0, $legacy, "The legacy writer's own header must not exist.");

        $this->assertSame(
            0,
            DB::table('journal_entries')
                ->where('company_id', $this->company->id)
                ->where('description', 'like', 'Update For Whom to Pay: %')
                ->whereNull('posting_date')
                ->count(),
            'No legacy-shaped (posting_date NULL) line may be written by this flow with the engine on.'
        );
    }

    public function test_a_repeat_call_posts_nothing_because_there_is_nothing_left_to_move(): void
    {
        config(['accounting.engine.enabled' => true]);

        $supplier = Supplier::factory()->create();
        $from = $this->creditorLeaf('CTA3 Old Payable', '211907');
        $to = $this->creditorLeaf('CTA3 New Payable', '211908');

        $task = $this->accruedTask($supplier, $from, total: 400.0);

        $this->callFlow($task, $to);
        $second = $this->callFlow($task, $to);

        $this->assertSame('success', $second['status'], 'A retry is a success, not an error.');
        $this->assertNull($second['data']['transaction_id']);
        $this->assertCount(1, $this->reassignDocuments(), 'A retry must never post a second document.');
    }

    public function test_moving_back_and_forth_posts_a_distinct_document_each_time(): void
    {
        config(['accounting.engine.enabled' => true]);

        $supplier = Supplier::factory()->create();
        $a = $this->creditorLeaf('CTA3 Payable A', '211909');
        $b = $this->creditorLeaf('CTA3 Payable B', '211910');

        $task = $this->accruedTask($supplier, $a, total: 400.0);

        $this->callFlow($task, $b);
        $this->callFlow($task, $a);
        $this->callFlow($task, $b);

        $docs = $this->reassignDocuments();

        $this->assertCount(
            3,
            $docs,
            'A -> B -> A -> B is three real change events; the sequence in the idempotency key must '
            .'keep them distinct instead of collapsing the third onto the first.'
        );
        $this->assertSame(
            3,
            $docs->pluck('idempotency_key')->unique()->count(),
            'Three distinct idempotency keys.'
        );
    }

    public function test_a_task_that_never_carried_a_payable_posts_nothing(): void
    {
        config(['accounting.engine.enabled' => true]);

        $supplier = Supplier::factory()->create();
        $to = $this->creditorLeaf('CTA3 New Payable', '211911');

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => 0.0,
            'reference' => 'CTA3-E3-EMPTY',
        ]);

        $response = $this->callFlow($task, $to);

        $this->assertSame('success', $response['status']);
        $this->assertNull($response['data']['transaction_id']);
        $this->assertCount(0, $this->reassignDocuments());
    }

    public function test_the_builder_never_sweeps_a_client_advance_or_an_accrued_expense(): void
    {
        config(['accounting.engine.enabled' => true]);

        $supplier = Supplier::factory()->create();
        $from = $this->creditorLeaf('CTA3 Old Payable', '211912');
        $to = $this->creditorLeaf('CTA3 New Payable', '211913');

        $task = $this->accruedTask($supplier, $from, total: 400.0);

        // A liability that is NOT a supplier payable, tagged with the same task.
        $advance = Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'Commissions (Agents)')
            ->firstOrFail();

        $txn = Transaction::forceCreate([
            'company_id' => $this->company->id,
            'entity_id' => $this->company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => 25.0,
            'description' => 'unrelated liability',
            'reference_type' => 'Invoice',
            'transaction_date' => Carbon::create(2026, 6, 10),
        ]);
        $commissionExpense = Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'Commissions Expense (Agents)')
            ->firstOrFail();

        foreach ([[$commissionExpense->id, 25.0, 0.0], [$advance->id, 0.0, 25.0]] as [$accountId, $dr, $cr]) {
            JournalEntry::forceCreate([
                'transaction_id' => $txn->id,
                'company_id' => $this->company->id,
                'account_id' => $accountId,
                'task_id' => $task->id,
                'transaction_date' => Carbon::create(2026, 6, 10),
                'description' => 'unrelated liability',
                'debit' => $dr,
                'credit' => $cr,
                'type' => $dr > 0 ? 'expense' : 'payable',
                'name' => 'unrelated liability',
            ]);
        }

        $lines = app(SupplierReassignDraftBuilder::class)->buildLines(
            $task->fresh(),
            $this->company->id,
            $to,
            $supplier->id,
            $supplier->name,
        );

        $touched = array_map(fn ($l) => $l->accountId, $lines);

        $this->assertNotContains(
            $advance->id,
            $touched,
            'Only the Accounts Payable (2100) subtree is a supplier payable. Commission liabilities, '
            .'client advances and accrued expenses must never be moved by this flow.'
        );
        $this->assertEqualsWithDelta(400.0, array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $lines)), 0.0005);
    }

    /**
     * OFF-path parity — and, in the same breath, the proof of the defect. HEAD's legacy writer
     * credits the new account WITHOUT a matching debit unless a replicate-based heuristic happens
     * to fire, which is exactly how CT-A1 §0 item 2 measured 1,511 documents carrying
     * KWD 220,908.987 of credits against KWD 477.800 of debits. So this test deliberately does NOT
     * hold the company to the C1 balanced-ledger invariant — reproducing the imbalance IS the
     * assertion, the same technique ChatControllerPostingTest uses for its own HEAD-reproduction
     * case.
     */
    public function test_engine_off_still_runs_the_legacy_writer_unchanged_and_is_still_one_sided(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->invariantCompanyIds = [];

        $supplier = Supplier::factory()->create();
        $from = $this->creditorLeaf('CTA3 Old Payable', '211914');
        $to = $this->creditorLeaf('CTA3 New Payable', '211915');

        $task = $this->accruedTask($supplier, $from, total: 400.0);

        $response = $this->callFlow($task, $to);

        $this->assertSame('success', $response['status']);
        $this->assertCount(0, $this->reassignDocuments(), 'No engine document with the engine off.');
        $this->assertGreaterThan(
            0,
            Transaction::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->where('description', 'like', 'Update payment account for:%')
                ->count(),
            'OFF-path parity: the legacy writer still runs byte-for-byte for a company that has not '
            .'cut over.'
        );

        $imbalance = (float) DB::table('journal_entries')
            ->where('company_id', $this->company->id)
            ->whereNull('deleted_at')
            ->sum(DB::raw('debit - credit'));

        $this->assertEqualsWithDelta(
            -400.0,
            $imbalance,
            0.0005,
            'CT-F39 reproduced: the legacy writer credits the new account with no matching debit, '
            .'leaving the company ledger out by the full amount. That is the defect the engine path '
            .'above fixes — and the reason a cutover must not simply drop this flow.'
        );
    }

    public function test_an_engine_accrued_task_reassigns_off_the_service_payable_control_leaf(): void
    {
        // The post-cutover shape: E-iss put the payable on the per-service SERVICE_PAYABLE leaf
        // under 2120 Suppliers (Flights), not on a legacy Creditors child. The builder must reach
        // it, because 2120 is also under Accounts Payable (2100).
        config(['accounting.engine.enabled' => true]);

        $supplier = Supplier::factory()->create(['payable_trigger' => 'on_issue', 'payable_hold' => false]);
        $to = $this->creditorLeaf('CTA3 New Payable', '211916');

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => 320.0,
            'reference' => 'CTA3-E3-ENGINE',
            'issued_date' => Carbon::create(2026, 6, 10),
        ]);

        $accrual = app(TaskIssuancePayableService::class)->postIfDue($task);
        $this->assertNotNull($accrual, 'The E-iss accrual must exist for this scenario to mean anything.');

        $this->callFlow($task, $to);

        $netOnDestination = (float) DB::table('journal_entries')
            ->where('company_id', $this->company->id)
            ->where('task_id', $task->id)
            ->where('account_id', $to->id)
            ->sum(DB::raw('credit - debit'));

        $this->assertEqualsWithDelta(
            320.0,
            $netOnDestination,
            0.0005,
            'A payable accrued by the engine onto a SERVICE_PAYABLE control leaf must be reachable '
            .'by the reassignment flow.'
        );
    }
}
