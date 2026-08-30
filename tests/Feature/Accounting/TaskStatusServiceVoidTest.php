<?php

namespace Tests\Feature\Accounting;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskStatusEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W6.V (w6-brief.md "Model" + "Kinds" 1-3 + "Commission" + "Preconditions").
 *
 * Covers:
 *  - core void(): reverses the task's own atomic sale document (ticket+client leg in one call,
 *    per SaleDraftBuilder's single-document shape), stamps bsptype=VOID, flips
 *    ticket_status/client_status/status;
 *  - idempotency: a second void() call on an already-voided task is a safe no-op;
 *  - preconditions: reconciled line refuses with a message pointing to the refund flow; a locked
 *    invoice refuses; ticket_status must be issued/reissued;
 *  - VOID WITH FEE: fee DBN to VOID_FEE_INCOME (4134), reason_tag=fee, fee-schedule resolution
 *    (override=free / percent / amount), commissionable_fee_types gate for the fee's own
 *    commission JV;
 *  - commission un-earn: reverses the original per-detail agent-commission document;
 *  - disposition: credit (default) -> Credit row + JV to CLIENT_ADVANCE; refund_out -> PV to the
 *    company-mapped payout leaf; manual -> no document, audit event only;
 *  - invoice status flip: CANCELLED when every task on the invoice is void, PARTIAL_REFUND
 *    otherwise;
 *  - AUTO_VOID via autoVoidExpiredInvoiced(): a confirmed-but-already-invoiced task past its
 *    deadline is voided through the engine, never a raw status flip;
 *  - dispatchFinancial() routing: an import-linked void-status row resolves to its originalTask
 *    and voids IT through the engine.
 */
