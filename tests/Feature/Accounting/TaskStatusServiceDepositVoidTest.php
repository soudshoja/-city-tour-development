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
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
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
 * W6.U residual fix round 2 (w6u-verify-2.md finding 1, BLOCKING -- hold-deposit double
 * disposition; finding 2, DESIGN -- missing InvoicePartial row).
 *
 * Repro this file closes, verbatim from the verify finding: on-hold task, $500 sale / $350 cost,
 * $200 hold-deposit taken via the real receipt-voucher store+approve HTTP flow, confirmed then
 * issued (deposit auto-applies at {@see TaskStatusService::issue()} via {@see TaskStatusService::
 * applyHoldDepositToInvoice()}), then voided the same day under the default `credit`
 * `invoice_overpay_cancel_policy`. Before this fix: account 2632 (Client Advance) netted -200
 * (should be +200) and account 1351 (Accounts Receivable Control) netted -400 (should be 0) --
 * {@see TaskStatusService::voidDisposition()} (via {@see TaskStatusService::paidAmountForTask()}
 * -> {@see TaskStatusService::depositHeld()}) disposed of the SAME already-consumed $200 a second
 * time, because nothing marked the deposit's `invoice_receipts` rows as consumed.
 *
 * Covers (per w6u-fix-2.md's own worked example):
 *  - test 1: full worked example, no fee -- final nets 2632=+200, AR=0, 4134=0, invoicePartials
 *    sum=200 (finding 2), receipt `applied_at` set;
 *  - test 2: same with a $30 void fee -- final nets 2632=+170, AR=0, 4134=30;
 *  - test 3: void() called twice -- no second disposition JV, no new JournalEntry rows at all on
 *    the second call;
 *  - test 4: a never-issued (still `on hold`) task's `cancel()` -- {@see TaskStatusService::
 *    depositHeld()} still reads 200 (unconsumed -- W6.S parity), no ledger rows at all beyond the
 *    original deposit receipt itself.
 *
 * Account balances are read via real `JournalEntry.debit`/`.credit` sums (Dr-Cr for the
 * asset-normal AR control account, Cr-Dr for the liability/income-normal 2632/4134 accounts) --
 * never `journal_entries.balance`/`accounts.actual_balance` (feedback_accounting_boundary --
 * forbidden reads). {@see \Tests\Support\AccountingTestCase}'s own tearDown hook independently
 * re-verifies the WHOLE trial balance still zeroes out for every company this file tracks, so a
 * mis-signed line here would fail that invariant even if a test's own narrower assertion did not.
 */
