<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W6.R (w6-brief.md "Kinds" 4: REISSUE / EXCHANGE).
 *
 * Covers:
 *  - core reissue(): reverses the OLD task's own atomic sale document, posts the NEW task's
 *    sale+cost as brand-new invoice lines on the SAME invoice (sub_type=REISSUE), stamps
 *    sub_type=REISSUE_REVERSAL on the reversal, flips ticket_status/client_status on both tasks;
 *  - idempotency: a second reissue() call on an already-reissued pair is a safe no-op;
 *  - preconditions: old ticket_status must be issued; old must have an invoice_detail; a locked
 *    invoice refuses; a reconciled line refuses (pointing at the refund flow);
 *  - fare difference: computed (never a third posted document) as dbn when the new sell exceeds
 *    the old, crn when it's less;
 *  - REISSUE FEE: DBN to REISSUE_FEE_INCOME (4135), reason_tag=fee, shared fee-schedule
 *    resolution, commissionable_fee_types gate on the fee's own commission;
 *  - commission: un-earns the OLD sale's commission, posts a NEW commission JV on the reissued
 *    sale's own margin (found later by the standard invoice-detail:{id}:agent-commission key);
 *  - existing receipt allocations are re-pointed from the old task onto the new one, with any
 *    remainder going through the standard overpay disposition;
 *  - dispatchFinancial() routing: an import-time reissued-status task with a resolvable,
 *    already-invoiced original routes to reissue(); one without falls back to issue().
 */
