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
use App\Services\Accounting\TaskIssuancePayableService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 wave 2, item W2-4 — wave 1's E3 "Update For Whom to Pay" feeder re-checked against owner
 * ruling R-CT3.
 *
 * Reassignment MOVES a payable between parties. It must never CREATE one. The question W2-4 asks
 * is the one wave 1 never had to: what happens when the task's payable was deliberately GATED OFF
 * — supplier on hold, trigger not reached, `manual` — and somebody reassigns the supplier anyway?
 *
 * The answer must be "nothing posts", and it is guaranteed twice over:
 *
 *   1. **Structurally.** {@see \App\Services\Accounting\SupplierReassignDraftBuilder} derives its
 *      debits from the ledger's CURRENT net credit per AP leaf for the task, so a task that was
 *      never accrued has no position to move.
 *   2. **Observably.** {@see \App\Services\Accounting\SupplierPayableRule} is consulted at the
 *      call site so the log and the response DISTINGUISH "gated off" from "already reassigned".
 *      Both used to read as an indistinguishable "nothing to move", and an operator cannot tell a
 *      working gate from a broken feeder that way — which is how CT-F39's one-sided writes went
 *      unnoticed across 1,511 documents.
 *
 * The matrix below is (payable gate state) × (does a position exist), plus the two cases that
 * matter on the other side: a committed task really does move, and it moves the PARTY with it.
 */