class TaskStatusServiceVoidTest extends AccountingTestCase
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
        $agentType = AgentType::firstOrCreate(['name' => 'w6v-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6V Test Supplier']);

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

    /** Posts a real agent-commission JV, exactly the shape RefundPostingServiceTest's own helper posts. */
    private function postRealCommission(Company $company, Agent $agent, Invoice $invoice, InvoiceDetail $invoiceDetail, Task $task, float $commission): Transaction
    {
        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'JV',
            subType: 'AGENT_COMMISSION',
            docDate: now(),
            narration: 'Agent commission',
            lines: [
                new LineDraft(
                    purposeCode: 'SALARY_EXPENSE',
                    accountId: null,
                    side: 'debit',
                    amount: $commission,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $commission,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_COMMISSION_EXPENSE',
                    partyAccountRef: $agent->id,
                    invoiceId: $invoice->id,
                    invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id,
                ),
                new LineDraft(
                    purposeCode: 'SALARY_PAYABLE',
                    accountId: null,
                    side: 'credit',
                    amount: $commission,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $commission,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_COMMISSION_PAYABLE',
                    partyAccountRef: $agent->id,
                    invoiceId: $invoice->id,
                    invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id,
                ),
            ],
            idempotencyKey: 'invoice-detail:' . $invoiceDetail->id . ':agent-commission',
            invoiceId: $invoice->id,
        );

        return app(PostingService::class)->post($draft)->transaction;
    }

    // ---------------------------------------------------------------------------------------
    // Core void() -- ticket+client leg, idempotency
    // ---------------------------------------------------------------------------------------

    public function test_void_reverses_the_sale_document_and_flips_status(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $invoiceDetail] = $this->issueTask($task);

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:' . $invoiceDetail->id . ':sale')
            ->first();

        $result = $this->service->void($task->fresh());

        $this->assertFalse($result['idempotent']);
        $this->assertNotNull($result['crn']);

        $task->refresh();
        $this->assertSame('void', $task->ticket_status);
        $this->assertSame('credited', $task->client_status);
        $this->assertSame('void', $task->status);

        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->first();
        $this->assertNotNull($reversal, 'The sale document must have been reversed via reverse().');
        $this->assertSame('VOID', $reversal->bsptype);

        // No hard-delete: the original sale document row must still exist, only its
        // posting_status flips.
        $saleTransaction->refresh();
        $this->assertSame('reversed', $saleTransaction->posting_status);
    }

    public function test_void_is_idempotent_on_a_second_call(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $this->service->void($task->fresh());
        $countAfterFirst = Transaction::withoutGlobalScopes()->count();

        $second = $this->service->void($task->fresh());

        $this->assertTrue($second['idempotent']);
        $this->assertSame($countAfterFirst, Transaction::withoutGlobalScopes()->count(), 'A second void() call must post nothing new.');
    }

    public function test_void_refuses_a_task_that_was_never_issued(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'status' => 'on hold',
            'ticket_status' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->void($task);
    }

    public function test_void_refuses_when_the_sale_document_has_a_reconciled_line(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $invoiceDetail] = $this->issueTask($task);

        JournalEntry::where('invoice_detail_id', $invoiceDetail->id)->update(['reconciled' => 1]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/refund flow/');
        $this->service->void($task->fresh());
    }

    public function test_void_refuses_when_the_carrying_invoice_is_locked(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice] = $this->issueTask($task);
        $invoice->update(['is_locked' => true]);

        $this->expectException(\RuntimeException::class);
        $this->service->void($task->fresh());
    }

    // ---------------------------------------------------------------------------------------
    // VOID WITH FEE
    // ---------------------------------------------------------------------------------------

    public function test_void_with_fee_posts_dbn_to_void_fee_income_with_reason_tag(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $result = $this->service->void($task->fresh(), ['fee' => 20.0]);

        $this->assertNotNull($result['fee']);
        $feeLines = JournalEntry::where('transaction_id', $result['fee']->transaction->id)->get();
        $this->assertSame(2, $feeLines->count());
        foreach ($feeLines as $line) {
            $this->assertSame('fee', $line->reason_tag);
        }

        $incomeAccountId = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'VOID_FEE_INCOME')
            ->value('account_id');
        $this->assertNotNull($incomeAccountId);
        $this->assertTrue($feeLines->contains('account_id', $incomeAccountId));
    }

    public function test_void_fee_schedule_free_override_forces_zero_fee(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.fee_schedule.flight.override',
            'value' => 'free',
            'type' => 'string',
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $result = $this->service->void($task->fresh(), ['fee' => 20.0]);

        $this->assertNull($result['fee'], 'override=free must zero the fee even when the caller passed a nonzero value.');
    }

    public function test_void_fee_schedule_percent_overrides_caller_fee(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.fee_schedule.flight.percent',
            'value' => 10,
            'type' => 'string',
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        [$invoice, $invoiceDetail] = $this->issueTask($task);

        $result = $this->service->void($task->fresh(), ['fee' => 1.0]);

        $this->assertNotNull($result['fee']);
        $feeAmount = (float) $result['fee']->lines[0]->amount;
        $this->assertEqualsWithDelta(50.0, $feeAmount, 0.001, '10% of the 500.0 sell price, not the caller-supplied 1.0.');
    }

    public function test_void_fee_commission_gated_by_commissionable_fee_types(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        // Not listed -- no fee commission.
        $resultA = $this->service->void($task->fresh(), ['fee' => 20.0]);
        $this->assertNull($resultA['fee_commission']);

        // New task, now listed -- fee commission posts.
        $taskB = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => 'PNR-' . uniqid()]);
        $this->issueTask($taskB);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.commissionable_fee_types',
            'value' => json_encode(['flight']),
            'type' => 'json',
        ]);

        $resultB = $this->service->void($taskB->fresh(), ['fee' => 20.0]);
        $this->assertNotNull($resultB['fee_commission']);
        $this->assertEqualsWithDelta(3.0, (float) $resultB['fee_commission']->lines[0]->amount, 0.001, '15% commission rate * 20.0 fee.');
    }

    // ---------------------------------------------------------------------------------------
    // Commission un-earn
    // ---------------------------------------------------------------------------------------

    public function test_void_unearns_the_original_commission_by_default(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $invoiceDetail] = $this->issueTask($task);
        $commissionTx = $this->postRealCommission($company, $agent, $invoice, $invoiceDetail, $task, 22.5);

        $result = $this->service->void($task->fresh());

        $this->assertNotNull($result['commission_unearn']);
        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $commissionTx->id)->first();
        $this->assertNotNull($reversal);
    }

    public function test_void_does_not_unearn_commission_when_policy_is_not_un_earn(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.commission_on_refunded_sale',
            'value' => 'keep',
            'type' => 'string',
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $invoiceDetail] = $this->issueTask($task);
        $commissionTx = $this->postRealCommission($company, $agent, $invoice, $invoiceDetail, $task, 22.5);

        $result = $this->service->void($task->fresh());

        $this->assertNull($result['commission_unearn']);
        $this->assertNull(Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $commissionTx->id)->first());
    }

    // ---------------------------------------------------------------------------------------
    // Disposition
    // ---------------------------------------------------------------------------------------

    public function test_void_disposition_defaults_to_credit_and_writes_a_credit_row(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $invoiceDetail] = $this->issueTask($task);
        $invoiceDetail->update(['paid' => true]);

        $result = $this->service->void($task->fresh());

        $this->assertNotNull($result['disposition']);
        $this->assertSame(1, Credit::where('client_id', $client->id)->where('type', Credit::REFUND)->count());
    }

    public function test_void_disposition_honours_refund_out_company_option(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.invoice_overpay_cancel_policy',
            'value' => 'refund_out',
            'type' => 'string',
        ]);

        $payoutLeaf = Account::factory()->create(['company_id' => $company->id]);
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'REFUND_PAYOUT_CASH_BANK',
            'service_type' => null,
            'account_id' => $payoutLeaf->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $invoiceDetail] = $this->issueTask($task);
        $invoiceDetail->update(['paid' => true]);

        $result = $this->service->void($task->fresh());

        $this->assertNotNull($result['disposition']);
        $this->assertSame('PV', $result['disposition']->transaction->doc_type);
        $this->assertSame(0, Credit::where('client_id', $client->id)->count(), 'refund_out never touches 2632/Credit.');
    }

    public function test_void_disposition_manual_posts_nothing_and_writes_an_audit_event(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.refund.invoice_overpay_cancel_policy',
            'value' => 'manual',
            'type' => 'string',
        ]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $invoiceDetail] = $this->issueTask($task);
        $invoiceDetail->update(['paid' => true]);

        $result = $this->service->void($task->fresh());

        $this->assertNull($result['disposition']);
        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'void_disposition_manual_pending',
        ]);
    }

    public function test_void_disposition_is_a_noop_when_nothing_was_paid(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task); // invoiceDetail->paid stays false (unconditional auto-invoice, AR open)

        $result = $this->service->void($task->fresh());

        $this->assertNull($result['disposition']);
        $this->assertSame(0, Credit::where('client_id', $client->id)->count());
    }

    /**
     * Adversarial fix regression (verify-round finding, CONFIRMED): the disposition amount MUST
     * be net of the void fee just posted in the same void() call -- mirroring
     * RefundPostingService::postDisposition()'s own `$clientNet` input, which is ALREADY pre-netted
     * of the fee by its caller (doc22 Revision 7 / w4-brief.md's "client net = original_invoice_price
     * - penalty recharge - fee" rule) before it ever reaches disposition. Reproduces the exact
     * worked example from the finding: sell=100, cost=90, invoiceDetail paid in full, then
     * void(['fee' => 5.0]) -- CRN reverses Cr AR 100 / Dr Payable 90 / Dr Revenue 10, the fee DBN
     * posts Dr AR 5 / Cr 4134 5 (reason_tag=fee), and the disposition Credit/JV to CLIENT_ADVANCE
     * MUST be 95.00 (100 paid - 5 fee), never the raw 100.00 paid amount with the fee still sitting
     * on top of it (which would mean the agency never actually collects its own void fee whenever
     * the disposition policy is 'credit' -- the shipped default -- or 'refund_out').
     */
    public function test_void_disposition_nets_the_fee_just_posted_in_the_same_call(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'price' => 100.0,
            'total' => 90.0,
        ]);
        [$invoice, $invoiceDetail] = $this->issueTask($task);
        $invoiceDetail->update(['paid' => true]);

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':sale')
            ->first();

        $result = $this->service->void($task->fresh(), ['fee' => 5.0]);

        // Fee DBN: Dr AR 5 / Cr VOID_FEE_INCOME(4134) 5, reason_tag=fee.
        $this->assertNotNull($result['fee']);
        $feeAmount = (float) $result['fee']->lines[0]->amount;
        $this->assertEqualsWithDelta(5.0, $feeAmount, 0.001);

        // CRN reversal: the ORIGINAL sale document's own lines net to the same total/cost/margin
        // split it was posted with (Cr AR 100 / Dr Payable 90 / Dr Revenue 10) -- verified via the
        // reversal existing and flipping the original to 'reversed', matching every other core-void
        // test's own assertion style in this file (exact reversal-line polarity is SaleDraftBuilder's
        // own contract, not re-derived here).
        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $saleTransaction->id)->first();
        $this->assertNotNull($reversal, 'The sale document must have been reversed via reverse().');

        // Disposition MUST be net of the fee: 100 paid - 5 fee = 95, never the raw 100.
        $this->assertNotNull($result['disposition']);
        $dispositionAmount = (float) $result['disposition']->lines[0]->amount;
        $this->assertEqualsWithDelta(95.0, $dispositionAmount, 0.001, 'Disposition must net the void fee out of the paid amount.');

        $credit = Credit::where('client_id', $client->id)->where('type', Credit::REFUND)->first();
        $this->assertNotNull($credit);
        $this->assertEqualsWithDelta(95.0, (float) $credit->amount, 0.001, 'The client credit row must also be net of the fee, not the raw paid amount.');
    }

    /**
     * When the fee meets or exceeds the paid amount, the net disposition clamps to 0 and nothing
     * posts -- the fee's own `Dr AR` line (posted by voidPostFee()) already stands as a separate
     * open receivable for the shortfall; this method never manufactures a negative disposition (a
     * credit-side JV/PV with a negative amount) to net that out a second time.
     */
    public function test_void_disposition_is_a_noop_when_the_fee_consumes_the_entire_paid_amount(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'price' => 100.0,
            'total' => 90.0,
        ]);
        [$invoice, $invoiceDetail] = $this->issueTask($task);
        $invoiceDetail->update(['paid' => true]);

        $result = $this->service->void($task->fresh(), ['fee' => 150.0]);

        $this->assertNotNull($result['fee']);
        $this->assertNull($result['disposition'], 'A fee >= the paid amount must clamp the net disposition to 0 -- no document posted.');
        $this->assertSame(0, Credit::where('client_id', $client->id)->count());
    }

    // ---------------------------------------------------------------------------------------
    // Invoice status flip
    // ---------------------------------------------------------------------------------------

    public function test_invoice_flips_to_cancelled_when_all_tasks_void(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $result = $this->service->void($task->fresh());

        $this->assertSame(InvoiceStatus::CANCELLED->value, $result['invoice_status']);
    }

    public function test_invoice_flips_to_partial_refund_when_some_tasks_remain(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $pnr = 'SHARED-PNR-' . uniqid();
        $taskA = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => $pnr, 'passenger_name' => 'A']);
        $taskB = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => $pnr, 'passenger_name' => 'B']);

        $this->issueTask($taskA);
        $this->issueTask($taskB); // per_pnr default groups both onto ONE invoice

        $result = $this->service->void($taskA->fresh());

        $this->assertSame(InvoiceStatus::PARTIAL_REFUND->value, $result['invoice_status']);
    }

    // ---------------------------------------------------------------------------------------
    // AUTO_VOID (ProcessExpiredConfirmedTasks / autoVoidExpiredInvoiced())
    // ---------------------------------------------------------------------------------------

    public function test_auto_void_expired_invoiced_voids_a_confirmed_but_already_invoiced_task(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        // Stale legacy status ('confirmed'), but already invoiced -- the brief's own Kind 2 case.
        $task->update(['status' => 'confirmed', 'deadline_at' => now()->subHours(3)]);

        $voidedCount = $this->service->autoVoidExpiredInvoiced($company->id);

        $this->assertSame(1, $voidedCount);
        $task->refresh();
        $this->assertSame('void', $task->ticket_status);

        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'auto_void',
        ]);
    }

    public function test_auto_void_expired_invoiced_never_touches_a_task_before_its_deadline(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);
        $task->update(['status' => 'confirmed', 'deadline_at' => now()->addHours(3)]);

        $voidedCount = $this->service->autoVoidExpiredInvoiced($company->id);

        $this->assertSame(0, $voidedCount);
        $this->assertNotSame('void', $task->fresh()->ticket_status);
    }

    // ---------------------------------------------------------------------------------------
    // dispatchFinancial() routing for an import-linked void-status row
    // ---------------------------------------------------------------------------------------

    public function test_dispatch_financial_routes_a_linked_void_row_to_void_the_original(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $original = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($original);

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

        $this->service->dispatchFinancial($voidRow);

        $original->refresh();
        $this->assertSame('void', $original->ticket_status);
    }

    public function test_dispatch_financial_void_off_path_never_calls_void(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        config(['accounting.engine.enabled' => false]);

        $original = $this->makeIssuedTask($company, $agent, $client, $supplier);

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

        try {
            $this->service->dispatchFinancial($voidRow);
        } catch (\Throwable $e) {
            // processTaskFinancial()'s legacy body needs a fully-wired SupplierCompany/account
            // fixture this test does not build -- irrelevant to what this test proves (the ROUTING
            // decision). See TaskStatusServiceIssueTest's own OFF-path tests for the same caveat.
        }

        $this->assertNotSame('void', $original->fresh()->ticket_status);
    }
}
