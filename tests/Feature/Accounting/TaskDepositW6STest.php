<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
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
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * W6.S "Hold/confirmed follow-up lifecycle" item 3 fix round (w6-brief.md, owner addition
 * 2026-08-28; previous verify: "the receipt-posts-to-2632 half ... could have been built now").
 *
 * "A receipt taken against an on hold/confirmed task posts Cr 2632 client advance (never revenue)
 * via the existing W5 RV path" -- covers the deposit-posting half only. The auto-apply-on-issue()
 * half of this same item (previously deferred here, then found entirely unbuilt by a later
 * verify round even after issue() shipped) is now covered by
 * {@see TaskStatusServiceIssueDepositApplyTest} instead of this file -- see
 * {@see \App\Services\TaskStatusService::applyHoldDepositToInvoice()}'s own docblock. The actual
 * `refund_out` PV-draft creation on cancel remains deferred (see
 * TaskStatusService::cancel()'s own docblock).
 */
class TaskDepositW6STest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User, 5: Task} */
    private function makeFixtureWithHoldTask(string $status = 'on hold'): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'supplier_id' => $supplier->id,
            'status' => $status,
        ]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $agent, $client, $admin, $task];
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

    public function test_receipt_against_on_hold_task_posts_cr_2632_tagged_with_the_task(): void
    {
        [$company, $branch, $agent, $client, $admin, $task] = $this->makeFixtureWithHoldTask('on hold');
        $this->enableEngine($company);

        $cashInHand = $this->accountByCode($company->id, '1120');
        $clientAdvance = $this->accountByCode($company->id, '2632');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'credit',
            'client_id' => $client->id,
            'task_id' => $task->id,
            'amount' => 75,
            'remarks_create' => 'Deposit against hold booking',
        ])->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($invoiceReceipt);
        $this->assertSame($task->id, $invoiceReceipt->task_id);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $invoiceReceipt->id))
            ->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);

        $lines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $this->assertCount(2, $lines);

        $debit = $lines->firstWhere('account_id', $cashInHand->id);
        $advanceCredit = $lines->firstWhere('account_id', $clientAdvance->id);

        $this->assertNotNull($debit);
        $this->assertEqualsWithDelta(75.0, (float) $debit->debit, 0.001);

        $this->assertNotNull($advanceCredit);
        $this->assertEqualsWithDelta(75.0, (float) $advanceCredit->credit, 0.001);
        $this->assertSame($task->id, $advanceCredit->task_id);

        // TaskStatusService::depositHeld() reads this same posted amount back.
        $this->assertEqualsWithDelta(75.0, (new TaskStatusService)->depositHeld($task), 0.001);
    }

    public function test_receipt_against_confirmed_task_also_posts_cr_2632(): void
    {
        [$company, $branch, $agent, $client, $admin, $task] = $this->makeFixtureWithHoldTask('confirmed');
        $this->enableEngine($company);

        $clientAdvance = $this->accountByCode($company->id, '2632');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'credit',
            'client_id' => $client->id,
            'task_id' => $task->id,
            'amount' => 40,
            'remarks_create' => 'Deposit against confirmed booking',
        ])->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $invoiceReceipt->id));

        $invoiceReceipt->refresh();
        $advanceCredit = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)
            ->where('account_id', $clientAdvance->id)->first();

        $this->assertNotNull($advanceCredit);
        $this->assertEqualsWithDelta(40.0, (float) $advanceCredit->credit, 0.001);
    }

    public function test_deposit_rejected_for_a_task_that_is_already_issued(): void
    {
        [$company, $branch, $agent, $client, $admin, $task] = $this->makeFixtureWithHoldTask('issued');
        $this->enableEngine($company);

        $before = InvoiceReceipt::count();

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'credit',
            'client_id' => $client->id,
            'task_id' => $task->id,
            'amount' => 40,
            'remarks_create' => 'Should be rejected',
        ])->assertSessionHasErrors('task_id');

        $this->assertSame($before, InvoiceReceipt::count());
    }

    public function test_task_id_requires_credit_type(): void
    {
        [$company, $branch, $agent, $client, $admin, $task] = $this->makeFixtureWithHoldTask('on hold');
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'task_id' => $task->id,
            'amount' => 40,
        ])->assertSessionHasErrors('task_id');
    }
}
