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
use App\Models\Supplier;
use App\Models\SupplierChargeRule;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 wave 2, item W2-5 — cancelling/voiding a task AFTER it has been invoiced.
 *
 * ── Inventory first (the existing cancellation feeder) ──────────────────────────────────────────
 * {@see TaskStatusService::void()} already: reverses the sale document
 * (`voidReverseSale()`), posts the agency's OWN void fee to the client
 * (`Dr RECEIVABLE_CONTROL / Cr VOID_FEE_INCOME`), un-earns the agent commission, posts a
 * fee-commission, disposes of the client balance, and — since wave 1 — reverses the task's
 * issuance accrual. {@see TaskStatusService::cancel()} covers the never-issued lifecycle
 * (`on hold`/`confirmed` -> `cancelled`) and correctly writes nothing to the ledger, because
 * nothing was ever accrued for those.
 *
 * So the sale reversal and the payable reversal both already worked. Case 1 below PINS that,
 * because "already works" is a claim a verification lane has to prove rather than assert.
 *
 * ── The gap W2-5 closes ─────────────────────────────────────────────────────────────────────────
 * Under wave 1's GROSS basis the sale document carries its own `SERVICE_COST`/`SERVICE_PAYABLE`
 * pair, so reversing it takes the supplier payable to ZERO — as though every cancellation were
 * free. Nothing in the engine expressed the fee a supplier actually KEEPS on a cancellation.
 *
 * Under R-CT3 that fee is configured master data, and the master data already exists:
 * `supplier_charge_rules`, which carries basis / amount / effective dates / active /
 * recharge policy / cost-account override per company × supplier × service × channel, with its own
 * resolver and line builder. All that was missing was a name — one new `charge_kind` value,
 * `cancellation_fee`.
 */
class W25VoidSupplierCancellationFeeTest extends AccountingTestCase
{
    private Company $company;

    private Agent $agent;

