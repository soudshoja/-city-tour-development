<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\InvoiceController;
use App\Models\Account;
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
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\AccountingTestCase;

/**
 * KEY: invoice-profit-loss. W3c — cuts InvoiceController's D1 + the 4 profit/loss generator
 * methods (createGatewayProfitEntries -- deleted and replaced by createGatewayFeeRecoveryEntries()
 * per W4.D, see that test below --, createSupplierLossEntries, createFeeLossEntries,
 * createProfitEntries) onto {@see \App\Services\Accounting\PostingSeam} (R3 route-to-legacy).
 *
 * These four methods (and the new postSaleJournalEntries() helper that now carries HEAD's
 * "ENTRY 1"/"ENTRY 2" out of addJournalEntry()) are `private`, and addJournalEntry() itself has
 * a large orchestration surface (charge/gateway-fee calculation, auto-COA-creation, a JsonResponse
 * contract) that is out of THIS lane's scope to fixture end-to-end. Every test below therefore
 * calls the private method directly via ReflectionMethod — a deliberate, narrow bypass of
 * addJournalEntry()'s own orchestration, not a weaker test: it lets each scenario control
 * `$profit`/`$commission`/`$markupProfit` exactly, which is what the ON/OFF contract in this
 * lane's brief is actually about.
 */
class InvoiceControllerProfitLossPostingTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Task, 5: Invoice, 6: InvoiceDetail, 7: Transaction}
     */
    private function makeFixture(
        int $agentTypeId = 2,
        float $commissionRate = 0.15,
        string $taskType = 'hotel', // matches CoaSeeder's pre-seeded 'Hotel Booking Revenue' leaf
        float $taskTotal = 150.000, // W3d: pinned (was the factory's random 150-2500) — the sale
        // posting now reads $task->total as the supplier cost, so every sale-shape test needs a
        // deterministic value to compute a deterministic margin from.
        bool $withSupplier = true
    ): array {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => $agentTypeId], ['name' => 'type-'.$agentTypeId]);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'commission' => $commissionRate,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $supplier = $withSupplier ? Supplier::factory()->create() : null;

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier?->id,
            'type' => $taskType,
            'total' => $taskTotal,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now(),
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
        ]);

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);

        return [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, $transaction];
    }

    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    private function resolvedAccountId(Company $company, string $purposeCode, ?string $serviceType = null): ?int
    {
        return DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', $purposeCode)
            ->where(function ($q) use ($serviceType) {
                $serviceType === null ? $q->whereNull('service_type') : $q->where('service_type', $serviceType);
            })
            ->value('account_id');
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (a) ON path sale posting — W3d: agent(NET) basis, Dr RECEIVABLE_CONTROL / Cr SERVICE_PAYABLE
    //     / Cr SERVICE_REVENUE (margin). Replaces the pre-W3d 2-line gross-and-incomplete shape
    //     (sale-shape-audit.md) — 'hotel' defaults to 'agent' basis (config('accounting.posting_basis')).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_sale_posts_receivable_control_service_payable_and_service_revenue_margin(): void
    {
        // total (supplier cost) pinned to 150.000 by makeFixture(); sell 250.000 -> margin 100.000.
        [$company, , $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $controller = app(InvoiceController::class);

        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            250.000,
            $client->full_name,
        ]);

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);

        $receivableControlId = $this->resolvedAccountId($company, 'RECEIVABLE_CONTROL');
        $servicePayableId = $this->resolvedAccountId($company, 'SERVICE_PAYABLE', 'hotel');
        $serviceRevenueId = $this->resolvedAccountId($company, 'SERVICE_REVENUE', 'hotel');
        $markupIncomeId = $this->resolvedAccountId($company, 'MARKUP_INCOME');

        $this->assertNotNull($receivableControlId);
        $this->assertNotNull($servicePayableId);
        $this->assertNotNull($serviceRevenueId);
        $this->assertNotEquals($serviceRevenueId, $markupIncomeId, 'Fixture sanity: SERVICE_REVENUE(hotel) and MARKUP_INCOME must be different leaves.');

        $lines = DB::table('journal_entries')->where('transaction_id', $result->transaction->id)->get();
        $this->assertCount(3, $lines, 'ON-path NET sale document must be Dr receivable / Cr payable / Cr margin.');

        $debitLine = $lines->firstWhere('account_id', $receivableControlId);
        $payableLine = $lines->firstWhere('account_id', $servicePayableId);
        $revenueLine = $lines->firstWhere('account_id', $serviceRevenueId);

        $this->assertNotNull($debitLine, 'Must debit RECEIVABLE_CONTROL.');
        $this->assertNotNull($payableLine, 'Must credit SERVICE_PAYABLE(hotel) with the supplier cost — this is the audit finding W3d fixes: cost was never posted at all before.');
        $this->assertNotNull($revenueLine, 'Must credit SERVICE_REVENUE(hotel) with the margin.');

        $this->assertEquals(250.000, (float) $debitLine->debit);
        $this->assertEquals(0.0, (float) $debitLine->credit);

        $this->assertEquals(150.000, (float) $payableLine->credit);
        $this->assertEquals(0.0, (float) $payableLine->debit);
        $this->assertSame((int) $task->supplier_id, (int) $payableLine->type_reference_id);

        $this->assertEquals(100.000, (float) $revenueLine->credit, 'Margin = sell(250) - cost(150) = 100, never the full sell price.');
        $this->assertEquals(0.0, (float) $revenueLine->debit);

        // No line anywhere in this document may carry the full $250 sell price except the
        // receivable debit itself — the exact defect the audit found (SERVICE_REVENUE credited
        // the FULL sell, not the margin).
        $this->assertSame(
            0,
            $lines->filter(fn ($l) => $l->id !== $debitLine->id && (float) $l->credit === 250.000)->count(),
            'No non-receivable line may carry the full sell price — SERVICE_REVENUE must hold only the margin.'
        );
    }

    /**
     * Negative-margin sign test (sold below cost): SERVICE_REVENUE flips to the DEBIT side for
     * abs(margin) rather than the whole sale being refused — mirrors ChatController's own
     * pre-existing sign-aware handling, now shared via SaleDraftBuilder. w3d-brief.md's own "W4.A
     * rule: company-borne negative margin posts nothing extra" — this IS the only posting for the
     * loss; the document still balances on its own (Dr receivable + Dr margin(abs) = Cr payable).
     */
    public function test_on_path_sale_negative_margin_debits_service_revenue(): void
    {
        [$company, , $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture(taskTotal: 300.000);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $controller = app(InvoiceController::class);

        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            250.000, // sold below the 300.000 cost -> margin = -50.000
            $client->full_name,
        ]);

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);
        $this->assertEqualsWithDelta(
            0.0,
            (float) $result->transaction->total_debit - (float) $result->transaction->total_credit,
            0.0005,
            'Below-cost sale must still balance: Dr receivable + Dr margin(abs) = Cr payable.'
        );

        $serviceRevenueId = $this->resolvedAccountId($company, 'SERVICE_REVENUE', 'hotel');
        $lines = DB::table('journal_entries')->where('transaction_id', $result->transaction->id)->get();
        $this->assertCount(3, $lines);

        $marginLine = $lines->firstWhere('account_id', $serviceRevenueId);
        $this->assertNotNull($marginLine);
        $this->assertEquals(50.000, (float) $marginLine->debit, 'Negative margin posts as a DEBIT of abs(margin), never a negative credit.');
        $this->assertEquals(0.0, (float) $marginLine->credit);
        $this->assertSame('income', $marginLine->type, 'A contra-income debit still carries ledgerType income, not expense — matches ChatController\'s own convention.');
    }

    /**
     * Margin vs MARKUP_INCOME (4132) exclusivity — w3d-brief.md decision 3: "Never both for the
     * same money." Proves the ordinary agent-basis margin (positive AND negative) never touches
     * MARKUP_INCOME, which is reserved for a distinct, not-yet-modeled event (an explicit markup
     * on top of a fare the invoice already separates from cost).
     */
    public function test_on_path_sale_margin_never_posts_to_markup_income(): void
    {
        [$company, , $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $markupIncomeId = $this->resolvedAccountId($company, 'MARKUP_INCOME');
        $this->assertNotNull($markupIncomeId);

        $controller = app(InvoiceController::class);

        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            250.000, // positive margin (cost pinned to 150.000)
            $client->full_name,
        ]);

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('transaction_id', $result->transaction->id)->where('account_id', $markupIncomeId)->count(),
            'The ordinary sale margin must never post to MARKUP_INCOME (4132) — that leaf is reserved for a distinct, not-yet-modeled event.'
        );
    }

    /**
     * Principal (GROSS) basis — w3d-brief.md decision 4/2: 'tour' defaults to 'principal'. Full
     * sell posts as SERVICE_REVENUE; cost posts as a separate SERVICE_COST/SERVICE_PAYABLE
     * cost-of-sales pair — SERVICE_COST's first real call site (dead purpose code since W1 per
     * the sale-shape audit).
     */
    public function test_on_path_principal_basis_sale_posts_gross_revenue_and_cost_of_sales(): void
    {
        [$company, , $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture(taskType: 'tour', taskTotal: 150.000);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6): 'tour' now DEFAULTS to at_travel revenue
        // recognition — this test is about the GROSS posting SHAPE (W3d), not recognition timing,
        // so it pins at_issue via the same per-company Setting override
        // SaleDraftBuilder::resolveRecognitionTiming() consults. See
        // RevenueRecognitionServiceTest for the at_travel/deferred coverage.
        \App\Models\Setting::create([
            'company_id' => $company->id, 'key' => 'accounting.revenue_recognition.tour',
            'value' => 'at_issue', 'type' => 'string',
        ]);

        $controller = app(InvoiceController::class);

        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            250.000,
            $client->full_name,
        ]);

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);

        $receivableControlId = $this->resolvedAccountId($company, 'RECEIVABLE_CONTROL');
        $serviceRevenueId = $this->resolvedAccountId($company, 'SERVICE_REVENUE', 'tour');
        $serviceCostId = $this->resolvedAccountId($company, 'SERVICE_COST', 'tour');
        $servicePayableId = $this->resolvedAccountId($company, 'SERVICE_PAYABLE', 'tour');

        $this->assertNotNull($serviceCostId, 'SERVICE_COST(tour) must be mapped — this is its first real call site.');

        $lines = DB::table('journal_entries')->where('transaction_id', $result->transaction->id)->get();
        $this->assertCount(4, $lines, 'Principal basis: receivable + gross revenue + cost-of-sales pair.');

        $debitLine = $lines->firstWhere('account_id', $receivableControlId);
        $revenueLine = $lines->firstWhere('account_id', $serviceRevenueId);
        $costLine = $lines->firstWhere('account_id', $serviceCostId);
        $payableLine = $lines->firstWhere('account_id', $servicePayableId);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($revenueLine);
        $this->assertNotNull($costLine);
        $this->assertNotNull($payableLine);

        $this->assertEquals(250.000, (float) $debitLine->debit);
        $this->assertEquals(250.000, (float) $revenueLine->credit, 'Principal basis: SERVICE_REVENUE holds the FULL sell price, not the margin.');
        $this->assertEquals(150.000, (float) $costLine->debit, 'SERVICE_COST holds the supplier cost as a debit (cost-of-sales).');
        $this->assertEquals(150.000, (float) $payableLine->credit);
    }

    /**
     * Company option override (w3d-brief.md decision 2): a company may flip a service type's
     * posting_basis via the per-company `settings` table
     * (key 'accounting.posting_basis.{service_type}'), the same convention
     * App\Models\Company::hasModule() uses for 'module.*' overrides.
     */
    public function test_on_path_sale_posting_basis_company_override_flips_shape(): void
    {
        [$company, , $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture(taskType: 'hotel', taskTotal: 150.000);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        \App\Models\Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.posting_basis.hotel',
            'value' => 'principal',
            'type' => 'string',
        ]);

        $controller = app(InvoiceController::class);

        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            250.000,
            $client->full_name,
        ]);

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);

        $lines = DB::table('journal_entries')->where('transaction_id', $result->transaction->id)->get();
        $this->assertCount(4, $lines, 'Company override to principal basis must produce the 4-line gross + cost-of-sales shape, not the agent-basis default 3-line shape.');

        $serviceRevenueId = $this->resolvedAccountId($company, 'SERVICE_REVENUE', 'hotel');
        $revenueLine = $lines->firstWhere('account_id', $serviceRevenueId);
        $this->assertEquals(250.000, (float) $revenueLine->credit, 'Overridden to principal basis: SERVICE_REVENUE now holds the full sell price.');
    }

    /**
     * Refund reversal (W3b PostingService::reverse()) still balances against the NEW 3-line NET
     * shape. reverse() is shape-agnostic (it swaps debit/credit for whatever canonical lines a
     * transaction actually has — see its own docblock), so this proves that generic mechanism
     * against THIS lane's specific new shape, without touching W3b's own code.
     */
    public function test_on_path_sale_reversal_still_balances_against_the_net_shape(): void
    {
        [$company, , $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $controller = app(InvoiceController::class);

        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            250.000,
            $client->full_name,
        ]);

        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $result);

        $reversal = app(\App\Services\Accounting\PostingService::class)->reverse(
            $result->transaction,
            now(),
            null
        );

        $this->assertEqualsWithDelta(
            0.0,
            (float) $reversal->transaction->total_debit - (float) $reversal->transaction->total_credit,
            0.0005,
            'The reversal of the new 3-line NET sale document must itself balance.'
        );

        $reversalLines = DB::table('journal_entries')->where('transaction_id', $reversal->transaction->id)->get();
        $this->assertCount(3, $reversalLines, 'Reversal must carry the same number of lines as the original.');

        // Original: Dr receivable 250 / Cr payable 150 / Cr margin 100. Reversed: Cr receivable
        // 250 / Dr payable 150 / Dr margin 100.
        $reversedCredits = $reversalLines->pluck('credit')->map(fn ($v) => (float) $v)->sort()->values();
        $reversedDebits = $reversalLines->pluck('debit')->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0)->sort()->values();

        $this->assertEqualsWithDelta(250.000, (float) $reversedCredits->last(), 0.0005, 'The receivable leg reverses to a 250 credit.');
        $this->assertEqualsWithDelta([100.000, 150.000], $reversedDebits->all(), 0.0005, 'Payable(150) and margin(100) legs both reverse to debits.');
    }

    /**
     * W4.D UPDATE: `createGatewayProfitEntries()` is deleted (it double-booked markup/rounding as
     * a phantom Dr <gateway asset account> / Cr "Gateway Fee Recovery" pair, disconnected from
     * RECEIVABLE_CONTROL). Its replacement, `createGatewayFeeRecoveryEntries()`, grosses up
     * RECEIVABLE_CONTROL itself (Dr) against the SAME "Gateway Fee Recovery" leaf (Cr) — this test
     * now exercises that new method/shape while preserving the original regression's intent: the
     * ON path must still credit the SAME "Gateway Fee Recovery" leaf (CoaSeeder 4131) the
     * OFF/legacy closure credits — per the locked decision "Gateway fee recovery from CLIENT =
     * 4131 (income)" — and must NOT post to MARKUP_INCOME (4132), a distinct economic concept
     * (sale-margin income, e.g. ChatController's NET-margin variant). The assertion is against the
     * OFF path's own resolved account (not just internal self-consistency), and explicitly checks
     * the two purpose codes differ so this can't pass by 4131 and 4132 accidentally colliding.
     */
    public function test_on_path_gateway_markup_credits_gateway_fee_recovery_4131_not_markup_income(): void
    {
        [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();

        // Resolve BEFORE enabling the engine so this is the same lookup the OFF/legacy closure
        // performs (Account::where('name', 'Gateway Fee Recovery')->where('company_id', ...)).
        $gatewayIncomeAccount = Account::where('company_id', $company->id)
            ->where('name', 'Gateway Fee Recovery')
            ->first();
        $this->assertNotNull($gatewayIncomeAccount, 'CoaSeeder must seed a Gateway Fee Recovery (4131) leaf for this fixture to be meaningful.');

        $receivableAccount = Account::where('company_id', $company->id)
            ->where('name', 'Clients')
            ->first();
        $this->assertNotNull($receivableAccount, 'CoaSeeder must seed a Clients (RECEIVABLE_CONTROL) leaf for this fixture to be meaningful.');

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $markupIncomeId = $this->resolvedAccountId($company, 'MARKUP_INCOME');
        $this->assertNotNull($markupIncomeId);
        $this->assertNotEquals(
            $gatewayIncomeAccount->id,
            $markupIncomeId,
            'Fixture sanity check: Gateway Fee Recovery (4131) and MARKUP_INCOME (4132) must be different leaves, otherwise this test cannot distinguish them.'
        );

        $controller = app(InvoiceController::class);

        // W4.D fix round 2: createGatewayFeeRecoveryEntries() is now a per-PAYMENT method (see its
        // own docblock — it posts from PaymentController::createInvoicePaymentCOA(), dated the
        // payment, never the invoice), so exercising it here needs a real Payment row and the
        // new named-argument signature; $paidBy is now a plain string (the same 'paid_by' value
        // PaymentController resolves from ChargeService::calculate()), not a hand-built Charge.
        $payment = \App\Models\Payment::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => null, // factory default (1) has no matching row for this fixture's company
            'created_by' => $agent->user_id, // factory default (1) has no matching row for this fixture
            'amount' => 100.000,
            'payment_gateway' => 'TESTGATEWAY',
            'payment_method_id' => null,
        ]);

        $controller->createGatewayFeeRecoveryEntries(
            payment: $payment,
            invoice: $invoice,
            companyId: $company->id,
            branchId: $branch->id,
            gatewayName: 'TESTGATEWAY',
            paidBy: 'Client', // bearer=Client -- the method only posts anything when the client bears the fee
            accountingFee: 1.000, // real processor cost
            markupProfit: 2.500,
            roundingProfit: 0.500,
            postingDate: now(),
            invoiceDetail: $invoiceDetail,
        );

        $creditLine = DB::table('journal_entries')
            ->where('account_id', $gatewayIncomeAccount->id)
            ->where('invoice_detail_id', $invoiceDetail->id)
            ->first();

        $this->assertNotNull($creditLine, 'Gateway fee gross-up must credit Gateway Fee Recovery (4131) on the ON path — same leaf as the OFF/legacy path.');
        $this->assertEquals(4.000, (float) $creditLine->credit);
        $this->assertEquals(0.0, (float) $creditLine->debit);

        // Must NOT have posted anything to MARKUP_INCOME (4132) — that would be the bug.
        $wrongLine = DB::table('journal_entries')
            ->where('account_id', $markupIncomeId)
            ->where('invoice_detail_id', $invoiceDetail->id)
            ->first();
        $this->assertNull($wrongLine, 'Gateway fee gross-up must NOT be posted to MARKUP_INCOME (4132) — that leaf is a distinct concept (sale-margin income).');

        // W4.D: the Dr leg is now RECEIVABLE_CONTROL (Clients) itself, grossing up AR to match
        // what PaymentController::createInvoicePaymentCOA() will later collect -- NOT a gateway
        // asset account (the deleted method's own shape, no longer posted here at all).
        $debitLine = DB::table('journal_entries')
            ->where('account_id', $receivableAccount->id)
            ->where('invoice_detail_id', $invoiceDetail->id)
            ->first();
        $this->assertNotNull($debitLine);
        $this->assertEquals(4.000, (float) $debitLine->debit);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) ON path commission — separate JV, Dr SALARY_EXPENSE / Cr SALARY_PAYABLE (2201),
    //     NEVER the full profit.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_commission_posts_separate_jv_and_never_equals_full_profit(): void
    {
        [$company, , $agent, , $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture(agentTypeId: 2, commissionRate: 0.15);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $controller = app(InvoiceController::class);

        $profit = 100.000;
        $commission = 15.000; // 15% of profit — deliberately NOT equal to $profit

        $this->callPrivate($controller, 'createProfitEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            $profit,
            $commission,
        ]);

        $salaryExpenseId = $this->resolvedAccountId($company, 'SALARY_EXPENSE');
        $salaryPayableId = $this->resolvedAccountId($company, 'SALARY_PAYABLE');
        $this->assertNotNull($salaryExpenseId);
        $this->assertNotNull($salaryPayableId);

        $salaryPayableAccount = Account::find($salaryPayableId);
        $this->assertSame('2201', $salaryPayableAccount->code, 'SALARY_PAYABLE must resolve to the locked 2201 leaf.');

        $lines = DB::table('journal_entries')->where('invoice_detail_id', $invoiceDetail->id)->get();

        // Exactly 2 lines total for this call — the D1 correction means the legacy $profit pair
        // (Dr Agent Salaries / Cr Agent Profit Payable) is NOT posted on the ON path at all.
        $this->assertCount(2, $lines, 'ON path must post ONLY the commission JV — never the case-profit pair too.');

        $debitLine = $lines->firstWhere('account_id', $salaryExpenseId);
        $creditLine = $lines->firstWhere('account_id', $salaryPayableId);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals($commission, (float) $debitLine->debit);
        $this->assertEquals($commission, (float) $creditLine->credit);

        // The direct regression assertion: commission must never equal the full profit.
        $this->assertNotEquals($profit, (float) $debitLine->debit);
        $this->assertNotEquals($profit, (float) $creditLine->credit);

        // No line anywhere for this call carries the full $profit amount.
        $this->assertSame(
            0,
            $lines->filter(fn ($l) => (float) $l->debit === $profit || (float) $l->credit === $profit)->count(),
            'No ON-path line may carry the full case profit — that is exactly the double-counting D1 corrects.'
        );
    }

    public function test_on_path_zero_commission_posts_nothing(): void
    {
        [$company, , $agent, , $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture(agentTypeId: 1, commissionRate: 0.0);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $controller = app(InvoiceController::class);

        $this->callPrivate($controller, 'createProfitEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            100.000, // profit
            0.0,     // commission
        ]);

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('invoice_detail_id', $invoiceDetail->id)->count(),
            'ON path with zero commission must post nothing at all — not even a lopsided profit pair.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (c) OFF path — provably unchanged from legacy, including the preserved double-counting.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_profit_entries_match_legacy_exactly_including_double_counting(): void
    {
        [$company, , $agent, , $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture(agentTypeId: 2, commissionRate: 0.15);
        config(['accounting.engine.enabled' => false]);
        // posting_engine_enabled defaults false — left untouched (OFF path).

        $controller = app(InvoiceController::class);

        $profit = 100.000;
        $commission = 15.000;

        $this->callPrivate($controller, 'createProfitEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            $profit,
            $commission,
        ]);

        $agentSalariesAccount = Account::where('company_id', $company->id)->where('name', 'Agent Salaries')->firstOrFail();

        $lines = DB::table('journal_entries')->where('invoice_detail_id', $invoiceDetail->id)->get();

        // HEAD posts the (buggy, preserved-on-purpose) profit pair UNCONDITIONALLY: this is the
        // diff-based proof that OFF path is untouched — it must NOT match the ON path's 2-line
        // commission-only shape.
        $profitDebitLine = $lines->first(fn ($l) => $l->account_id === $agentSalariesAccount->id && (float) $l->debit === $profit);
        $this->assertNotNull($profitDebitLine, 'OFF path must still post the legacy $profit pair (Dr Agent Salaries) — HEAD parity, not the D1 fix.');

        // Commission pair also still posted (legacy double-counts on purpose, unchanged).
        $commissionExpenseAccount = Account::where('company_id', $company->id)
            ->where('name', 'like', 'Commissions Expense (Agents)%')
            ->first();

        if ($commissionExpenseAccount) {
            $commissionDebitLine = $lines->first(fn ($l) => $l->account_id === $commissionExpenseAccount->id && (float) $l->debit === $commission);
            $this->assertNotNull($commissionDebitLine, 'OFF path must still post the legacy commission pair alongside the profit pair (the preserved double-count).');
        }

        // The engine's own purpose-code accounts must NEVER be touched on the OFF path.
        $salaryPayableId = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SALARY_PAYABLE')
            ->value('account_id');

        if ($salaryPayableId !== null) {
            $this->assertSame(
                0,
                $lines->where('account_id', $salaryPayableId)->count(),
                'OFF path must never write to the engine SALARY_PAYABLE leaf — that is an ON-path-only account.'
            );
        }
    }

    public function test_off_path_sale_entries_match_legacy_shape(): void
    {
        [$company, , $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture();
        config(['accounting.engine.enabled' => false]);

        $controller = app(InvoiceController::class);

        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            250.000,
            $client->full_name,
        ]);

        $this->assertNull($result, 'OFF path success must return null, matching every other legacy closure convention in this lane.');

        $clientsAccount = Account::where('company_id', $company->id)->where('name', 'Clients')->firstOrFail();
        $revenueAccount = Account::where('company_id', $company->id)->where('name', 'Hotel Booking Revenue')->firstOrFail();

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('account_id', $clientsAccount->id);
        $creditLine = $lines->firstWhere('account_id', $revenueAccount->id);

        $this->assertNotNull($debitLine, 'OFF path must debit the legacy "Clients" leaf by name, exactly as HEAD does.');
        $this->assertNotNull($creditLine, 'OFF path must credit the legacy "{Type} Booking Revenue" leaf by name, exactly as HEAD does.');
        $this->assertEquals(250.000, (float) $debitLine->debit);
        $this->assertEquals(250.000, (float) $creditLine->credit);
        $this->assertSame('receivable', $debitLine->type);
        $this->assertSame('income', $creditLine->type);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (d) InvoiceController / ChatController parity — w3d-brief.md decision 1: both sale feeders
    //     now build their LineDraft array from the SAME App\Services\Accounting\SaleDraftBuilder,
    //     so the same economic inputs (sell/cost/service type) must produce the same shape.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Same sell/cost/service-type input posted through BOTH controllers must produce the same
     * per-purpose-code amounts — proof the two feeders share one builder rather than two
     * hand-rolled copies that can silently diverge again.
     */
    public function test_invoice_controller_and_chat_controller_sale_drafts_have_parity(): void
    {
        [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, $transaction] = $this->makeFixture(taskType: 'flight', taskTotal: 100.000);
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // (1) InvoiceController::postSaleJournalEntries — sell 130, cost 100 (pinned) -> margin 30.
        $invoiceController = app(InvoiceController::class);
        $invoiceResult = $this->callPrivate($invoiceController, 'postSaleJournalEntries', [
            $transaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            130.000,
            $client->full_name,
        ]);
        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $invoiceResult);

        // (2) ChatController::postChatInvoiceTaskEntries — same 130/100/flight economics, via a
        // SEPARATE company/task/invoice-detail (its own idempotency key is per invoice-detail).
        $chatCompany = Company::factory()->create();
        CoaSeeder::run($chatCompany->id);
        $chatBranch = Branch::factory()->create(['company_id' => $chatCompany->id, 'user_id' => User::factory()->create()->id]);
        $chatAgentType = AgentType::firstOrCreate(['name' => 'Sales']);
        $chatAgent = Agent::factory()->create(['branch_id' => $chatBranch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $chatAgentType->id]);
        $chatClient = Client::factory()->create(['agent_id' => $chatAgent->id]);
        $chatSupplier = Supplier::factory()->create();
        $chatInvoice = Invoice::factory()->create(['agent_id' => $chatAgent->id, 'client_id' => $chatClient->id]);
        $chatTask = Task::factory()->create([
            'company_id' => $chatCompany->id,
            'agent_id' => $chatAgent->id,
            'client_id' => $chatClient->id,
            'supplier_id' => $chatSupplier->id,
            'type' => 'flight',
            'total' => 100.000,
        ]);
        $chatInvoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $chatInvoice->id,
            'task_id' => $chatTask->id,
            'task_price' => 130.000,
            'supplier_price' => 100.000,
            'markup_price' => 30.000,
        ]);
        $chatTaskPayload = $chatTask;
        $chatTaskPayload->invprice = 130.000;

        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $chatCompany->id, '--enable' => true]);
        $this->trackCompanyForInvariants($chatCompany->id);

        $legacyStandIn = Account::factory()->create(['company_id' => $chatCompany->id]);

        $chatController = app(\App\Http\Controllers\ChatController::class);
        $chatResult = $chatController->postChatInvoiceTaskEntries(
            $chatTaskPayload,
            $chatTask->fresh(),
            $chatSupplier,
            $chatClient,
            $chatAgent,
            $chatInvoice,
            $chatInvoice->invoice_number,
            $chatInvoiceDetail,
            $legacyStandIn,
            $legacyStandIn,
            $legacyStandIn,
            $chatCompany->id,
            $chatBranch->id
        );
        $this->assertInstanceOf(\App\Services\Accounting\PostedDocument::class, $chatResult);

        // Parity assertion: both documents post the SAME 3-line shape with the SAME amounts —
        // Dr receivable 130 / Cr payable(flight) 100 / Cr margin(flight) 30 — even though one
        // came from the GDS/task auto-invoicing path and the other from the AI-chat path.
        $invoiceLines = DB::table('journal_entries')->where('transaction_id', $invoiceResult->transaction->id)->get();
        $chatLines = DB::table('journal_entries')->where('transaction_id', $chatResult->transaction->id)->get();

        $this->assertCount(3, $invoiceLines);
        $this->assertCount(3, $chatLines);

        $invoiceShape = collect($invoiceLines)->map(fn ($l) => round((float) $l->debit, 3).'/'.round((float) $l->credit, 3))->sort()->values()->all();
        $chatShape = collect($chatLines)->map(fn ($l) => round((float) $l->debit, 3).'/'.round((float) $l->credit, 3))->sort()->values()->all();

        $this->assertSame(
            $invoiceShape,
            $chatShape,
            'InvoiceController and ChatController must post the SAME set of (debit, credit) amounts for the same sell/cost/service-type input — proof both share SaleDraftBuilder.'
        );
        // Lexicographic string sort, not numeric: '0/100' < '0/30' (char-by-char, '1' < '3').
        $this->assertSame(['0/100', '0/30', '130/0'], $invoiceShape, 'Expected shape: Dr receivable 130 / Cr payable 100 / Cr margin 30.');
    }
}