class TaskStatusServiceDepositVoidTest extends AccountingTestCase
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

        $agentType = AgentType::firstOrCreate(['name' => 'w6u2-deposit-void-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6U2 Deposit Void Supplier']);

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

    /** Dr-Cr net -- for an asset-normal account (e.g. 1351 Accounts Receivable Control). */
    private function netDebit(int $companyId, string $code): float
    {
        $account = $this->accountByCode($companyId, $code);
        $debit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('account_id', $account->id)->sum('debit');
        $credit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('account_id', $account->id)->sum('credit');

        return round($debit - $credit, 3);
    }

    /** Cr-Dr net -- for a liability/income-normal account (e.g. 2632 Client Advance, 4134 Void Fee Income). */
    private function netCredit(int $companyId, string $code): float
    {
        return round(-1 * $this->netDebit($companyId, $code), 3);
    }

    /** Real HTTP flow (create + approve), same pattern as TaskStatusServiceIssueDepositApplyTest. */
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
            'remarks_create' => 'Deposit for deposit+void interaction test',
        ])->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('task_id', $task->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $invoiceReceipt->id))
            ->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);

        return $invoiceReceipt;
    }

    /** Builds an on-hold task, takes a real $200 deposit, confirms, then issues it through the real engine path. */
    private function makeIssuedTaskWithAppliedDeposit(
        Company $company,
        Branch $branch,
        Agent $agent,
        Client $client,
        Supplier $supplier,
        User $admin,
        float $depositAmount = 200.0
    ): array {
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'on hold',
            'reference' => 'W6U2-PNR-'.uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ]);

        $this->postApprovedDeposit($company, $branch, $client, $task, $admin, $depositAmount);

        $task->status = 'confirmed';
        $task->save();
        $task->status = 'issued';
        $task->save();

        $result = $this->service->issue($task->fresh());
        $this->assertTrue($result['success'] ?? false, json_encode($result));

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->firstOrFail();
        $invoice = Invoice::find($invoiceDetail->invoice_id);

        return [$task->fresh(), $invoice, $invoiceDetail];
    }

    // ---------------------------------------------------------------------------------------
    // (i) Full worked example, no fee.
    // ---------------------------------------------------------------------------------------

    public function test_deposit_applied_at_issue_then_voided_same_day_disposes_the_paid_amount_exactly_once(): void
    {
        [$company, $branch, $agent, $client, $supplier, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        [$task, $invoice, $invoiceDetail] = $this->makeIssuedTaskWithAppliedDeposit(
            $company, $branch, $agent, $client, $supplier, $admin, 200.0
        );

        // Post-issue state (matches TaskStatusServiceIssueDepositApplyTest's own assertions).
        $this->assertEqualsWithDelta(0.0, $this->netCredit($company->id, '2632'), 0.001, '2632 net 0 after issue.');
        $this->assertEqualsWithDelta(300.0, $this->netDebit($company->id, '1351'), 0.001, 'AR net 300 after issue.');

        $invoiceReceipt = InvoiceReceipt::where('task_id', $task->id)->firstOrFail();
        $this->assertNotNull($invoiceReceipt->applied_at, 'The deposit receipt must be stamped applied_at once consumed.');
        $this->assertNotNull($invoiceReceipt->applied_transaction_id);
        $this->assertNotNull($invoiceReceipt->invoice_partial_id);

        // Finding 2: the invoice's own invoicePartials() relation must show the applied deposit.
        $invoice->refresh();
        $this->assertEqualsWithDelta(200.0, (float) $invoice->invoicePartials()->sum('amount'), 0.001);

        $partial = InvoicePartial::findOrFail($invoiceReceipt->invoice_partial_id);
        $this->assertSame($invoice->id, $partial->invoice_id);
        $this->assertEqualsWithDelta(200.0, (float) $partial->amount, 0.001);

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':sale')
            ->firstOrFail();

        $result = $this->service->void($task->fresh());

        $this->assertFalse($result['idempotent']);
        $this->assertNotNull($result['crn'], 'The CRN reversal of the original sale must post.');
        $this->assertNotNull($result['disposition'], 'A disposition must post for the consumed deposit.');
        $this->assertNull($result['fee']);

        // CRN reverses the ENTIRE original sale (500 AR / 350 cost / 150 margin).
        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->first();
        $this->assertNotNull($reversal, 'The sale document must have been reversed.');
        $reversalLines = JournalEntry::where('transaction_id', $reversal->id)->get();
        $ar = $this->accountByCode($company->id, '1351');
        $arReversalLine = $reversalLines->firstWhere('account_id', $ar->id);
        $this->assertNotNull($arReversalLine);
        $this->assertEqualsWithDelta(500.0, (float) $arReversalLine->credit, 0.001, 'CRN 500 on the AR line.');

        // The reversal's debit side (cost 350 + margin 150, split across the payable/revenue
        // lines SaleDraftBuilder's own NET-basis shape produces) must sum back to the same 500
        // the AR credit line above carries -- the reversal is a balanced flip of the whole
        // original document, not a partial one.
        $totalDebit = (float) $reversalLines->sum('debit');
        $this->assertEqualsWithDelta(500.0, $totalDebit, 0.001, 'Reversal debits (cost 350 + margin 150) sum to the original sell.');

        // Disposition amount itself = the consumed deposit (200), not netted (no fee this time).
        $dispositionAmount = (float) $result['disposition']->lines[0]->amount;
        $this->assertEqualsWithDelta(200.0, $dispositionAmount, 0.001);

        // Final nets -- the exact worked example.
        $this->assertEqualsWithDelta(200.0, $this->netCredit($company->id, '2632'), 0.001, 'Final 2632 = +200 (client owed credit).');
        $this->assertEqualsWithDelta(0.0, $this->netDebit($company->id, '1351'), 0.001, 'Final AR = 0.');
        $this->assertEqualsWithDelta(0.0, $this->netCredit($company->id, '4134'), 0.001, 'No fee -> 4134 untouched.');
    }

    // ---------------------------------------------------------------------------------------
    // (ii) Same, with a $30 void fee.
    // ---------------------------------------------------------------------------------------

    public function test_deposit_applied_at_issue_then_voided_with_fee_nets_the_fee_out_of_the_disposition(): void
    {
        [$company, $branch, $agent, $client, $supplier, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        [$task] = $this->makeIssuedTaskWithAppliedDeposit($company, $branch, $agent, $client, $supplier, $admin, 200.0);

        $result = $this->service->void($task->fresh(), ['fee' => 30.0]);

        $this->assertNotNull($result['fee']);
        $this->assertNotNull($result['disposition']);

        $dispositionAmount = (float) $result['disposition']->lines[0]->amount;
        $this->assertEqualsWithDelta(170.0, $dispositionAmount, 0.001, '200 deposit - 30 fee = 170.');

        $this->assertEqualsWithDelta(170.0, $this->netCredit($company->id, '2632'), 0.001, 'Final 2632 = +170.');
        $this->assertEqualsWithDelta(0.0, $this->netDebit($company->id, '1351'), 0.001, 'Final AR = 0.');
        $this->assertEqualsWithDelta(30.0, $this->netCredit($company->id, '4134'), 0.001, 'Final 4134 = 30 (the void fee income).');
    }

    // ---------------------------------------------------------------------------------------
    // (iii) void() twice -- no second disposition.
    // ---------------------------------------------------------------------------------------

    public function test_voiding_a_task_with_an_applied_deposit_twice_posts_no_second_disposition(): void
    {
        [$company, $branch, $agent, $client, $supplier, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        [$task] = $this->makeIssuedTaskWithAppliedDeposit($company, $branch, $agent, $client, $supplier, $admin, 200.0);

        $first = $this->service->void($task->fresh());
        $this->assertFalse($first['idempotent']);
        $this->assertNotNull($first['disposition']);

        $journalCountAfterFirst = JournalEntry::withoutGlobalScopes()->count();
        $transactionCountAfterFirst = Transaction::withoutGlobalScopes()->count();

        $second = $this->service->void($task->fresh());

        $this->assertTrue($second['idempotent']);
        $this->assertNull($second['disposition']);
        $this->assertSame($journalCountAfterFirst, JournalEntry::withoutGlobalScopes()->count(), 'A second void() call must post no new journal lines at all.');
        $this->assertSame($transactionCountAfterFirst, Transaction::withoutGlobalScopes()->count(), 'A second void() call must post no new documents at all.');

        // Final nets are unaffected by the redundant call.
        $this->assertEqualsWithDelta(200.0, $this->netCredit($company->id, '2632'), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->netDebit($company->id, '1351'), 0.001);
    }

    // ---------------------------------------------------------------------------------------
    // (iv) Never-issued on-hold task -- cancel() -- W6.S parity, depositHeld() unaffected.
    // ---------------------------------------------------------------------------------------

    public function test_cancel_on_a_never_issued_task_leaves_the_deposit_unconsumed_and_posts_no_ledger_rows(): void
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
            'reference' => 'W6U2-NEVER-ISSUED-PNR-'.uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ]);

        $this->postApprovedDeposit($company, $branch, $client, $task, $admin, 200.0);

        $depositBeforeCancel = $this->service->depositHeld($task);
        $this->assertEqualsWithDelta(200.0, $depositBeforeCancel, 0.001, 'depositHeld() must read the unconsumed 200 before cancel().');

        $journalCountBefore = JournalEntry::withoutGlobalScopes()->count();
        $transactionCountBefore = Transaction::withoutGlobalScopes()->count();

        $this->service->cancel($task->fresh());

        $this->assertSame('cancelled', $task->fresh()->status);

        // No new ledger rows from cancel() itself (W6.S parity -- cancel() never posts).
        $this->assertSame($journalCountBefore, JournalEntry::withoutGlobalScopes()->count());
        $this->assertSame($transactionCountBefore, Transaction::withoutGlobalScopes()->count());

        // The deposit itself is still there, still unconsumed (applied_at never set for a task
        // that was never issued -- applyHoldDepositToInvoice() only ever runs from issue()).
        $this->assertEqualsWithDelta(200.0, $this->service->depositHeld($task->fresh()), 0.001);

        $invoiceReceipt = InvoiceReceipt::where('task_id', $task->id)->firstOrFail();
        $this->assertNull($invoiceReceipt->applied_at);
        $this->assertNull($invoiceReceipt->invoice_partial_id);

        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'cancel_disposition_credit_retained',
        ]);
    }
}
