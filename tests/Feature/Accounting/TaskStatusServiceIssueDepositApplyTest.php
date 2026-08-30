<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * FIX ROUND (previous verify, CONFIRMED gap against a named verify criterion): w6-brief.md
 * "W6.S -- Hold/confirmed follow-up lifecycle" item 3 states verbatim "On issue() (W6.I), the
 * advance is auto-applied to the newly created invoice through the existing apply/allocation
 * engine (same mechanism W6.R uses to re-apply receipts on reissue)." Before this fix round,
 * {@see TaskStatusService::issue()} called only {@see \App\Http\Controllers\InvoiceController::
 * autoGenerateInvoice()} -- zero references anywhere to any apply/allocation mechanism -- so a
 * client with a deposit already sitting against a task's future invoice saw that invoice land
 * fully outstanding regardless. Covers the fix: {@see TaskStatusService::
 * applyHoldDepositToInvoice()}, called from the end of {@see TaskStatusService::issue()}.
 *
 * Fixture pattern mirrors {@see TaskStatusServiceIssueTest} exactly (same company/agent/client/
 * supplier builder, same enableEngine() helper) plus the real receipt-voucher HTTP flow
 * {@see TaskDepositW6STest} already uses to create a genuine `on hold`-task deposit (real
 * seeders, real PostingSeam-posted RV -- no fabricated InvoiceReceipt row).
 */
class TaskStatusServiceIssueDepositApplyTest extends AccountingTestCase
{
    private TaskStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TaskStatusService;
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Supplier, 5: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentType = AgentType::firstOrCreate(['name' => 'w6u-deposit-apply-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6U Deposit Apply Supplier']);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $agent, $client, $supplier, $admin];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /**
     * Real HTTP flow (create + approve), same as {@see TaskDepositW6STest}: posts Dr instrument /
     * Cr CLIENT_ADVANCE (2632), tagged `journal_entries.task_id`, `invoice_receipts.invoice_id`
     * left NULL (no invoice exists yet -- the task is still `on hold`).
     */
    private function postApprovedDeposit(Company $company, Branch $branch, Client $client, Task $task, User $admin, float $amount): InvoiceReceipt
    {
        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'credit',
            'client_id' => $client->id,
            'task_id' => $task->id,
            'amount' => $amount,
            'remarks_create' => 'Deposit for issue-time auto-apply test',
        ])->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('task_id', $task->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $invoiceReceipt->id))
            ->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);
        $this->assertNull($invoiceReceipt->invoice_id, 'Deposit must have no invoice yet -- task is still on hold.');

        return $invoiceReceipt;
    }

    public function test_partial_deposit_auto_applies_to_the_new_invoice_at_issue_without_flipping_paid(): void
    {
        [$company, $branch, $agent, $client, $supplier, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'on hold',
            'reference' => 'HOLD-DEPOSIT-PNR-'.uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ]);

        $this->postApprovedDeposit($company, $branch, $client, $task, $admin, 200.0);

        $task->status = 'confirmed';
        $task->save();
        $task->status = 'issued';
        $task->save();

        $result = $this->service->issue($task->fresh());

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $invoiceId = $result['invoice_id'];
        $this->assertNotNull($invoiceId);

        $invoiceReceipt = InvoiceReceipt::where('task_id', $task->id)->firstOrFail();
        $this->assertSame($invoiceId, $invoiceReceipt->invoice_id, 'Deposit must be re-pointed onto the newly issued invoice.');

        $clientAdvance = $this->accountByCode($company->id, '2632');
        $receivable = $this->accountByCode($company->id, '1351');

        $applyDebits = JournalEntry::where('task_id', $task->id)
            ->where('account_id', $clientAdvance->id)
            ->where('debit', '>', 0)
            ->get();
        $this->assertCount(1, $applyDebits, 'Exactly one deposit-apply debit line to 2632 must post.');
        $this->assertEqualsWithDelta(200.0, (float) $applyDebits->first()->debit, 0.001);

        $applyCredits = JournalEntry::where('task_id', $task->id)
            ->where('account_id', $receivable->id)
            ->where('credit', '>', 0)
            ->get();
        $this->assertGreaterThanOrEqual(1, $applyCredits->count(), 'A matching credit to Accounts Receivable must post.');
        $this->assertEqualsWithDelta(200.0, (float) $applyCredits->last()->credit, 0.001);

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->firstOrFail();
        $this->assertFalse((bool) $invoiceDetail->paid, 'A 200 deposit against a 500 sale must not flip paid true.');

        $invoice = Invoice::find($invoiceId);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertEqualsWithDelta(500.0, (float) $invoice->amount, 0.001, 'Invoice amount itself is unaffected by the deposit application.');

        // Idempotency: a second issue() call (retry) must never re-apply the same deposit.
        $secondResult = $this->service->issue($task->fresh());
        $this->assertTrue($secondResult['success'] ?? false);

        $this->assertSame(
            1,
            JournalEntry::where('task_id', $task->id)->where('account_id', $clientAdvance->id)->where('debit', '>', 0)->count(),
            'Second issue() call must not re-apply the deposit a second time.'
        );
    }

    public function test_deposit_that_fully_covers_the_sale_flips_invoice_detail_and_invoice_to_paid(): void
    {
        [$company, $branch, $agent, $client, $supplier, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'on hold',
            'reference' => 'HOLD-DEPOSIT-FULL-PNR-'.uniqid(),
            'price' => 300.0,
            'total' => 200.0,
        ]);

        $this->postApprovedDeposit($company, $branch, $client, $task, $admin, 300.0);

        $task->status = 'issued';
        $task->save();

        $result = $this->service->issue($task->fresh());
        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $invoiceId = $result['invoice_id'];

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->firstOrFail();
        $this->assertTrue((bool) $invoiceDetail->paid, 'A deposit that fully covers the sale must flip paid true.');

        $invoice = Invoice::find($invoiceId);
        $this->assertSame('paid', $invoice->status, 'A single-task invoice fully covered by its deposit must flip to paid.');

        $clientAdvance = $this->accountByCode($company->id, '2632');
        $applied = (float) JournalEntry::where('task_id', $task->id)
            ->where('account_id', $clientAdvance->id)
            ->where('debit', '>', 0)
            ->sum('debit');
        $this->assertEqualsWithDelta(300.0, $applied, 0.001);
    }

    public function test_a_task_with_no_deposit_is_a_no_op_and_leaves_invoice_unpaid(): void
    {
        [$company, $branch, $agent, $client, $supplier, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'NO-DEPOSIT-PNR-'.uniqid(),
            'price' => 400.0,
            'total' => 300.0,
        ]);

        $result = $this->service->issue($task);
        $this->assertTrue($result['success'] ?? false, json_encode($result));

        $clientAdvance = $this->accountByCode($company->id, '2632');
        $this->assertSame(0, JournalEntry::where('task_id', $task->id)->where('account_id', $clientAdvance->id)->count());

        $invoice = Invoice::find($result['invoice_id']);
        $this->assertSame('unpaid', $invoice->status);
    }
}