class W24ReassignRespectsPayableGateTest extends AccountingTestCase
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
        config(['accounting.engine.enabled' => true]);
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

    /**
     * A task at $status whose supplier carries $supplierAttributes, run through the REAL issuance
     * feeder — so whether a payable exists is decided by R-CT3 exactly as it is in production,
     * never faked by inserting rows.
     */
    private function taskThroughTheRealFeeder(array $supplierAttributes, string $status, float $total = 400.0): Task
    {
        $supplier = Supplier::factory()->create($supplierAttributes);

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => $status,
            'total' => $total,
            'reference' => 'CTA3-W24-'.uniqid(),
            'issued_date' => Carbon::create(2026, 6, 10),
            // Explicit: the Task factory seeds a voucher_status, and the `on_voucher` row of the
            // matrix below is specifically about a task with NO voucher raised.
            'voucher_status' => null,
        ]);

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        return $task->fresh();
    }

    private function callFlow(Task $task, Account $destination): array
    {
        $task->payment_method_account_id = $destination->id;
        $task->save();

        $response = app(TaskController::class)->updateJournalPaymentMethod($task->fresh(), (int) $destination->id);

        return json_decode($response->getContent(), true);
    }

    private function reassignDocuments(): \Illuminate\Support\Collection
    {
        return Transaction::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('sub_type', 'PAYEE_REASSIGN')
            ->orderBy('id')
            ->get();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The matrix
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Cases 1-4: every way R-CT3 can gate a payable off. In each, no accrual exists, so a
     * reassignment must post NOTHING and must say WHY.
     *
     * @dataProvider gatedOffMatrix
     */
    public function test_a_reassignment_on_a_gated_off_task_posts_nothing_and_names_the_gate(
        array $supplierAttributes,
        string $status,
        string $expectedReason
    ): void {
        $task = $this->taskThroughTheRealFeeder($supplierAttributes, $status);
        $destination = $this->creditorLeaf('Destination Supplier', '21197');

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->where('sub_type', 'SUPPLIER_ACCRUAL')->count(),
            'Fixture check: R-CT3 gated this payable off, so nothing was accrued.'
        );

        $payload = $this->callFlow($task, $destination);

        $this->assertSame('success', $payload['status']);
        $this->assertNull($payload['data']['transaction_id'], 'A gated-off task must post no reassignment document.');
        $this->assertTrue($payload['data']['payable_gated_off'], 'The response must say the payable was gated off, not merely that nothing moved.');
        $this->assertSame($expectedReason, $payload['data']['reason']);
        $this->assertCount(0, $this->reassignDocuments());

        // And nothing was created anywhere: this is the "must not CREATE a payable" guarantee.
        $this->assertSame(
            0.0,
            (float) JournalEntry::where('company_id', $this->company->id)->where('account_id', $destination->id)->sum('credit')
        );
    }

    public static function gatedOffMatrix(): array
    {
        return [
            'supplier on hold' => [['payable_trigger' => 'on_issue', 'payable_hold' => true], 'issued', 'supplier_payable_hold'],
            'trigger not reached (confirmed under on_issue)' => [['payable_trigger' => 'on_issue'], 'confirmed', 'status_not_committed'],
            'manual trigger' => [['payable_trigger' => 'manual'], 'issued', 'trigger_manual'],
            'no voucher raised under on_voucher' => [['payable_trigger' => 'on_voucher'], 'issued', 'no_voucher_raised'],
        ];
    }

    /**
     * Case 5: the committed case. A task whose payable IS accrued moves between parties — one
     * debit on the leaf that carried it, one credit on the destination, balanced, with the party
     * preserved on both legs so the supplier sub-ledger stays answerable after the move.
     */
    public function test_a_committed_task_moves_its_payable_between_parties(): void
    {
        $task = $this->taskThroughTheRealFeeder(['payable_trigger' => 'on_issue'], 'issued', 400.0);

        $accrual = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('sub_type', 'SUPPLIER_ACCRUAL')
            ->firstOrFail();

        $payableLine = JournalEntry::where('transaction_id', $accrual->id)->where('credit', '>', 0)->firstOrFail();
        $sourceAccountId = (int) $payableLine->account_id;

        $destination = $this->creditorLeaf('New Payee', '21198');
        $payload = $this->callFlow($task, $destination);

        $this->assertNotNull($payload['data']['transaction_id']);
        $this->assertFalse($payload['data']['payable_gated_off'] ?? false);

        $documents = $this->reassignDocuments();
        $this->assertCount(1, $documents);

        $lines = JournalEntry::where('transaction_id', $documents->first()->id)->get();
        $this->assertCount(2, $lines, 'Exactly one debit off the old leaf and one credit to the new one.');
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0005);

        $debit = $lines->firstWhere('debit', '>', 0);
        $credit = $lines->firstWhere('credit', '>', 0);

        $this->assertSame($sourceAccountId, (int) $debit->account_id, 'The debit releases the leaf that actually carried the payable.');
        $this->assertSame($destination->id, (int) $credit->account_id);
        $this->assertEqualsWithDelta(400.0, (float) $credit->credit, 0.0005);

        $this->assertSame((int) $task->supplier_id, (int) $debit->type_reference_id, 'Party preserved on the releasing leg.');
        $this->assertSame((int) $task->supplier_id, (int) $credit->type_reference_id, 'Party preserved on the receiving leg.');
    }

    /**
     * Case 6: idempotence by construction. A second call finds nothing left to move — and reports
     * that as "nothing to move", NOT as "gated off", because the payable is very much accrued.
     * The two must not blur into each other; telling them apart is the whole point of W2-4.
     */
    public function test_a_second_reassignment_reports_nothing_to_move_not_gated_off(): void
    {
        $task = $this->taskThroughTheRealFeeder(['payable_trigger' => 'on_issue'], 'issued', 400.0);
        $destination = $this->creditorLeaf('New Payee', '21199');

        $this->callFlow($task, $destination);
        $payload = $this->callFlow($task->fresh(), $destination);

        $this->assertNull($payload['data']['transaction_id']);
        $this->assertFalse($payload['data']['payable_gated_off'], 'The payable IS accrued; this is a no-op retry, not a gate.');
        $this->assertSame('committed', $payload['data']['reason']);
        $this->assertCount(1, $this->reassignDocuments(), 'Still exactly one document after two calls.');
    }

    /**
     * Case 7: a held task that is LATER released still reassigns correctly. The gate is a
     * point-in-time decision, not a permanent mark on the task — the same "status transitions
     * drive posting in both directions" property wave 1 built into the accrual.
     */
    public function test_a_held_task_released_later_then_reassigns_normally(): void
    {
        $task = $this->taskThroughTheRealFeeder(['payable_trigger' => 'on_issue', 'payable_hold' => true], 'issued', 250.0);
        $destination = $this->creditorLeaf('New Payee', '21200');

        $payload = $this->callFlow($task, $destination);
        $this->assertTrue($payload['data']['payable_gated_off']);

        // The hold is lifted and the task dispatches again: now it accrues.
        Supplier::withoutGlobalScopes()->whereKey($task->supplier_id)->update(['payable_hold' => false]);
        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->where('sub_type', 'SUPPLIER_ACCRUAL')->count()
        );

        $payload = $this->callFlow($task->fresh(), $destination);

        $this->assertNotNull($payload['data']['transaction_id']);
        $this->assertCount(1, $this->reassignDocuments());
    }
}