class TaskStatusServiceReissueTest extends AccountingTestCase
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
        $agentType = AgentType::firstOrCreate(['name' => 'w6r-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6R Test Supplier']);

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

    /** A brand-new reissue-target Task row -- same shape store()'s own fare-delta heuristic creates. */
    private function makeReissueTargetTask(Task $oldTask, array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'company_id' => $oldTask->company_id,
            'agent_id' => $oldTask->agent_id,
            'client_id' => $oldTask->client_id,
            'supplier_id' => $oldTask->supplier_id,
            'type' => $oldTask->type,
            'status' => 'reissued',
            'reference' => $oldTask->reference,
            'passenger_name' => $oldTask->passenger_name,
            'original_task_id' => $oldTask->id,
            'price' => (float) $oldTask->price,
            'total' => (float) $oldTask->total,
        ], $overrides));
    }

    /** Posts a real agent-commission JV, exactly the shape TaskStatusServiceVoidTest's own helper posts. */
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
    // Core reissue() -- reversal + new sale on the same invoice, idempotency
    // ---------------------------------------------------------------------------------------

    public function test_reissue_reverses_old_sale_and_posts_new_sale_on_same_invoice(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $oldInvoiceDetail] = $this->issueTask($oldTask);

        $oldSaleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:' . $oldInvoiceDetail->id . ':sale')
            ->first();

        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 600.0, 'total' => 400.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        $this->assertFalse($result['idempotent']);
        $this->assertNotNull($result['reversal']);
        $this->assertNotNull($result['new_sale']);

        // Reversal of the OLD sale, sub_type stamped REISSUE_REVERSAL.
        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $oldSaleTransaction->id)->first();
        $this->assertNotNull($reversal, 'The old sale document must have been reversed via reverse().');
        $this->assertSame('REISSUE_REVERSAL', $reversal->sub_type);
        $oldSaleTransaction->refresh();
        $this->assertSame('reversed', $oldSaleTransaction->posting_status);

        // New sale on the SAME invoice.
        $newInvoiceDetail = InvoiceDetail::where('task_id', $newTask->id)->first();
        $this->assertNotNull($newInvoiceDetail);
        $this->assertSame($invoice->id, $newInvoiceDetail->invoice_id, 'The new line must land on the SAME invoice, not a new one.');
        $this->assertSame('REISSUE', $result['new_sale']->transaction->sub_type);
        $this->assertEqualsWithDelta(600.0, (float) $newInvoiceDetail->task_price, 0.001);

        // Old invoice_detail row itself is left untouched (never deleted, never overwritten).
        $this->assertNotNull(InvoiceDetail::find($oldInvoiceDetail->id));

        // Status flips.
        $oldTask->refresh();
        $newTask->refresh();
        $this->assertSame('reissued', $oldTask->ticket_status);
        $this->assertSame('rebilled', $oldTask->client_status);
        $this->assertSame('issued', $newTask->ticket_status);
        $this->assertSame('open', $newTask->client_status);

        // Legacy `status` column untouched on the old task -- see reissue()'s own docblock.
        $this->assertSame('issued', $oldTask->status);
    }

    // ── P2.5.D fix (verify finding) ────────────────────────────────────────────────────────────────
    // Previous submission: reissuePostNewSale() (the private helper that posts the reissued
    // task's brand-new sale document) never resolved SaleDraftBuilder::resolveRecognitionTiming()
    // -- it silently applied ONLY the config default inside buildLines(), dropping any per-company
    // Setting override on a reissued ticket. This test sets an override that DIFFERS from 'tour's
    // config default ('at_travel') and proves the override survives a reissue's new-sale posting.

    public function test_reissue_new_sale_preserves_a_per_company_recognition_timing_override(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // 'tour' defaults to at_travel -- override to at_issue for this company, the OPPOSITE of
        // the config default, so a silent fall-through to the config default (the bug) is
        // observable rather than accidentally matching by coincidence.
        Setting::create([
            'company_id' => $company->id, 'key' => 'accounting.revenue_recognition.tour',
            'value' => 'at_issue', 'type' => 'string',
        ]);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'type' => 'tour', 'price' => 500.0, 'total' => 200.0,
        ]);
        $this->issueTask($oldTask);

        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 600.0, 'total' => 250.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh());
        $this->assertNotNull($result['new_sale']);

        $newInvoiceDetail = InvoiceDetail::where('task_id', $newTask->id)->first();
        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $result['new_sale']->transaction->id)->get();

        $resolver = app(\App\Services\Accounting\AccountResolver::class);
        $revenueAccountId = $resolver->resolve('SERVICE_REVENUE', $company->id, 'tour')->id;
        $deferredAccountId = $resolver->resolve('DEFERRED_REVENUE', $company->id)->id;

        $accountIdsHit = $lines->pluck('account_id')->all();
        $this->assertContains($revenueAccountId, $accountIdsHit, 'The per-company at_issue override must survive the reissue new-sale posting -- SERVICE_REVENUE must be hit.');
        $this->assertNotContains($deferredAccountId, $accountIdsHit, 'Must NOT silently fall back to the config default (at_travel) and post DEFERRED_REVENUE, dropping the company override.');
        $this->assertNotNull($newInvoiceDetail);
    }

    public function test_reissue_is_idempotent_on_a_second_call(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($oldTask);
        $newTask = $this->makeReissueTargetTask($oldTask);

        $this->service->reissue($oldTask->fresh(), $newTask->fresh());
        $countAfterFirst = Transaction::withoutGlobalScopes()->count();

        $second = $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        $this->assertTrue($second['idempotent']);
        $this->assertSame($countAfterFirst, Transaction::withoutGlobalScopes()->count(), 'A second reissue() call must post nothing new.');
    }

    // ---------------------------------------------------------------------------------------
    // Preconditions
    // ---------------------------------------------------------------------------------------

    public function test_reissue_refuses_when_old_ticket_status_is_not_issued(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, [
            'status' => 'on hold',
            'ticket_status' => null,
        ]);
        $newTask = $this->makeReissueTargetTask($oldTask);

        $this->expectException(\RuntimeException::class);
        $this->service->reissue($oldTask, $newTask);
    }

    public function test_reissue_refuses_when_old_has_no_invoice_detail(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // ticket_status forced 'issued' directly (never actually invoiced) -- the precondition
        // must still refuse: there is no invoice to post the new lines onto.
        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['ticket_status' => 'issued']);
        $newTask = $this->makeReissueTargetTask($oldTask);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no invoice_detail/');
        $this->service->reissue($oldTask, $newTask);
    }

    public function test_reissue_refuses_when_the_carrying_invoice_is_locked(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice] = $this->issueTask($oldTask);
        $invoice->update(['is_locked' => true]);
        $newTask = $this->makeReissueTargetTask($oldTask);

        $this->expectException(\RuntimeException::class);
        $this->service->reissue($oldTask->fresh(), $newTask->fresh());
    }

    public function test_reissue_refuses_when_the_old_sale_has_a_reconciled_line(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [, $oldInvoiceDetail] = $this->issueTask($oldTask);
        JournalEntry::where('invoice_detail_id', $oldInvoiceDetail->id)->update(['reconciled' => 1]);
        $newTask = $this->makeReissueTargetTask($oldTask);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/refund flow/');
        $this->service->reissue($oldTask->fresh(), $newTask->fresh());
    }

    // ---------------------------------------------------------------------------------------
    // Fare difference (DBN/CRN, computed -- never a third document)
    // ---------------------------------------------------------------------------------------

    public function test_reissue_fare_difference_is_dbn_when_new_sell_exceeds_old(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        $this->issueTask($oldTask);
        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 650.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        $this->assertSame('dbn', $result['fare_difference']['type']);
        $this->assertEqualsWithDelta(150.0, $result['fare_difference']['amount'], 0.001);
    }

    public function test_reissue_fare_difference_is_crn_when_new_sell_is_less(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        $this->issueTask($oldTask);
        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 420.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        $this->assertSame('crn', $result['fare_difference']['type']);
        $this->assertEqualsWithDelta(80.0, $result['fare_difference']['amount'], 0.001);
    }

    // ---------------------------------------------------------------------------------------
    // REISSUE FEE
    // ---------------------------------------------------------------------------------------

    public function test_reissue_fee_posts_dbn_to_reissue_fee_income_with_reason_tag(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($oldTask);
        $newTask = $this->makeReissueTargetTask($oldTask);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh(), ['fee' => 15.0]);

        $this->assertNotNull($result['fee']);
        $feeLines = JournalEntry::where('transaction_id', $result['fee']->transaction->id)->get();
        $this->assertSame(2, $feeLines->count());
        foreach ($feeLines as $line) {
            $this->assertSame('fee', $line->reason_tag);
        }

        $incomeAccountId = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'REISSUE_FEE_INCOME')
            ->value('account_id');
        $this->assertNotNull($incomeAccountId);
        $this->assertTrue($feeLines->contains('account_id', $incomeAccountId));
    }

    // ---------------------------------------------------------------------------------------
    // Commission -- un-earn old, earn new on the reissued sale's own margin
    // ---------------------------------------------------------------------------------------

    public function test_reissue_unearns_old_commission_and_posts_new_commission_on_margin(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0, 'total' => 350.0]);
        [$invoice, $oldInvoiceDetail] = $this->issueTask($oldTask);
        $commissionTx = $this->postRealCommission($company, $agent, $invoice, $oldInvoiceDetail, $oldTask, 22.5);

        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 600.0, 'total' => 400.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        // Old commission un-earned.
        $this->assertNotNull($result['commission_unearn']);
        $this->assertNotNull(Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $commissionTx->id)->first());

        // New commission JV posted on the new sale's own margin (600 - 400 = 200 * 0.15 = 30).
        $this->assertNotNull($result['commission_earn']);
        $this->assertEqualsWithDelta(30.0, (float) $result['commission_earn']->lines[0]->amount, 0.001);

        $newInvoiceDetail = InvoiceDetail::where('task_id', $newTask->id)->first();
        $this->assertNotNull(Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:' . $newInvoiceDetail->id . ':agent-commission')
            ->first());
    }

    public function test_reissue_fee_commission_gated_by_commissionable_fee_types(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTaskA = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($oldTaskA);
        $newTaskA = $this->makeReissueTargetTask($oldTaskA);

        // Not listed -- no fee commission.
        $resultA = $this->service->reissue($oldTaskA->fresh(), $newTaskA->fresh(), ['fee' => 20.0]);
        $this->assertNull($resultA['fee_commission']);

        // New pair, now listed -- fee commission posts.
        $oldTaskB = $this->makeIssuedTask($company, $agent, $client, $supplier, ['reference' => 'PNR-' . uniqid()]);
        $this->issueTask($oldTaskB);
        $newTaskB = $this->makeReissueTargetTask($oldTaskB);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.commissionable_fee_types',
            'value' => json_encode(['flight']),
            'type' => 'json',
        ]);

        $resultB = $this->service->reissue($oldTaskB->fresh(), $newTaskB->fresh(), ['fee' => 20.0]);
        $this->assertNotNull($resultB['fee_commission']);
        $this->assertEqualsWithDelta(3.0, (float) $resultB['fee_commission']->lines[0]->amount, 0.001, '15% commission rate * 20.0 fee.');
    }

    // ---------------------------------------------------------------------------------------
    // Receipt re-application + overpay disposition
    // ---------------------------------------------------------------------------------------

    public function test_reissue_repoints_existing_receipts_onto_the_new_task(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        [$invoice] = $this->issueTask($oldTask);

        $receipt = InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'task_id' => $oldTask->id,
            'client_id' => $client->id,
            'amount' => 500.0,
            'status' => 'approved',
        ]);

        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 500.0]);

        $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        $receipt->refresh();
        $this->assertSame($newTask->id, $receipt->task_id, 'The approved receipt must be re-pointed onto the new task.');
    }

    public function test_reissue_disposition_fires_when_paid_amount_exceeds_the_new_sell(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        [$invoice] = $this->issueTask($oldTask);

        InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'task_id' => $oldTask->id,
            'client_id' => $client->id,
            'amount' => 500.0,
            'status' => 'approved',
        ]);

        // New ticket is CHEAPER than what was already paid -- 200 overpay to disperse.
        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 300.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        $this->assertNotNull($result['disposition']);
        $this->assertEqualsWithDelta(200.0, (float) $result['disposition']->lines[0]->amount, 0.001);
        $this->assertSame(1, Credit::where('client_id', $client->id)->where('type', Credit::REFUND)->count());
    }

    public function test_reissue_disposition_is_a_noop_when_the_client_now_owes_more(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        [$invoice] = $this->issueTask($oldTask);

        InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'task_id' => $oldTask->id,
            'client_id' => $client->id,
            'amount' => 500.0,
            'status' => 'approved',
        ]);

        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 650.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh());

        $this->assertNull($result['disposition'], 'The client now owes MORE than was paid -- nothing to disburse.');
    }

    /**
     * Regression: reissueDisposition() must net out the reissue fee before computing the overpay,
     * exactly like voidDisposition() already nets out the void fee -- the fee is money the client
     * owes ON TOP of the new sale, never part of what was "overpaid". Old sale 100.0 paid in full;
     * reissue downgrades to 80.0 with a 10.0 fee -- the client's TRUE remaining credit is
     * 100 - 80 - 10 = 10.0, never the un-netted 100 - 80 = 20.0 the pre-fix code posted.
     */
    public function test_reissue_disposition_nets_out_the_reissue_fee_before_computing_overpay(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 100.0]);
        [$invoice] = $this->issueTask($oldTask);

        InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'task_id' => $oldTask->id,
            'client_id' => $client->id,
            'amount' => 100.0,
            'status' => 'approved',
        ]);

        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 80.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh(), ['fee' => 10.0]);

        $this->assertNotNull($result['fee']);
        $this->assertNotNull($result['disposition'], 'True overpay net of the fee is still 10.0 -- a disposition must still fire.');
        $this->assertEqualsWithDelta(
            10.0,
            (float) $result['disposition']->lines[0]->amount,
            0.001,
            'Overpay must be net of the fee (100 paid - 80 new sell - 10 fee = 10), never the un-netted 20.0.'
        );
        $this->assertSame(1, Credit::where('client_id', $client->id)->where('type', Credit::REFUND)->count());
        $credit = Credit::where('client_id', $client->id)->where('type', Credit::REFUND)->first();
        $this->assertEqualsWithDelta(10.0, (float) $credit->amount, 0.001);
    }

    /**
     * Borderline regression companion: when the fee consumes the entire raw overpay and leaves the
     * client still owing money net of the fee (90 paid - 80 new sell - 15 fee = -5, clamped to 0),
     * NO disposition may fire -- the pre-fix code would have wrongly disposed 10.0 (90 - 80) to the
     * client's credit while AR was actually understated by 5.0.
     */
    public function test_reissue_disposition_is_a_noop_when_the_fee_consumes_the_entire_overpay(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        [$invoice] = $this->issueTask($oldTask);

        InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'task_id' => $oldTask->id,
            'client_id' => $client->id,
            'amount' => 90.0,
            'status' => 'approved',
        ]);

        $newTask = $this->makeReissueTargetTask($oldTask, ['price' => 80.0]);

        $result = $this->service->reissue($oldTask->fresh(), $newTask->fresh(), ['fee' => 15.0]);

        $this->assertNotNull($result['fee']);
        $this->assertNull(
            $result['disposition'],
            'Net of the fee the client still owes 5.0 -- no disposition may fire, and none may credit the client.'
        );
        $this->assertSame(0, Credit::where('client_id', $client->id)->where('type', Credit::REFUND)->count());
    }

    // ---------------------------------------------------------------------------------------
    // dispatchFinancial() routing
    // ---------------------------------------------------------------------------------------

    public function test_dispatch_financial_routes_reissued_status_to_reissue_when_original_is_invoiced(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice, $oldInvoiceDetail] = $this->issueTask($oldTask);
        $newTask = $this->makeReissueTargetTask($oldTask);

        $this->service->dispatchFinancial($newTask->fresh());

        $oldTask->refresh();
        $newTask->refresh();
        $this->assertSame('reissued', $oldTask->ticket_status);
        $this->assertSame('issued', $newTask->ticket_status);

        $newInvoiceDetail = InvoiceDetail::where('task_id', $newTask->id)->first();
        $this->assertSame($invoice->id, $newInvoiceDetail->invoice_id);
    }

    public function test_dispatch_financial_falls_back_to_issue_when_no_resolvable_original(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $standaloneReissued = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'reissued',
            'reference' => 'PNR-' . uniqid(),
            'original_task_id' => null,
            'price' => 500.0,
            'total' => 350.0,
        ]);

        $this->service->dispatchFinancial($standaloneReissued->fresh());

        // Falls back to issue() -- posts its OWN sale, no reversal attempted (no original at all).
        $invoiceDetail = InvoiceDetail::where('task_id', $standaloneReissued->id)->first();
        $this->assertNotNull($invoiceDetail, 'issue() must still have run for the fallback case.');
    }
}
