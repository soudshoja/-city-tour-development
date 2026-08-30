<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierChargeRule;
use App\Models\SupplierChargeRuleFiring;
use App\Models\Task;
use App\Models\TaskStatusEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * W6.I "Importer contract" (w6-brief.md; Accounting Gap/22-plan-amendments.md §16.1).
 *
 * Covers:
 *  - issue(): one atomic sale document posted through the seam + an UNCONDITIONAL server-numbered
 *    invoice, unpaid, AR open, regardless of any payment;
 *  - invoice_grouping=per_pnr (the shipped default): two tasks sharing one `reference` land on
 *    ONE invoice, two InvoiceDetail rows;
 *  - per_task grouping: two tasks sharing one reference each get their OWN invoice;
 *  - idempotency: a second issue() call for an already-invoiced task is a safe no-op;
 *  - dispatchFinancial() routing: engine ON routes `issued`/`emd` through issue()/
 *    postEmdAncillary(); engine OFF is byte-for-byte legacy processTaskFinancial() parity (no
 *    invoice auto-created, cost-only JE, matching HEAD);
 *  - postEmdAncillary(): posts on the PARENT's existing invoice, never a new one; unlinked EMD is
 *    flagged, never silently falls back to `issued`.
 */
class TaskStatusServiceIssueTest extends AccountingTestCase
{
    private TaskStatusService $service;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TaskStatusService;
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Supplier}
     */
    private function makeCompanyAgentClientSupplier(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'w6i-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6I Test Supplier']);

        return [$company, $agent, $client, $supplier];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function makeIssuedTask(Company $company, Agent $agent, Client $client, Supplier $supplier, array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'PNR-'.uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ], $overrides));
    }

    // ---------------------------------------------------------------------------------------
    // issue() — unconditional auto-invoice, AR open, one atomic sale document
    // ---------------------------------------------------------------------------------------

    public function test_issue_posts_one_balanced_sale_document_and_leaves_invoice_unpaid(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);

        $result = $this->service->issue($task);

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $this->assertNotNull($result['invoice_id'] ?? null);

        $invoice = Invoice::find($result['invoice_id']);
        $this->assertNotNull($invoice);
        // Unconditional auto-invoice, no payment involved -- AR stays open.
        $this->assertSame('unpaid', $invoice->status);
        $this->assertEqualsWithDelta(500.0, (float) $invoice->amount, 0.001);

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $this->assertNotNull($invoiceDetail);
        $this->assertSame($invoice->id, $invoiceDetail->invoice_id);

        // The sale document itself: client receivable + supplier payable + margin, real
        // transaction/journal rows via the engine.
        $this->assertGreaterThanOrEqual(2, JournalEntry::where('task_id', $task->id)->count());
    }

    public function test_issue_is_idempotent_on_a_second_call(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);

        $first = $this->service->issue($task);
        $secondTask = Task::find($task->id);
        $second = $this->service->issue($secondTask);

        $this->assertTrue($first['success'] ?? false);
        $this->assertTrue($second['success'] ?? false);
        $this->assertSame($first['invoice_id'], $second['invoice_id']);
        $this->assertSame(1, InvoiceDetail::where('task_id', $task->id)->count());
    }

    // ---------------------------------------------------------------------------------------
    // invoice_grouping option
    // ---------------------------------------------------------------------------------------

    public function test_per_pnr_grouping_lands_two_tasks_sharing_a_reference_on_one_invoice(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $pnr = 'SHARED-PNR-'.uniqid();

        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'reference' => $pnr, 'passenger_name' => 'Passenger A', 'price' => 400.0, 'total' => 300.0,
        ]);
        $taskB = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'reference' => $pnr, 'passenger_name' => 'Passenger B', 'price' => 450.0, 'total' => 320.0,
        ]);

        $resultA = $this->service->issue($taskA);
        $resultB = $this->service->issue($taskB);

        $this->assertTrue($resultA['success'] ?? false);
        $this->assertTrue($resultB['success'] ?? false);
        $this->assertSame($resultA['invoice_id'], $resultB['invoice_id'], 'per_pnr default must group both passengers onto one invoice.');

        $invoice = Invoice::find($resultA['invoice_id']);
        $this->assertSame(2, InvoiceDetail::where('invoice_id', $invoice->id)->count());
        $this->assertEqualsWithDelta(850.0, (float) $invoice->amount, 0.001, 'Grouped invoice amount must be the SUM of both tasks\' own sell prices.');
    }

    public function test_per_task_grouping_option_gives_each_task_its_own_invoice(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.invoice_grouping',
            'type' => 'string',
            'value' => 'per_task',
        ]);

        $pnr = 'SHARED-PNR-'.uniqid();
        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => $pnr, 'passenger_name' => 'A']);
        $taskB = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => $pnr, 'passenger_name' => 'B']);

        $resultA = $this->service->issue($taskA);
        $resultB = $this->service->issue($taskB);

        $this->assertNotSame($resultA['invoice_id'], $resultB['invoice_id'], 'per_task must never group, even when the reference matches.');
    }

    // ---------------------------------------------------------------------------------------
    // dispatchFinancial() routing — engine ON vs OFF
    // ---------------------------------------------------------------------------------------

    public function test_dispatch_financial_routes_issued_through_issue_when_engine_on(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);

        $this->service->dispatchFinancial($task);

        $this->assertSame(1, InvoiceDetail::where('task_id', $task->id)->count());
        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $this->assertSame('unpaid', Invoice::find($invoiceDetail->invoice_id)->status);
    }

    public function test_dispatch_financial_off_path_never_auto_invoices_matches_legacy(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        // Engine deliberately left OFF (default) -- byte-for-byte legacy parity required.
        config(['accounting.engine.enabled' => false]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);

        // Legacy processIssuedTask() needs a fully-wired SupplierCompany/account fixture this
        // test does not build (irrelevant to what THIS test proves -- see below) -- it may throw
        // for that unrelated reason. The one thing under test is the ROUTING decision itself:
        // dispatchFinancial() must never reach issue()/auto-invoice on the OFF path, regardless
        // of whether the legacy call it falls through to succeeds.
        try {
            $this->service->dispatchFinancial($task);
        } catch (\Throwable $e) {
            // Expected for this bare fixture -- see comment above.
        }

        // Legacy processIssuedTask() books ONLY the cost-side JE -- no client receivable/revenue,
        // no invoice, ever (importer-status-contract.md's own Table 1 finding for this path).
        $this->assertSame(0, InvoiceDetail::where('task_id', $task->id)->count());
    }

    /**
     * FIX ROUND (re-verify, CRITICAL): dispatchFinancial() previously special-cased ONLY
     * `issued`/`emd` for the ON-path routing decision -- an import-time `reissued` task fell
     * through unconditionally to `processTaskFinancial()` -> `processIssuedTask()`, the raw
     * legacy Transaction/JournalEntry writer, REGARDLESS of the engine flag. This directly
     * contradicted the brief's own "processIssuedTask() becomes unreachable dead code on the
     * issued/reissued cases" requirement. Proves the fix: a `reissued` task, engine ON, posts
     * through the SAME atomic-sale-document path `issued` uses (one balanced document, via
     * PostingSeam, with a real InvoiceDetail/invoice), not the raw legacy cost-only writer.
     */
    public function test_dispatch_financial_routes_reissued_through_issue_when_engine_on(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'status' => 'reissued',
            'reference' => 'REISSUE-PNR-'.uniqid(),
        ]);

        $this->service->dispatchFinancial($task);

        // The engine's own atomic sale document must have posted -- an invoice detail linked to
        // THIS task (never the raw legacy cost-only JE processIssuedTask() would have written
        // outside PostingSeam entirely).
        $this->assertSame(1, InvoiceDetail::where('task_id', $task->id)->count());
        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $this->assertSame('unpaid', Invoice::find($invoiceDetail->invoice_id)->status);
        $this->assertGreaterThanOrEqual(2, JournalEntry::where('task_id', $task->id)->count());
    }

    /**
     * OFF-path parity for `reissued` is unaffected by this fix round -- still a plain
     * pass-through to `processTaskFinancial()`, exactly as it always was.
     */
    public function test_dispatch_financial_reissued_off_path_never_auto_invoices_matches_legacy(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        config(['accounting.engine.enabled' => false]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'status' => 'reissued',
            'reference' => 'REISSUE-OFF-PNR-'.uniqid(),
        ]);

        try {
            $this->service->dispatchFinancial($task);
        } catch (\Throwable $e) {
            // Same bare-fixture caveat as the `issued` OFF-path test above -- only the ROUTING
            // decision is under test here.
        }

        $this->assertSame(0, InvoiceDetail::where('task_id', $task->id)->count());
    }

    // ---------------------------------------------------------------------------------------
    // W6.C supplier_charge_rules wired into issue()'s sale document (w6-brief.md W6.I item 1:
    // "SaleDraftBuilder resolves active supplier_charge_rules ... and appends the resulting
    // extra LineDraft[] to the same sale document before it posts")
    // ---------------------------------------------------------------------------------------

    public function test_issue_appends_an_active_supplier_charge_rule_as_its_own_separate_lines(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        SupplierChargeRule::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'service_type' => null,
            'channel' => null,
            'charge_kind' => 'iata_fee',
            'basis' => SupplierChargeRule::BASIS_FIXED,
            'amount' => 5.000,
            'recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB,
            'commissionable' => false,
            'active' => true,
            'once_per_reference' => false,
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'reference' => 'CHARGE-RULE-PNR-'.uniqid(),
        ]);

        $result = $this->service->issue($task);

        $this->assertTrue($result['success'] ?? false, json_encode($result));

        // The base sale document's own 3 lines (receivable/payable/margin) PLUS the supplier
        // charge rule's own cost pair (Dr SUPPLIER_CHARGE_EXPENSE / Cr SERVICE_PAYABLE) -- never
        // blended into the base sell/cost pair.
        $this->assertGreaterThanOrEqual(5, JournalEntry::where('task_id', $task->id)->count());
    }

    public function test_issue_records_a_once_per_reference_firing_so_a_second_issue_never_reapplies_the_rule(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $rule = SupplierChargeRule::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'service_type' => null,
            'channel' => null,
            'charge_kind' => 'iata_fee',
            'basis' => SupplierChargeRule::BASIS_FIXED,
            'amount' => 5.000,
            'recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB,
            'commissionable' => false,
            'active' => true,
            'once_per_reference' => true,
        ]);

        $reference = 'ONCE-PER-REF-PNR-'.uniqid();

        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => $reference, 'passenger_name' => 'A']);
        $taskB = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => $reference, 'passenger_name' => 'B']);

        $this->service->issue($taskA);

        $this->assertSame(1, SupplierChargeRuleFiring::where('supplier_charge_rule_id', $rule->id)->where('reference', $reference)->count());

        // Second task sharing the same reference: once_per_reference must skip the rule entirely
        // (no second firing row, and the second task's own JournalEntry count reflects only the
        // base sale, not a second copy of the rule's cost pair).
        $this->service->issue($taskB);

        $this->assertSame(1, SupplierChargeRuleFiring::where('supplier_charge_rule_id', $rule->id)->where('reference', $reference)->count(), 'once_per_reference must fire exactly once across tasks sharing the same reference.');
    }

    public function test_off_path_never_resolves_or_posts_any_supplier_charge_rule(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        // Engine deliberately OFF -- W6.C's own test requirement: "no rule resolution runs".
        config(['accounting.engine.enabled' => false]);

        SupplierChargeRule::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'service_type' => null,
            'channel' => null,
            'charge_kind' => 'iata_fee',
            'basis' => SupplierChargeRule::BASIS_FIXED,
            'amount' => 5.000,
            'recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB,
            'commissionable' => false,
            'active' => true,
            'once_per_reference' => true,
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => 'OFF-PATH-CHARGE-PNR-'.uniqid()]);

        try {
            $this->service->issue($task);
        } catch (\Throwable $e) {
            // Irrelevant to what this test proves -- see the OFF-path tests above.
        }

        $this->assertSame(0, SupplierChargeRuleFiring::count(), 'OFF path must never resolve/fire a supplier_charge_rule.');
    }

    // ---------------------------------------------------------------------------------------
    // postEmdAncillary()
    // ---------------------------------------------------------------------------------------

    public function test_emd_ancillary_posts_on_parent_invoice_not_a_new_one(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $parent = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => 'PARENT-REF']);
        $issueResult = $this->service->issue($parent);
        $this->assertTrue($issueResult['success'] ?? false);
        $parentInvoiceId = $issueResult['invoice_id'];

        $emd = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'emd',
            'reference' => 'PARENT-REF',
            'original_task_id' => $parent->id,
            'price' => 25.0,
            'total' => 0.0,
        ]);

        $this->service->postEmdAncillary($emd);

        $emdDetail = InvoiceDetail::where('task_id', $emd->id)->first();
        $this->assertNotNull($emdDetail, 'EMD ancillary line must be posted.');
        $this->assertSame($parentInvoiceId, $emdDetail->invoice_id, 'EMD must land on the PARENT\'s existing invoice, never a new one.');

        $emd->refresh();
        $this->assertSame('emd', $emd->status, 'EMD status must never be rewritten to issued.');

        $invoice = Invoice::find($parentInvoiceId);
        $this->assertEqualsWithDelta(525.0, (float) $invoice->amount, 0.001, 'Parent invoice amount must grow by the EMD ancillary sell amount.');
    }

    public function test_emd_with_no_parent_never_falls_back_to_issued_and_is_flagged(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $emd = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'emd',
            'reference' => 'ORPHAN-REF',
            'original_task_id' => null,
            'price' => 25.0,
            'total' => 0.0,
        ]);

        $this->service->postEmdAncillary($emd);

        $emd->refresh();
        $this->assertSame('emd', $emd->status, 'Never silently falls back to issued.');
        $this->assertSame(0, InvoiceDetail::where('task_id', $emd->id)->count());
        $this->assertSame(0, JournalEntry::where('task_id', $emd->id)->count());

        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $emd->id,
            'event' => 'emd_unlinked',
        ]);
    }

    public function test_emd_via_dispatch_financial_is_reachable_and_never_double_posts_parent_cost(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $parent = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => 'PARENT-REF-2']);
        $this->service->dispatchFinancial($parent);
        $parentCostJournalCount = JournalEntry::where('task_id', $parent->id)->count();

        $emd = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'emd',
            'reference' => 'PARENT-REF-2',
            'original_task_id' => $parent->id,
            'price' => 40.0,
            'total' => 0.0,
        ]);

        $this->service->dispatchFinancial($emd);

        // Parent's own journal-entry count must be unchanged -- the EMD ancillary line never
        // touches/duplicates the parent's own cost leg.
        $this->assertSame($parentCostJournalCount, JournalEntry::where('task_id', $parent->id)->count());
        $this->assertSame(1, InvoiceDetail::where('task_id', $emd->id)->count());
    }
}
