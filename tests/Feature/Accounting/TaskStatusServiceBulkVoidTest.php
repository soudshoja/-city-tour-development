<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * W6.B (w6-brief.md "## Kinds" 5 -- "BULK VOID").
 *
 * Covers:
 *  - `atomic` mode: a mid-batch failing task rolls back the ENTIRE outer transaction -- zero
 *    tasks voided, zero sale documents reversed, even for tasks earlier in the list that would
 *    otherwise have succeeded.
 *  - `per_task_report` mode: a mid-batch failing task's own savepoint rolls back in isolation --
 *    every other task in the same batch still voids for real (a real reversed sale document
 *    posted through the engine), and the failure is reported in the per-task results list rather
 *    than aborting the whole call.
 *  - `TaskStatusService::bulkVoid()`'s own per-task delegation to {@see TaskStatusService::void()}
 *    -- no reimplementation of void mechanics in the bulk path.
 */
class TaskStatusServiceBulkVoidTest extends AccountingTestCase
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

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier} */
    private function makeCompanyAgentClientSupplier(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'w6b-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6B Test Supplier']);

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
            'reference' => 'PNR-' . uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ], $overrides));
    }

    /** Issues a task through the real W6.I path and returns [invoice, invoiceDetail]. */
    private function issueTask(Task $task): array
    {
        $result = $this->service->issue($task);
        $this->assertTrue($result['success'] ?? false, json_encode($result));

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $invoice = Invoice::find($invoiceDetail->invoice_id);

        return [$invoice, $invoiceDetail];
    }

    // ---------------------------------------------------------------------------------------
    // atomic mode
    // ---------------------------------------------------------------------------------------

    public function test_atomic_mode_voids_every_task_when_all_succeed(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $taskB = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $taskC = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($taskA);
        $this->issueTask($taskB);
        $this->issueTask($taskC);

        $outcome = $this->service->bulkVoid([$taskA->id, $taskB->id, $taskC->id], ['mode' => 'atomic']);

        $this->assertSame('atomic', $outcome['mode']);
        $this->assertCount(3, $outcome['results']);
        foreach ($outcome['results'] as $row) {
            $this->assertTrue($row['success']);
        }

        foreach ([$taskA, $taskB, $taskC] as $task) {
            $this->assertSame('void', $task->fresh()->ticket_status);
        }
    }

    public function test_atomic_mode_rolls_back_every_task_when_one_in_the_middle_fails(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier);
        // taskBad was never issued/invoiced -- void()'s own precondition
        // (ticket_status must be issued|reissued) refuses it, a genuine mid-batch failure.
        $taskBad = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'status' => 'on hold',
            'ticket_status' => null,
        ]);
        $taskC = $this->makeIssuedTask($company, $agent, $client, $supplier);

        [$invoiceA, $invoiceDetailA] = $this->issueTask($taskA);
        $this->issueTask($taskC);

        $saleTransactionA = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:' . $invoiceDetailA->id . ':sale')
            ->first();
        $this->assertNotNull($saleTransactionA);

        try {
            $this->service->bulkVoid([$taskA->id, $taskBad->id, $taskC->id], ['mode' => 'atomic']);
            $this->fail('bulkVoid() must rethrow in atomic mode when a task fails.');
        } catch (\Throwable $e) {
            // expected -- atomic mode propagates the failure so the whole outer transaction rolls back.
        }

        // Nothing voided -- not even taskA/taskC, which would have succeeded on their own.
        $this->assertNotSame('void', $taskA->fresh()->ticket_status);
        $this->assertNotSame('void', $taskC->fresh()->ticket_status);
        $this->assertSame('on hold', $taskBad->fresh()->status);

        // The real proof: taskA's sale document was never actually reversed on disk.
        $this->assertNull(
            Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransactionA->id)->first(),
            'atomic mode must not leave a partial reversal for a task earlier in the batch than the failing one.'
        );
    }

    // ---------------------------------------------------------------------------------------
    // per_task_report mode
    // ---------------------------------------------------------------------------------------

    public function test_per_task_report_mode_voids_the_good_tasks_and_reports_the_bad_one(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $taskBad = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'status' => 'on hold',
            'ticket_status' => null,
        ]);
        $taskC = $this->makeIssuedTask($company, $agent, $client, $supplier);

        [$invoiceA, $invoiceDetailA] = $this->issueTask($taskA);
        [$invoiceC, $invoiceDetailC] = $this->issueTask($taskC);

        $outcome = $this->service->bulkVoid(
            [$taskA->id, $taskBad->id, $taskC->id],
            ['mode' => 'per_task_report']
        );

        $this->assertSame('per_task_report', $outcome['mode']);
        $this->assertCount(3, $outcome['results']);

        $byId = collect($outcome['results'])->keyBy('task_id');
        $this->assertTrue($byId[$taskA->id]['success']);
        $this->assertFalse($byId[$taskBad->id]['success']);
        $this->assertNotNull($byId[$taskBad->id]['error']);
        $this->assertTrue($byId[$taskC->id]['success']);

        // The good tasks really voided -- real reversed sale documents on disk.
        $this->assertSame('void', $taskA->fresh()->ticket_status);
        $this->assertSame('void', $taskC->fresh()->ticket_status);

        $saleTransactionA = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:' . $invoiceDetailA->id . ':sale')
            ->first();
        $this->assertNotNull(
            Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransactionA->id)->first(),
            'A good task in a per_task_report batch must actually be reversed, not merely status-flipped.'
        );

        // The bad task is untouched -- its own savepoint rolled back, the outer commit did not
        // undo it, and the failure never touched its status.
        $this->assertSame('on hold', $taskBad->fresh()->status);
    }

    public function test_per_task_report_mode_is_idempotent_per_task_on_a_second_call(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($taskA);

        $first = $this->service->bulkVoid([$taskA->id], ['mode' => 'per_task_report']);
        $this->assertTrue($first['results'][0]['success']);

        $countAfterFirst = Transaction::withoutGlobalScopes()->count();

        $second = $this->service->bulkVoid([$taskA->id], ['mode' => 'per_task_report']);

        $this->assertTrue($second['results'][0]['success']);
        $this->assertSame($countAfterFirst, Transaction::withoutGlobalScopes()->count(), 'A second bulk-void call on an already-voided task must post nothing new.');
    }

    // ---------------------------------------------------------------------------------------
    // Mode resolution
    // ---------------------------------------------------------------------------------------

    public function test_default_mode_is_read_from_the_company_bulk_void_mode_option(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.bulk_void_mode',
            'value' => 'per_task_report',
            'type' => 'string',
        ]);

        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $taskBad = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'status' => 'on hold',
            'ticket_status' => null,
        ]);
        $this->issueTask($taskA);

        // No explicit 'mode' opt -- must fall back to the company's own setting.
        $outcome = $this->service->bulkVoid([$taskA->id, $taskBad->id]);

        $this->assertSame('per_task_report', $outcome['mode']);
        $this->assertSame('void', $taskA->fresh()->ticket_status);
    }
}
