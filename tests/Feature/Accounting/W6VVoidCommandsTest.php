<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
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
 * W6.V -- the two console commands the sub-wave rewires:
 *  - `void-tasks:process-financials` (ProcessVoidTasksFinancials): --company-id filtered IN the
 *    query, engine-ON idempotency checked structurally (never the broken description-LIKE probe).
 *  - `tasks:process-expired-confirmed` (ProcessExpiredConfirmedTasks): now runs BOTH
 *    TaskStatusService::expire() (never-invoiced) and TaskStatusService::autoVoidExpiredInvoiced()
 *    (already-invoiced, W6.V Kind 2 AUTO_VOID) for every supplier, no Jazeera-only special case.
 */
class W6VVoidCommandsTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
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
        $agentType = AgentType::firstOrCreate(['name' => 'w6v-command-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6V Command Test Supplier']);

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

    public function test_process_void_tasks_financials_voids_a_linked_task_through_the_engine(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $original = $this->makeIssuedTask($company, $agent, $client, $supplier);
        (new TaskStatusService)->issue($original);

        $voidRow = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'void',
            'reference' => $original->reference,
            'original_task_id' => $original->id,
            'price' => 0.0,
            'total' => 0.0,
        ]);

        Artisan::call('void-tasks:process-financials', ['--company-id' => $company->id]);

        $original->refresh();
        $this->assertSame('void', $original->ticket_status);
    }

    public function test_process_void_tasks_financials_is_idempotent_on_a_second_run(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $original = $this->makeIssuedTask($company, $agent, $client, $supplier);
        (new TaskStatusService)->issue($original);

        Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'void',
            'reference' => $original->reference,
            'original_task_id' => $original->id,
            'price' => 0.0,
            'total' => 0.0,
        ]);

        Artisan::call('void-tasks:process-financials', ['--company-id' => $company->id]);
        $countAfterFirst = Transaction::withoutGlobalScopes()->count();

        Artisan::call('void-tasks:process-financials', ['--company-id' => $company->id]);
        $countAfterSecond = Transaction::withoutGlobalScopes()->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'A second run must post nothing new for an already-processed void row.');
    }

    public function test_process_void_tasks_financials_company_filter_is_applied_in_the_query(): void
    {
        [$companyA, $agentA, $clientA, $supplierA] = $this->makeCompanyAgentClientSupplier();
        [$companyB, $agentB, $clientB, $supplierB] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($companyA);
        $this->enableEngine($companyB);
        $this->trackCompanyForInvariants($companyA->id);
        $this->trackCompanyForInvariants($companyB->id);

        $originalB = $this->makeIssuedTask($companyB, $agentB, $clientB, $supplierB);
        (new TaskStatusService)->issue($originalB);

        Task::factory()->create([
            'company_id' => $companyB->id,
            'agent_id' => $agentB->id,
            'client_id' => $clientB->id,
            'supplier_id' => $supplierB->id,
            'type' => 'flight',
            'status' => 'void',
            'reference' => $originalB->reference,
            'original_task_id' => $originalB->id,
            'price' => 0.0,
            'total' => 0.0,
        ]);

        // Filtered to company A only -- company B's void row must be untouched.
        Artisan::call('void-tasks:process-financials', ['--company-id' => $companyA->id]);

        $originalB->refresh();
        $this->assertNotSame('void', $originalB->ticket_status, 'The --company-id filter must be applied in the query, not skip in-memory only.');
    }

    public function test_process_expired_confirmed_tasks_auto_voids_an_already_invoiced_task(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        (new TaskStatusService)->issue($task);
        $task->update(['status' => 'confirmed', 'deadline_at' => now()->subHours(5)]);

        Artisan::call('tasks:process-expired-confirmed', ['--company-id' => $company->id]);

        $task->refresh();
        $this->assertSame('void', $task->ticket_status);
    }

    public function test_process_expired_confirmed_tasks_expires_a_never_invoiced_task_with_no_ledger(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'confirmed',
            'reference' => 'NEVER-INVOICED-' . uniqid(),
            'deadline_at' => now()->subHours(5),
            'price' => 500.0,
            'total' => 350.0,
        ]);

        Artisan::call('tasks:process-expired-confirmed', ['--company-id' => $company->id]);

        $task->refresh();
        $this->assertSame('expired', $task->status);
        $this->assertSame(0, InvoiceDetail::where('task_id', $task->id)->count());
    }
}