    private Client $client;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10));

        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($this->company->id);
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $this->company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->company->id]);

        $branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'Sales']);
        $this->agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
        $this->supplier = Supplier::factory()->create();
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: Task, 1: InvoiceDetail} */
    private function invoicedTask(float $sell = 300.000, float $cost = 250.000): array
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'ticket_status' => 'issued',
            'client_status' => 'open',
            'total' => $cost,
            'price' => $sell,
            'reference' => 'CTA3-W25-'.uniqid(),
            'issued_date' => Carbon::create(2026, 6, 10),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'amount' => $sell,
            'status' => 'unpaid',
            'invoice_date' => Carbon::create(2026, 6, 11),
        ]);

        $detail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'task_price' => $sell,
            'commission' => 0,
        ]);

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $this->client->id,
            clientName: $this->client->full_name,
            supplierId: $this->supplier->id,
            supplierName: $this->supplier->name,
            agentId: $this->agent->id,
            agentName: $this->agent->name,
            invoiceId: $invoice->id,
            invoiceDetailId: $detail->id,
            taskId: $task->id,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $this->company->id,
            branchId: (int) $this->agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: Carbon::create(2026, 6, 11),
            narration: 'Sale',
            lines: $lines,
            idempotencyKey: 'invoice-detail:'.$detail->id.':sale',
            invoiceId: $invoice->id,
        ));

        return [$task->fresh(), $detail->fresh()];
    }

    private function netForPurpose(string $purpose, ?string $serviceType = null): float
    {
        $account = app(AccountResolver::class)->resolve($purpose, $this->company->id, $serviceType);

        $debit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id)->sum('debit');
        $credit = (float) JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id)->sum('credit');

        return round($debit - $credit, 3);
    }

    private function cancellationFeeRule(array $overrides = []): SupplierChargeRule
    {
        return SupplierChargeRule::create(array_merge([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'service_type' => 'flight',
            'channel' => null,
            'charge_kind' => 'cancellation_fee',
            'basis' => 'fixed',
            'amount' => 20.000,
            'currency' => 'KWD',
            'recharge_policy' => 'absorb',
            'commissionable' => false,
            'active' => true,
            'once_per_reference' => false,
            'label' => 'Consolidator cancellation fee',
        ], $overrides));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 1. What already worked — pinned, not assumed
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 1: voiding an invoiced task with NO cancellation-fee rule reverses the whole sale —
     * receivable, revenue, cost AND supplier payable all return to zero. That is the correct
     * behaviour when a cancellation genuinely costs nothing, and it is the baseline the fee case
     * below has to differ from.
     */
    public function test_voiding_an_invoiced_task_reverses_the_sale_and_the_payable(): void
    {
        [$task] = $this->invoicedTask(300.000, 250.000);

        $this->assertEqualsWithDelta(300.000, $this->netForPurpose('RECEIVABLE_CONTROL'), 0.0005);
        $this->assertEqualsWithDelta(-250.000, $this->netForPurpose('SERVICE_PAYABLE', 'flight'), 0.0005);

        app(TaskStatusService::class)->void($task);

        $this->assertEqualsWithDelta(0.000, $this->netForPurpose('SERVICE_REVENUE', 'flight'), 0.0005, 'Revenue reversed.');
        $this->assertEqualsWithDelta(0.000, $this->netForPurpose('SERVICE_COST', 'flight'), 0.0005, 'Cost reversed.');
        $this->assertEqualsWithDelta(0.000, $this->netForPurpose('SERVICE_PAYABLE', 'flight'), 0.0005, 'Supplier payable reversed.');
        $this->assertEqualsWithDelta(0.000, $this->netForPurpose('RECEIVABLE_CONTROL'), 0.0005, 'Receivable reversed.');

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->where('sub_type', 'SUPPLIER_CXL_FEE')->count(),
            'No configured cancellation fee means no cancellation-fee document.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 2. The gap W2-5 closes
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 2 — THE DEFECT. With a configured cancellation fee the supplier KEEPS 20, so after the
     * void the agency still owes 20 and has borne a 20 cost. Before wave 2 the payable went to
     * zero: every void looked free.
     */
    public function test_a_configured_cancellation_fee_is_kept_as_a_payable(): void
    {
        [$task] = $this->invoicedTask(300.000, 250.000);
        $this->cancellationFeeRule();

        app(TaskStatusService::class)->void($task);

        $this->assertEqualsWithDelta(
            -20.000,
            $this->netForPurpose('SERVICE_PAYABLE', 'flight'),
            0.0005,
            'The supplier keeps its cancellation fee: the agency still owes exactly that.'
        );

        $this->assertEqualsWithDelta(
            20.000,
            $this->netForPurpose('SUPPLIER_CHARGE_EXPENSE'),
            0.0005,
            'And has borne it as a cost.'
        );

        $document = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('idempotency_key', 'void:'.$task->id.':supplier-cxl-fee')
            ->firstOrFail();

        $this->assertSame('SUPPLIER_CXL_FEE', $document->sub_type);
        $this->assertLessThanOrEqual(16, strlen((string) $document->sub_type), 'transactions.sub_type is varchar(16).');

        $lines = JournalEntry::where('transaction_id', $document->id)->get();
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0005);

        $payableLine = $lines->firstWhere('credit', '>', 0);
        $this->assertSame((int) $this->supplier->id, (int) $payableLine->type_reference_id, 'The retained fee carries its supplier party.');
    }

    /**
     * Case 3: the amount is the RULE's, not a constant. A percent-of-total rule bills a
     * percentage of the sell, resolved by the same builder the sale feeder uses.
     */
    public function test_the_fee_amount_comes_from_the_configured_basis(): void
    {
        [$task] = $this->invoicedTask(300.000, 250.000);
        $this->cancellationFeeRule(['basis' => 'percent_of_total', 'amount' => 10.000]);

        app(TaskStatusService::class)->void($task);

        $this->assertEqualsWithDelta(-30.000, $this->netForPurpose('SERVICE_PAYABLE', 'flight'), 0.0005, '10% of the 300 sell.');
    }

    /**
     * Case 4: an INACTIVE rule, and a rule for another supplier, both post nothing. The fee is
     * configured per supplier — it is never a company-wide constant applied to everybody.
     */
    public function test_an_inactive_rule_or_another_suppliers_rule_posts_nothing(): void
    {
        [$task] = $this->invoicedTask(300.000, 250.000);

        $this->cancellationFeeRule(['active' => false]);
        $other = Supplier::factory()->create();
        $this->cancellationFeeRule(['supplier_id' => $other->id]);

        app(TaskStatusService::class)->void($task);

        $this->assertEqualsWithDelta(0.000, $this->netForPurpose('SERVICE_PAYABLE', 'flight'), 0.0005);
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->where('sub_type', 'SUPPLIER_CXL_FEE')->count()
        );
    }

    /**
     * Case 5: `recharge_policy = recharge_client` passes the fee on. The builder's own recharge
     * pair fires — this feeder inherits the policy rather than re-deciding it.
     */
    public function test_a_recharged_cancellation_fee_also_bills_the_client(): void
    {
        [$task] = $this->invoicedTask(300.000, 250.000);
        $this->cancellationFeeRule(['recharge_policy' => 'recharge_client']);

        app(TaskStatusService::class)->void($task);

        $this->assertEqualsWithDelta(-20.000, $this->netForPurpose('SERVICE_PAYABLE', 'flight'), 0.0005);
        $this->assertEqualsWithDelta(
            -20.000,
            $this->netForPurpose('SUPPLIER_CHARGE_RECHARGE_INCOME'),
            0.0005,
            'A recharged fee is recovered from the client, per the rule.'
        );
    }

    /**
     * Case 6: idempotence. void() short-circuits on an already-void ticket, and the fee document
     * carries its own key regardless — a re-void can never fee twice.
     */
    public function test_a_re_void_never_fees_twice(): void
    {
        [$task] = $this->invoicedTask(300.000, 250.000);
        $this->cancellationFeeRule();

        app(TaskStatusService::class)->void($task);
        $result = app(TaskStatusService::class)->void($task->fresh());

        $this->assertTrue($result['idempotent']);
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->where('sub_type', 'SUPPLIER_CXL_FEE')->count()
        );
        $this->assertEqualsWithDelta(-20.000, $this->netForPurpose('SERVICE_PAYABLE', 'flight'), 0.0005);
    }
}
