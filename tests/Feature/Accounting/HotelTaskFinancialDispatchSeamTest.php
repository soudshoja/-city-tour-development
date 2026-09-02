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
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * W7.Y fix (gate item 4, BLOCKER): `UpdateHotelTaskStatus` (`app:update-hotel-status`, scheduled
 * every 15 minutes -- app/Console/Kernel.php), `UpdateHotelStatusWithoutCancellationDate`, and
 * `UpdateHotelTaskWithSupplierPayDateCOA` previously called
 * `(new TaskController())->processTaskFinancial($task)` directly, bypassing
 * `TaskStatusService::dispatchFinancial()`'s engine-ON interception entirely, regardless of the
 * flag -- so a hotel task crossing its cancellation deadline through this scheduled sweep was
 * NEVER routed through the posting engine's `issue()` path, no matter what `posting_engine_enabled`
 * said. Fixed by routing all three through `app(TaskStatusService::class)->dispatchFinancial($task)`,
 * which already intercepts `status === 'issued'` (set by each command immediately before this
 * call) on the ON path via `issue()`, and falls straight through to the unchanged
 * `processTaskFinancial()` on the OFF path -- byte-identical to what each command did before this
 * fix. This suite proves the ROUTING decision for `UpdateHotelTaskStatus` (the deadline-driven,
 * every-15-minutes command explicitly named in the gate) end-to-end through the real Artisan
 * command, not just a direct `dispatchFinancial()` call -- mirroring
 * TaskStatusServiceIssueTest::test_dispatch_financial_routes_issued_through_issue_when_engine_on()'s
 * own "InvoiceDetail exists / Invoice unpaid" distinguishing signal (legacy `processIssuedTask()`
 * books ONLY the cost-side JE -- no client receivable/revenue, no invoice, ever -- so InvoiceDetail
 * presence unambiguously proves the engine's `issue()` ran instead of the raw legacy writer), plus
 * an explicit `idempotency_key` check on the posted document.
 */
class HotelTaskFinancialDispatchSeamTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
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
        $agentType = AgentType::firstOrCreate(['name' => 'w7y-hotel-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W7Y Hotel Test Supplier']);

        return [$company, $agent, $client, $supplier];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    /**
     * A hotel task past its own cancellation deadline -- exactly the state
     * `UpdateHotelTaskStatus::handle()`'s own `Date::now()->greaterThanOrEqualTo($cancellationDeadline)`
     * check picks up and flips to `status = 'issued'` before dispatching financials.
     */
    private function makeDeadlinePassedHotelTask(Company $company, Agent $agent, Client $client, Supplier $supplier): Task
    {
        return Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
            'status' => 'confirmed',
            'reference' => 'HTL-PNR-'.uniqid(),
            'price' => 300.0,
            'total' => 210.0,
            'cancellation_deadline' => now()->subDay(),
        ]);
    }

    public function test_engine_on_routes_the_scheduled_deadline_sweep_through_the_engines_issue_path(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeDeadlinePassedHotelTask($company, $agent, $client, $supplier);

        Artisan::call('app:update-hotel-status');

        $task->refresh();
        $this->assertSame('issued', $task->status, 'The command must still flip the task to issued regardless of the engine flag.');

        // Distinguishing signal (same one TaskStatusServiceIssueTest's own dispatchFinancial()
        // routing tests use): legacy processTaskFinancial() -> processIssuedTask() never creates
        // an InvoiceDetail/Invoice at all (cost-only JE) -- its PRESENCE here proves
        // dispatchFinancial()'s issue() path actually ran, not the raw legacy bypass this fix
        // closes.
        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $this->assertNotNull($invoiceDetail, 'Engine ON must route this hotel task through TaskStatusService::issue(), which always creates an InvoiceDetail -- the raw legacy bypass this fix closes never does.');
        $this->assertSame('unpaid', Invoice::find($invoiceDetail->invoice_id)->status);

        // The engine document itself carries a real idempotency_key -- never true of a legacy
        // Transaction row.
        $posted = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotNull('idempotency_key')
            ->first();
        $this->assertNotNull($posted, 'Expected a real engine-posted document carrying an idempotency_key.');
    }

    public function test_engine_off_matches_legacy_and_never_auto_invoices(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        // Engine deliberately left OFF (default) -- byte-for-byte legacy parity required.
        config(['accounting.engine.enabled' => false]);

        $task = $this->makeDeadlinePassedHotelTask($company, $agent, $client, $supplier);

        // Legacy processIssuedTask() needs a fully-wired SupplierCompany/account fixture this
        // test does not build (irrelevant to what THIS test proves) -- it may throw for that
        // unrelated reason (the command itself catches \Throwable around the financial-dispatch
        // call and logs it, never aborting the status flip -- see each command's own try/catch).
        // The one thing under test is the ROUTING decision: dispatchFinancial() must never reach
        // issue()/auto-invoice on the OFF path, regardless of whether the legacy call it falls
        // through to succeeds.
        Artisan::call('app:update-hotel-status');

        $task->refresh();
        $this->assertSame('issued', $task->status, 'The command must still flip the task to issued on the OFF path too -- only the financial-posting path differs.');

        $this->assertSame(
            0,
            InvoiceDetail::where('task_id', $task->id)->count(),
            'OFF path must be byte-identical to legacy: processIssuedTask() never auto-creates an InvoiceDetail/Invoice.'
        );
    }
}
