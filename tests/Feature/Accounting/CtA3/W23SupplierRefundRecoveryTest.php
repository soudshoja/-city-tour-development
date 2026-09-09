<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RefundPostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\SupplierRefundRule;
use App\Services\Accounting\TaskIssuancePayableService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 wave 2, item W2-3 — refunds under owner ruling R-CT3, and the fix for CT-A1 CT-F11.
 *
 * CT-F11, verbatim: *"Refunds reverse the wrong side, and never reverse revenue. KWD 57,891.068
 * credited to COGS that should have credited the 1430 asset; KWD 1,768.750 of revenue on refunded
 * tasks never reversed."* — and the wrong form was still live in 2026 (176 rows).
 *
 * Three things are asserted here, and each failed before this wave:
 *
 *  1. **The supplier cost comes back only when the supplier actually refunds**, decided by
 *     configured master-data status ({@see SupplierRefundRule} over `suppliers.refund_trigger` /
 *     `refund_hold` and `config('accounting.supplier_refund.triggers')`). Before wave 2,
 *     `RefundPostingService::supplierRefundAmount()` defaulted to `cost - penalty`, so "nobody
 *     recorded what the supplier did" was booked as a full recovery.
 *  2. **When it does not come back, the cost is not erased**: it is reclassified onto
 *     `5126 Supplier Refund Loss` and the supplier payable is left standing, because the agency
 *     still owes it.
 *  3. **The cost is credited back where it actually sits.** A task whose cost is still in
 *     `1430 Unbilled Supplier Cost` relieves 1430, not COGS — CT-F11's exact complaint.
 *
 * Plus the revenue half of CT-F11: the refund's CRN reverses revenue AND the receivable.
 */
class W23SupplierRefundRecoveryTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config([
            'accounting.engine.enabled' => false,
            'accounting.engine.agent_loss_recovery_enabled' => false,
        ]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice, 6: InvoiceDetail}
     */
    private function makeFixture(array $supplierAttributes = [], string $taskStatus = 'refunded'): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create($supplierAttributes);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => $taskStatus,
            'total' => 100.000,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
        ]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        return [$company, $agent, $client, $supplier, $task->fresh(), $invoice, $invoiceDetail];
    }

    private function postRealSale(Company $company, Agent $agent, Client $client, Supplier $supplier, Task $task, Invoice $invoice, InvoiceDetail $detail, float $sell, float $cost): Transaction
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $task->type,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $client->id,
            clientName: $client->full_name,
            supplierId: $supplier->id,
            supplierName: $supplier->name,
            agentId: $agent->id,
            agentName: $agent->name,
            invoiceId: $invoice->id,
            invoiceDetailId: $detail->id,
            taskId: $task->id,
        ));

        return app(PostingService::class)->post(new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: now()->subDays(4),
            narration: 'Sale',
            lines: $lines,
            idempotencyKey: 'invoice-detail:'.$detail->id.':sale',
            invoiceId: $invoice->id,
        ))->transaction;
    }

    private function makeRefund(Company $company, Agent $agent, Invoice $invoice, Task $task, Client $client, array $detailOverrides = []): Refund
    {
        $refund = Refund::create([
            'refund_number' => 'REF-W23-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 0,
        ]);

        RefundDetail::create(array_merge([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 120.000,
            'original_task_cost' => 100.000,
            'original_task_profit' => 20.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'supplier_refund_amount' => null,
            'new_task_profit' => 0,
            'total_refund_to_client' => 120.000,
        ], $detailOverrides));

        return $refund->fresh();
    }

    /**
     * Dr - Cr on the leaf a PURPOSE CODE resolves to, not on a hard-coded account code. The
     * per-service payable/cost leaves are minted by CoaSeeder under a naming convention and
     * re-pointed by SupplierCompanyController the moment a supplier is activated, so a test that
     * pinned '21109' would be asserting against CT-A2's hand-made scaffolding rather than against
     * what the engine actually resolves.
     */
    private function netDebitForPurpose(int $companyId, string $purpose, ?string $serviceType = null): float
    {
        $account = app(\App\Services\Accounting\AccountResolver::class)->resolve($purpose, $companyId, $serviceType);

        $debit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id)->sum('debit');
        $credit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id)->sum('credit');

        return round($debit - $credit, 3);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The rule itself
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 1: the decision matrix — trigger x task status x hold x explicit amount. Every verdict
     * comes from configured master data; none of it is a constant in a feeder.
     *
     * @dataProvider recoveryMatrix
     */
    public function test_the_refund_rule_decides_recovery_from_configured_status(
        string $trigger,
        bool $hold,
        string $taskStatus,
        ?float $explicitAmount,
        bool $expectedRecover,
        float $expectedRecoverable,
        string $expectedReason
    ): void {
        $supplier = Supplier::factory()->create(['refund_trigger' => $trigger, 'refund_hold' => $hold]);
        $task = new Task(['status' => $taskStatus, 'total' => 100.000]);
        $detail = new RefundDetail([
            'original_task_cost' => 100.000,
            'supplier_charge' => 0,
            'supplier_refund_amount' => $explicitAmount,
        ]);

        $decision = app(SupplierRefundRule::class)->decide($task, $supplier, $detail);

        $this->assertSame($expectedRecover, $decision->shouldRecover, $expectedReason);
        $this->assertEqualsWithDelta($expectedRecoverable, $decision->recoverableAmount, 0.001);
        $this->assertEqualsWithDelta(100.000 - $expectedRecoverable, $decision->nonRecoverableAmount, 0.001);
        $this->assertSame($expectedReason, $decision->reason);
    }

    public static function recoveryMatrix(): array
    {
        return [
            // trigger, hold, task status, explicit amount, recovers?, recoverable, reason
            'default trigger + confirmed refund' => ['on_supplier_refund_confirmed', false, 'refunded', null, true, 100.0, 'supplier_confirmed_refund'],
            'default trigger + only requested' => ['on_supplier_refund_confirmed', false, 'refund', null, false, 0.0, 'status_not_refund_confirmed'],
            'standing agreement + requested' => ['on_refund_request', false, 'refund', null, true, 100.0, 'supplier_confirmed_refund'],
            'standing agreement + confirmed' => ['on_refund_request', false, 'refunded', null, true, 100.0, 'supplier_confirmed_refund'],
            'never recovers, even when confirmed' => ['never', false, 'refunded', null, false, 0.0, 'trigger_never'],
            'manual with no amount typed' => ['manual', false, 'refunded', null, false, 0.0, 'trigger_manual_no_amount'],
            'manual with an amount typed' => ['manual', false, 'refunded', 60.0, true, 60.0, 'operator_set_amount'],
            'hold suppresses a confirmed refund' => ['on_supplier_refund_confirmed', true, 'refunded', null, false, 0.0, 'supplier_refund_hold'],
            'an explicit amount beats a hold' => ['on_supplier_refund_confirmed', true, 'refunded', 40.0, true, 40.0, 'operator_set_amount'],
            'an explicit zero is a real answer' => ['on_supplier_refund_confirmed', false, 'refunded', 0.0, false, 0.0, 'operator_set_amount'],
        ];
    }

    /** Case 2: an unknown or null trigger degrades to the configured default, never throws. */
    public function test_an_unknown_or_null_refund_trigger_falls_back_to_the_configured_default(): void
    {
        $rule = app(SupplierRefundRule::class);

        $this->assertSame('on_supplier_refund_confirmed', $rule->triggerFor(null));

        $supplier = Supplier::factory()->create();
        \DB::table('suppliers')->where('id', $supplier->id)->update(['refund_trigger' => 'on_refund_request']);
        $this->assertSame('on_refund_request', $rule->triggerFor($supplier->fresh()));

        config(['accounting.supplier_refund.default_trigger' => 'never']);
        $this->assertSame('never', $rule->triggerFor(null));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The refund document
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 3 — THE DEFECT. The supplier does not refund (default trigger, task only at `refund`).
     * The cost must NOT be credited back as a recovery: the payable stands and the cost lands on
     * 5126 Supplier Refund Loss.
     *
     * Before wave 2 this posted Dr SERVICE_PAYABLE 100 / Cr SERVICE_COST 100 — erasing a cost the
     * agency had really borne and clearing a payable it still owed.
     */
    public function test_a_supplier_that_does_not_refund_leaves_the_payable_and_books_a_loss(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture([], 'refund');

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $detail, 120.000, 100.000);

        $payableBefore = $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight');
        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);

        app(RefundPostingService::class)->post($refund, null);

        $loss = $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS');
        $this->assertEqualsWithDelta(
            100.000,
            $loss,
            0.001,
            'A cost the supplier is not giving back must land on 5126 Supplier Refund Loss.'
        );

        $this->assertEqualsWithDelta(
            $payableBefore,
            $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'),
            0.001,
            'The supplier payable must be untouched: we still owe the supplier.'
        );

        $supplierCredit = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'refund:'.$refund->id.':supplier-credit:'.$refund->refundDetails->first()->id)
            ->firstOrFail();

        $lines = JournalEntry::where('transaction_id', $supplierCredit->id)->get();
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.001);
    }

    /**
     * Case 4: the supplier DOES refund (task confirmed `refunded`) — the payable is debited, the
     * cost credited back, exactly the pre-existing shape. The wave-2 gate must not break the
     * ordinary happy path.
     */
    public function test_a_supplier_that_refunds_relieves_the_payable_and_the_cost(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture([], 'refunded');

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $detail, 120.000, 100.000);
        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);

        app(RefundPostingService::class)->post($refund, null);

        $this->assertEqualsWithDelta(
            0.000,
            $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'),
            0.001,
            'A recovered cost must not touch the refund-loss leaf at all.'
        );

        // The payable was credited 100 by the sale and is now debited 100 back: net zero.
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001);
    }

    /**
     * Case 5: the supplier refunds, keeping a penalty. The penalty keeps its own
     * PENALTY_COST_EXPENSE leaf (5124) — it is a real cost of a real refund, not an unrecovered
     * loss, and the two must stay distinguishable in the P&L.
     */
    public function test_a_penalty_kept_out_of_a_real_refund_stays_on_the_penalty_leaf(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture([], 'refunded');

        $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $detail, 120.000, 100.000);
        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client, ['supplier_charge' => 15.000]);

        app(RefundPostingService::class)->post($refund, null);

        $this->assertEqualsWithDelta(15.000, $this->netDebitForPurpose($company->id, 'PENALTY_COST_EXPENSE'), 0.001, 'The penalty belongs on 5124 Refund Penalty Cost.');
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'), 0.001, 'A penalty is not an unrecovered loss.');
        $this->assertEqualsWithDelta(-15.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'Only the net refund relieves the payable.');
    }

    /**
     * Case 6 — CT-F11's own words. The cost is still sitting in `1430 Unbilled Supplier Cost` (the
     * task was accrued at issuance and its sale document was posted by a LEGACY path that left no
     * engine SERVICE_COST line). The refund must credit 1430, not COGS.
     *
     * Before wave 2 the credit went to SERVICE_COST unconditionally: *"COGS understated and
     * Unbilled Supplier Cost overstated by the same amount."*
     */
    public function test_a_cost_still_in_1430_is_credited_back_to_1430_not_to_cogs(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture([], 'issued');

        // The issuance accrual: Dr 1430 / Cr SERVICE_PAYABLE, while the task is still `issued`.
        // No sale document is posted, so the cost never reaches cost of sales -- exactly the
        // post-P1a shape CT-F11 describes.
        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertEqualsWithDelta(100.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001, 'Fixture check: the cost is in the asset.');

        // The supplier then confirms the refund. The status is set directly (not through
        // dispatchFinancial) so this test exercises the REFUND DOCUMENT's own handling of a cost
        // sitting in 1430, which is what CT-F11 is about -- the accrual-side path is case 8/9.
        $task->status = 'refunded';
        $task->save();

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);

        app(RefundPostingService::class)->post($refund, null);

        $this->assertEqualsWithDelta(
            0.000,
            $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'),
            0.001,
            'A refund must relieve the asset the cost is actually sitting in.'
        );

        $this->assertEqualsWithDelta(
            0.000,
            $this->netDebitForPurpose($company->id, 'SERVICE_COST', 'flight'),
            0.001,
            'It must NOT credit a cost-of-sales leaf that never carried this cost (CT-F11).'
        );
    }

    /**
     * Case 7 — CT-F11's revenue half. The refund's CRN reverses BOTH the revenue and the
     * receivable. CT-A1 measured 367 refunded tasks carrying revenue credit against 0.000 of
     * revenue debit.
     */
    public function test_the_refund_reverses_revenue_and_the_receivable(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture([], 'refunded');

        $sale = $this->postRealSale($company, $agent, $client, $supplier, $task, $invoice, $detail, 120.000, 100.000);

        $this->assertEqualsWithDelta(-120.000, $this->netDebitForPurpose($company->id, 'SERVICE_REVENUE', 'flight'), 0.001, 'Fixture check: revenue was credited.');
        $this->assertEqualsWithDelta(120.000, $this->netDebitForPurpose($company->id, 'RECEIVABLE_CONTROL'), 0.001, 'Fixture check: the client was debited.');

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client);
        app(RefundPostingService::class)->post($refund, null);

        $this->assertEqualsWithDelta(
            0.000,
            $this->netDebitForPurpose($company->id, 'SERVICE_REVENUE', 'flight'),
            0.001,
            'Revenue must be reversed on a refund (CT-F11: 367 refunded tasks carried revenue credit against 0.000 of revenue debit).'
        );

        // The receivable is asserted on the REVERSAL DOCUMENT rather than on the account's net,
        // because this refund's disposition is `credit` -- the client's money becomes a client
        // credit, which legitimately re-debits AR against CLIENT_ADVANCE (W7.P). What CT-F11 is
        // about is whether the SALE was reversed at all, and that is this document.
        $reversal = Transaction::withoutGlobalScopes()
            ->where('reversal_of_transaction_id', $sale->id)
            ->firstOrFail();

        $reversalLines = JournalEntry::where('transaction_id', $reversal->id)->get();

        $revenueAccount = app(\App\Services\Accounting\AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'flight');
        $receivableAccount = app(\App\Services\Accounting\AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);

        $revenueLine = $reversalLines->firstWhere('account_id', $revenueAccount->id);
        $receivableLine = $reversalLines->firstWhere('account_id', $receivableAccount->id);

        $this->assertNotNull($revenueLine, 'The reversal must carry a revenue leg.');
        $this->assertNotNull($receivableLine, 'The reversal must carry a receivable leg.');
        $this->assertEqualsWithDelta(120.000, (float) $revenueLine->debit, 0.001, 'Revenue is DEBITED back.');
        $this->assertEqualsWithDelta(120.000, (float) $receivableLine->credit, 0.001, 'The receivable is CREDITED back.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // The uninvoiced mirror: the issuance accrual
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 8: an UNINVOICED task that reaches a refund status the supplier has not confirmed.
     * `RefundPostingService` refuses a detail with no invoice_detail at all, so this path is the
     * only one such a task has. The accrual must NOT simply be reversed as if the supplier had
     * refunded: the payable stands and the 1430 balance is reclassified to the loss leaf.
     */
    public function test_an_unconfirmed_refund_on_an_uninvoiced_task_moves_1430_to_the_loss_leaf(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture([], 'issued');

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());
        $this->assertEqualsWithDelta(100.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001);

        $task->status = 'refund';
        $task->save();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001, 'The asset is relieved...');
        $this->assertEqualsWithDelta(100.000, $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'), 0.001, '...onto the refund-loss leaf, not by reversing the accrual.');
        $this->assertEqualsWithDelta(-100.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'The supplier payable still stands: we still owe it.');

        $this->assertNotNull(
            Transaction::withoutGlobalScopes()->where('idempotency_key', 'task:'.$task->id.':refund-loss')->first(),
            'The reclassification is its own keyed document, so a later confirmation can still reverse the accrual.'
        );
    }

    /**
     * Case 9: the same task, but the supplier HAS confirmed the refund. The accrual is reversed
     * outright — no loss line at all.
     */
    public function test_a_confirmed_refund_on_an_uninvoiced_task_reverses_the_accrual(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture([], 'issued');

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $task->status = 'refunded';
        $task->save();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001);
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'), 0.001, 'A confirmed refund books no loss.');
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001, 'The payable is relieved: the supplier is paying us back.');
    }

    /**
     * Case 10: a VOID is not a refund. It keeps the plain reversal — nothing happened, so the
     * accrual comes straight off and no loss is booked. This is the case the refund branch must
     * not have swallowed.
     */
    public function test_a_void_still_reverses_the_accrual_outright(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture([], 'issued');

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $task->status = 'void';
        $task->save();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'UNBILLED_SUPPLIER_COST'), 0.001);
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SUPPLIER_REFUND_LOSS'), 0.001, 'A void is not a refund loss.');
        $this->assertEqualsWithDelta(0.000, $this->netDebitForPurpose($company->id, 'SERVICE_PAYABLE', 'flight'), 0.001);
    }
}
