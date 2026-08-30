<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\TaskController;
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
 * W6.V (w6-brief.md "Consolidation + fixes": "revertFinancialsForTask/Void, handleClientChange,
 * handleAmountChange: delete-by-description -> reverse() + repost via engine keys"). Exercises
 * TaskController's own private methods directly via reflection -- they are genuinely internal
 * (called only from handleStatusChange()/update()'s own private call chain), so this is the same
 * "unit-test the effect through the real private method" approach the codebase itself has no
 * public HTTP-route shortcut for without standing up the full update() request/validation stack,
 * which is not what this fix is actually about (the fix is the ON-path BODY of these four
 * methods, not update()'s own request handling).
 */
class TaskControllerW6VRevertFinancialsTest extends AccountingTestCase
{
    private TaskController $controller;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = app(TaskController::class);
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod(TaskController::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->controller, ...$args);
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
        $agentType = AgentType::firstOrCreate(['name' => 'w6v-revert-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6V Revert Test Supplier']);

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

    public function test_revert_financials_for_task_reverses_via_engine_key_never_deletes(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $service = new TaskStatusService;
        $service->issue($task);

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $saleKey = 'invoice-detail:' . $invoiceDetail->id . ':sale';
        $saleTransaction = Transaction::withoutGlobalScopes()->where('idempotency_key', $saleKey)->first();
        $this->assertNotNull($saleTransaction);

        $this->invokePrivate('revertFinancialsForTask', $task->fresh());

        $saleTransaction->refresh();
        $this->assertSame('reversed', $saleTransaction->posting_status, 'ON path must reverse(), never delete, the posted row.');
        $this->assertNotNull(
            Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->first(),
            'A real reversal document must exist.'
        );
    }

    public function test_handle_amount_change_reverses_and_reposts_the_sale_with_the_new_amount(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0, 'total' => 350.0]);
        $service = new TaskStatusService;
        $service->issue($task);

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $saleKey = 'invoice-detail:' . $invoiceDetail->id . ':sale';
        $oldSale = Transaction::withoutGlobalScopes()->where('idempotency_key', $saleKey)->first();

        $task->total = 600.0;
        $task->save();

        $this->invokePrivate('handleAmountChange', $task->fresh());

        $oldSale->refresh();
        $this->assertSame('reversed', $oldSale->posting_status, 'The OLD amount document must be reversed, never mutated in place.');

        // repost()'s own key convention (PostingService::repost(), see its docblock): when the
        // replacement draft's idempotencyKey collides with the ORIGINAL's, it is suffixed
        // ':repost:{old_id}' rather than reused verbatim -- exactly the same convention
        // InvoiceController::updateTaskPriceOnPath() already relies on for this identical shape.
        $newSale = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', $saleKey . ':repost:' . $oldSale->id)
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($newSale, 'A fresh posted document must exist under the repost-suffixed idempotency key.');
        $this->assertEqualsWithDelta(600.0, (float) $newSale->amount, 0.001);

        $invoiceDetail->refresh();
        $this->assertEqualsWithDelta(600.0, (float) $invoiceDetail->task_price, 0.001, 'invoice_detail.task_price must be kept in sync before reposting.');
    }

    public function test_handle_client_change_reverses_and_reposts_under_the_new_client(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $service = new TaskStatusService;
        $service->issue($task);

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $saleKey = 'invoice-detail:' . $invoiceDetail->id . ':sale';
        $oldSale = Transaction::withoutGlobalScopes()->where('idempotency_key', $saleKey)->first();

        $newClient = Client::factory()->create(['agent_id' => $agent->id]);
        $prevClientName = $client->full_name;
        $task->client_id = $newClient->id;
        $task->client_name = $newClient->full_name;
        $task->save();

        $this->invokePrivate('handleClientChange', $task->fresh(), $prevClientName);

        $oldSale->refresh();
        $this->assertSame('reversed', $oldSale->posting_status);

        $newSale = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', $saleKey . ':repost:' . $oldSale->id)
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($newSale);
    }

    // ── P2.5.D fix (verify finding) ────────────────────────────────────────────────────────────────
    // Previous submission: reverseAndRepostSale() (shared by handleClientChange()/
    // handleAmountChange()) never resolved SaleDraftBuilder::resolveRecognitionTiming() -- it
    // silently applied ONLY the config default inside buildLines(), dropping any per-company
    // Setting override the moment a task's client name or total was edited. This test sets an
    // override that DIFFERS from 'tour's config default ('at_travel') and proves the override
    // survives a client-name edit's reverse+repost.

    public function test_handle_client_change_preserves_a_per_company_recognition_timing_override(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // 'tour' defaults to at_travel -- override to at_issue for this company, the OPPOSITE of
        // the config default, so a silent fall-through to the config default (the bug) is
        // observable rather than accidentally matching by coincidence.
        \App\Models\Setting::create([
            'company_id' => $company->id, 'key' => 'accounting.revenue_recognition.tour',
            'value' => 'at_issue', 'type' => 'string',
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'type' => 'tour', 'price' => 500.0, 'total' => 200.0,
        ]);
        $service = new TaskStatusService;
        $service->issue($task);

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $saleKey = 'invoice-detail:'.$invoiceDetail->id.':sale';
        $oldSale = Transaction::withoutGlobalScopes()->where('idempotency_key', $saleKey)->first();
        $this->assertNotNull($oldSale);

        $newClient = Client::factory()->create(['agent_id' => $agent->id]);
        $prevClientName = $client->full_name;
        $task->client_id = $newClient->id;
        $task->client_name = $newClient->full_name;
        $task->save();

        $this->invokePrivate('handleClientChange', $task->fresh(), $prevClientName);

        $newSale = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', $saleKey.':repost:'.$oldSale->id)
            ->where('posting_status', 'posted')
            ->first();
        $this->assertNotNull($newSale, 'A fresh posted document must exist under the repost-suffixed idempotency key.');

        $lines = \App\Models\JournalEntry::withoutGlobalScopes()->where('transaction_id', $newSale->id)->get();

        $resolver = app(\App\Services\Accounting\AccountResolver::class);
        $revenueAccountId = $resolver->resolve('SERVICE_REVENUE', $company->id, 'tour')->id;
        $deferredAccountId = $resolver->resolve('DEFERRED_REVENUE', $company->id)->id;

        $accountIdsHit = $lines->pluck('account_id')->all();
        $this->assertContains($revenueAccountId, $accountIdsHit, 'The per-company at_issue override must survive the client-change repost -- SERVICE_REVENUE must be hit.');
        $this->assertNotContains($deferredAccountId, $accountIdsHit, 'Must NOT silently fall back to the config default (at_travel) and post DEFERRED_REVENUE, dropping the company override.');
    }

    public function test_revert_financials_for_void_unwinds_the_full_void_document_set(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $original = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $service = new TaskStatusService;
        $service->issue($original);

        $voidResult = $service->void($original->fresh(), ['fee' => 15.0]);
        $this->assertNotNull($voidResult['crn']);
        $this->assertNotNull($voidResult['fee']);

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

        $crnReversal = $voidResult['crn']->transaction;
        $feeDoc = $voidResult['fee']->transaction;

        $this->invokePrivate('revertFinancialsForVoid', $voidRow);

        // Un-void: the void's own CRN reversal gets reversed AGAIN (a REV-of-REV), restoring the
        // original sale's balance.
        $this->assertNotNull(
            Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $crnReversal->id)->first(),
            'The void CRN reversal itself must be reversed (un-void).'
        );

        // The satellite fee document must also have been reversed.
        $feeDoc->refresh();
        $this->assertSame('reversed', $feeDoc->posting_status);
    }
}
