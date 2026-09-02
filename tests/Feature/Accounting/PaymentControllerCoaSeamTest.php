<?php

namespace Tests\Feature\Accounting;

use App\Enums\ChargeType;
use App\Exceptions\Accounting\PostingException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Http\Controllers\PaymentController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\PaymentIdempotencyKey;
use Carbon\Carbon;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * KEY: coa-seam (B1). Cuts PaymentController::createInvoicePaymentCOA() onto {@see
 * \App\Services\Accounting\PostingSeam} (R3 route-to-legacy decision). Read that method in full
 * before touching this file.
 *
 * OFF path (test_off_path_*): the legacy closure is HEAD's own body, byte-identical in every
 * row/value/order it can produce — proven against LedgerDerivedBalanceCallSitesTest and
 * PaymentControllerHotfixTest (both untouched, both still green — see the coa-seam report) plus
 * the two parity tests here. Uses plain CreatesTenantFixtures (no CoaSeeder), matching those two
 * existing tests' own convention for the OFF path.
 *
 * ON path (test_on_path_*): extends AccountingTestCase for the C1 trial-balance invariant.
 * REAL CoaSeeder::run() + SystemAccountsSeeder::run() are used for every ON-path test, never a
 * hand-inserted system_accounts row.
 */
class PaymentControllerCoaSeamTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function invokePrivate(object $object, string $method, array $args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @return array{0: Invoice, 1: InvoiceDetail, 2: Client}
     */
    private function makeInvoice(array $tenant, float $taskPrice = 100.00): array
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => $taskPrice,
            'sub_amount' => $taskPrice,
        ]);
        $task = Task::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
        ]);
        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_price' => $taskPrice,
            // Pinned EQUAL to $taskPrice (InvoiceDetailFactory's own defaults are random
            // 5-500 / 0-100) so margin = task_price - supplier_price = 0, and every profit/
            // loss-sharing branch in InvoiceController::recalculateInvoiceCOA() computes a
            // zero amount and is skipped by updateOrCreateEntryByAccount()'s own `elseif
            // ($amount > 0)` guard — that unrelated recompute step (which HEAD already calls
            // unconditionally, unchanged by this seam cutover) must add NO extra
            // journal_entries row onto this same transaction_id, so this test can assert the
            // ENGINE's own document in isolation.
            'supplier_price' => $taskPrice,
            'markup_price' => 0,
        ]);

        return [$invoice, $invoiceDetail, $tenant['client']];
    }

    /**
     * Charge row for $gatewayName with a deterministic Flat Rate accounting fee. Matches
     * LedgerDerivedBalanceCallSitesTest's own convention (no payment_method_id on the Payment
     * below, so ChargeService::calculate() falls through to this Charge row rather than a
     * random PaymentMethod).
     */
    private function makeCharge(Company $company, string $gatewayName, Account $assetAccount, Account $expenseAccount, float $selfCharge): Charge
    {
        return Charge::create([
            'name' => $gatewayName,
            'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => $selfCharge,
            'charge_type' => 'Flat Rate',
            'self_charge' => $selfCharge,
            'extra_charge' => 0,
            'paid_by' => 'Company',
            'company_id' => $company->id,
            'acc_fee_bank_id' => $assetAccount->id,
            'acc_fee_id' => $expenseAccount->id,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path — HEAD parity.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_no_partials_matches_head_shape(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);

        Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayAsset = Account::create(['name' => 'Gateway Asset', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayExpense = Account::create(['name' => 'Gateway Expense', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $this->makeCharge($company, 'MyFatoorah', $gatewayAsset, $gatewayExpense, 5.00);

        $payment = Payment::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_method_id' => null,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-OFF-1',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $transaction = Transaction::find($result['transaction_id']);
        $this->assertSame('Invoice', $transaction->reference_type);
        $this->assertSame($payment->id, $transaction->payment_id);
        $this->assertNull($transaction->idempotency_key);

        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(3, $entries);
        $this->assertEqualsWithDelta(100.00, (float) $entries->sum('credit'), 0.001);
        $this->assertEqualsWithDelta(100.00, (float) $entries->sum('debit'), 0.001);

        $receivableLine = $entries->firstWhere('type', 'receivable');
        $this->assertNotNull($receivableLine);
        $this->assertEqualsWithDelta(100.00, (float) $receivableLine->credit, 0.001);

        $feeLine = $entries->firstWhere('type', 'charges');
        $this->assertNotNull($feeLine);
        $this->assertEqualsWithDelta(5.00, (float) $feeLine->debit, 0.001);

        $this->assertEqualsWithDelta(95.00, (float) $gatewayAsset->fresh()->actual_balance, 0.001);
        $this->assertEqualsWithDelta(5.00, (float) $gatewayExpense->fresh()->actual_balance, 0.001);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_off_path_with_two_partials_matches_head_shape(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);

        Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayAsset = Account::create(['name' => 'Gateway Asset', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayExpense = Account::create(['name' => 'Gateway Expense', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $this->makeCharge($company, 'MyFatoorah', $gatewayAsset, $gatewayExpense, 5.00);

        $partial1 = InvoicePartial::create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id, 'amount' => 50.00, 'status' => 'unpaid', 'service_charge' => 0,
            'expiry_date' => now()->addDays(7), 'type' => 'split', 'payment_gateway' => 'MyFatoorah',
        ]);
        $partial2 = InvoicePartial::create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id, 'amount' => 50.00, 'status' => 'unpaid', 'service_charge' => 0,
            'expiry_date' => now()->addDays(7), 'type' => 'split', 'payment_gateway' => 'MyFatoorah',
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_method_id' => null,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', [$partial1->id, $partial2->id], 'REF-OFF-2',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');
        $this->assertSame('paid', $partial1->fresh()->status);
        $this->assertSame('paid', $partial2->fresh()->status);
        $this->assertSame($payment->id, $partial1->fresh()->payment_id);
        $this->assertSame('paid', $invoice->fresh()->status);

        $entries = JournalEntry::where('transaction_id', $result['transaction_id'])->get();
        $this->assertCount(3, $entries);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path — balanced document via purpose codes, correct leaves, idempotent.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @return array{company: Company, branch: Branch, agent: Agent, client: Client}
     */
    private function makeOnPathTenant(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return compact('company', 'branch', 'agent', 'client');
    }

    private function resolvedAccountId(Company $company, string $purposeCode): ?int
    {
        return DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', $purposeCode)
            ->value('account_id');
    }

    public function test_on_path_posts_a_balanced_document_via_the_correct_purpose_codes(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);

        // MyFatoorah's fee-expense child ('MyFatoorah Charges', 5142) is one of the three
        // CoaSeeder already seeds under 'Payment Gateway Charges' (5140) — see
        // SystemAccountsSeeder::resolveGatewayFeeExpense().
        Charge::create([
            'name' => 'MyFatoorah',
            'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 5.00,
            'charge_type' => 'Flat Rate',
            'self_charge' => 5.00,
            'extra_charge' => 0,
            'paid_by' => 'Company',
            'company_id' => $company->id,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenant['agent']->user_id,
            'payment_method_id' => null,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        $receivableLeaf = $this->resolvedAccountId($company, 'RECEIVABLE_CONTROL');
        $clearingLeaf = $this->resolvedAccountId($company, 'GATEWAY_CLEARING_MYFATOORAH');
        $feeLeaf = $this->resolvedAccountId($company, 'GATEWAY_FEE_EXPENSE_MYFATOORAH');
        $this->assertNotNull($receivableLeaf, 'RECEIVABLE_CONTROL must be mapped for this test to be meaningful');
        $this->assertNotNull($clearingLeaf, 'GATEWAY_CLEARING_MYFATOORAH must be mapped for this test to be meaningful');
        $this->assertNotNull($feeLeaf, 'GATEWAY_FEE_EXPENSE_MYFATOORAH must be mapped for this test to be meaningful');

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-ON-1',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $transaction = Transaction::find($result['transaction_id']);
        $expectedKey = PaymentIdempotencyKey::forGatewayPayment('MyFatoorah', $payment->id, null);
        $this->assertSame($expectedKey, $transaction->idempotency_key);
        $this->assertSame($payment->id, $transaction->payment_id);
        $this->assertSame($invoice->id, $transaction->invoice_id);

        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(3, $entries);
        $this->assertEqualsWithDelta((float) $entries->sum('debit'), (float) $entries->sum('credit'), 0.0005);
        $this->assertEqualsWithDelta(100.00, (float) $entries->sum('credit'), 0.001);

        $this->assertTrue($entries->pluck('account_id')->contains($receivableLeaf));
        $this->assertTrue($entries->pluck('account_id')->contains($clearingLeaf));
        $this->assertTrue($entries->pluck('account_id')->contains($feeLeaf));

        $receivableLine = $entries->firstWhere('account_id', $receivableLeaf);
        $this->assertSame($client->id, $receivableLine->type_reference_id);
        $this->assertSame($client->full_name, $receivableLine->name);
        $this->assertEqualsWithDelta(100.00, (float) $receivableLine->credit, 0.001);

        $feeLine = $entries->firstWhere('account_id', $feeLeaf);
        $this->assertEqualsWithDelta(5.00, (float) $feeLine->debit, 0.001);

        // BLOCKER 4 (engine contract): journal_entries.balance is never read-modify-write on
        // the ON path.
        $this->assertNull($receivableLine->balance);
    }

    /**
     * Residual 3 fix (W2.1). createInvoicePaymentCOA() calls
     * InvoiceController::recalculateInvoiceCOA() unconditionally after the seam post, inside
     * the SAME outer transaction, for both the ON and OFF path. Before the fix,
     * recalculateInvoiceCOA()'s own target lookup (`JournalEntry::where('invoice_id', ...)
     * ->value('transaction_id')`, no idempotency_key exclusion) matched the ENGINE's own
     * just-posted lines -- every LineDraft the ON path builds sets invoiceId -- whenever no
     * OTHER invoice_id-tagged transaction already existed, the realistic case for a fresh
     * invoice's very first payment, and appended a one-sided profit-share debit onto it. The
     * committed header (total_debit/total_credit, stamped by the engine) then contradicted
     * its own lines' sums. Every OTHER ON-path test in this file deliberately pins margin = 0
     * (see makeInvoice()'s own comment) specifically so this never triggers; this test uses a
     * genuinely marked-up invoice (task_price 100 / supplier_price 70 -> margin 30) to
     * exercise it. Reverting the fix turns this test red: the naive query still resolves to
     * the engine's own transaction_id (every one of its lines carries the same transaction_id,
     * so row order can't save it), the profit-share line lands there, and the engine
     * transaction becomes 130/100 -- no longer balanced.
     */
    public function test_on_path_marked_up_invoice_keeps_engine_header_balanced(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $client = $tenant['client'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $tenant['agent']->id,
        ]);
        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_price' => 100.00,
            // Deliberately UNEQUAL to task_price (unlike makeInvoice()'s pinned margin = 0)
            // so recalculateInvoiceCOA() computes a genuine non-zero profit and would write a
            // real "Agent profit share" debit line if it resolved a target for it.
            'supplier_price' => 70.00,
            'markup_price' => 30.00,
        ]);

        Charge::create([
            'name' => 'MyFatoorah', 'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 5.00, 'charge_type' => 'Flat Rate', 'self_charge' => 5.00,
            'extra_charge' => 0, 'paid_by' => 'Company', 'company_id' => $company->id,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id, 'invoice_id' => $invoice->id, 'account_id' => null,
            'created_by' => $tenant['agent']->user_id, 'payment_method_id' => null,
            'amount' => 100.00, 'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-ON-MARKUP',
        ]);
        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $engineTransaction = Transaction::find($result['transaction_id']);
        $this->assertNotNull($engineTransaction->idempotency_key, 'the returned transaction must be the engine-owned document');

        $engineLines = JournalEntry::where('transaction_id', $engineTransaction->id)->get();
        $this->assertEqualsWithDelta(
            (float) $engineTransaction->total_debit,
            (float) $engineLines->sum('debit'),
            0.0005,
            'engine header total_debit must equal the sum of its own lines'
        );
        $this->assertEqualsWithDelta(
            (float) $engineTransaction->total_credit,
            (float) $engineLines->sum('credit'),
            0.0005,
            'engine header total_credit must equal the sum of its own lines'
        );
        $this->assertEqualsWithDelta(
            (float) $engineLines->sum('debit'),
            (float) $engineLines->sum('credit'),
            0.0005,
            'the engine document itself must stay balanced'
        );

        // With no pre-existing (legacy) invoice_id-tagged transaction, the fixed target
        // lookup excludes the engine's own transaction and resolves to nothing, so
        // recalculateInvoiceCOA() correctly no-ops rather than corrupting the engine
        // document -- no "Agent profit share" line exists anywhere for this margin-30
        // invoice.
        $profitLine = JournalEntry::where('invoice_id', $invoice->id)
            ->where('description', 'like', 'Agent profit share:%')
            ->first();
        $this->assertNull(
            $profitLine,
            'with no pre-existing legacy invoice transaction, the fixed target lookup must resolve to nothing and no-op, never append to the engine document'
        );
    }

    public function test_on_path_second_call_same_partials_is_a_no_op(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);
        Charge::create([
            'name' => 'MyFatoorah', 'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 5.00, 'charge_type' => 'Flat Rate', 'self_charge' => 5.00,
            'extra_charge' => 0, 'paid_by' => 'Company', 'company_id' => $company->id,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id, 'invoice_id' => $invoice->id, 'account_id' => null,
            'created_by' => $tenant['agent']->user_id, 'payment_method_id' => null,
            'amount' => 100.00, 'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $first = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-ON-2',
        ]);
        $this->assertTrue($first['success'] ?? false, $first['message'] ?? 'unexpected failure');

        $second = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-ON-2',
        ]);
        $this->assertTrue($second['success'] ?? false, $second['message'] ?? 'unexpected failure');
        $this->assertSame($first['transaction_id'], $second['transaction_id']);

        $key = PaymentIdempotencyKey::forGatewayPayment('MyFatoorah', $payment->id, null);
        $this->assertSame(1, Transaction::where('company_id', $company->id)->where('idempotency_key', $key)->count());
        $this->assertSame(3, JournalEntry::where('transaction_id', $first['transaction_id'])->count());
    }

    /**
     * Two DISTINCT Payment rows (the realistic shape: each gateway transaction has its own
     * Payment record) each settling a different partial of the SAME invoice — proves the
     * idempotency key genuinely varies by partial set (D2), producing two independent
     * documents. NOT the same Payment row called twice with two different partial sets: D3's
     * own `(payment_id, reference_type)` unique index deliberately allows only ONE 'Receipt'
     * document per payment_id (see DuplicatePaymentReferenceException) — a second call on the
     * SAME payment_id with a different key is correctly refused as "a different document than
     * the one being posted, not a retry of it", proven separately below by the fact that this
     * test's two calls use two different payments and therefore never collide.
     */
    public function test_on_path_different_partial_set_creates_a_second_document(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);
        Charge::create([
            'name' => 'MyFatoorah', 'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 2.50, 'charge_type' => 'Flat Rate', 'self_charge' => 2.50,
            'extra_charge' => 0, 'paid_by' => 'Company', 'company_id' => $company->id,
        ]);

        $partial1 = InvoicePartial::create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id, 'amount' => 50.00, 'status' => 'unpaid', 'service_charge' => 0,
            'expiry_date' => now()->addDays(7), 'type' => 'split', 'payment_gateway' => 'MyFatoorah',
        ]);
        $partial2 = InvoicePartial::create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id, 'amount' => 50.00, 'status' => 'unpaid', 'service_charge' => 0,
            'expiry_date' => now()->addDays(7), 'type' => 'split', 'payment_gateway' => 'MyFatoorah',
        ]);

        $paymentA = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id, 'invoice_id' => $invoice->id, 'account_id' => null,
            'created_by' => $tenant['agent']->user_id, 'payment_method_id' => null,
            'amount' => 50.00, 'status' => 'completed',
        ]);
        $paymentB = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id, 'invoice_id' => $invoice->id, 'account_id' => null,
            'created_by' => $tenant['agent']->user_id, 'payment_method_id' => null,
            'amount' => 50.00, 'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $first = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $paymentA, 50.00, 'MyFatoorah', [$partial1->id], 'REF-ON-3a',
        ]);
        $this->assertTrue($first['success'] ?? false, $first['message'] ?? 'unexpected failure');

        $second = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $paymentB, 50.00, 'MyFatoorah', [$partial2->id], 'REF-ON-3b',
        ]);
        $this->assertTrue($second['success'] ?? false, $second['message'] ?? 'unexpected failure');

        $this->assertNotSame($first['transaction_id'], $second['transaction_id']);

        $keyA = PaymentIdempotencyKey::forGatewayPayment('MyFatoorah', $paymentA->id, [$partial1->id]);
        $keyB = PaymentIdempotencyKey::forGatewayPayment('MyFatoorah', $paymentB->id, [$partial2->id]);
        $this->assertNotSame($keyA, $keyB);
        $this->assertSame($keyA, Transaction::find($first['transaction_id'])->idempotency_key);
        $this->assertSame($keyB, Transaction::find($second['transaction_id'])->idempotency_key);
        $this->assertSame(2, Transaction::where('company_id', $company->id)->whereIn('payment_id', [$paymentA->id, $paymentB->id])->count());
    }

    /**
     * D3's `(payment_id, reference_type)` unique index deliberately allows only ONE live
     * 'Receipt' document per payment_id: a SECOND call on the SAME payment with a genuinely
     * different idempotency key (a different partial set) is not a retry of the first, and
     * PostingService correctly refuses it as a DuplicatePaymentReferenceException rather than
     * silently posting a second document that would leave `transactions.payment_id` pointing
     * at whichever one committed last.
     *
     * TRANSACTIONS-CUTOVER SPLIT: this unique index is now scoped to POST-CUTOVER rows only
     * (payment_ref_dedup_key is NULL, and never collides, for any row with created_at before
     * 2026-09-01 00:00:00 — see migration
     * 2026_08_24_000002_add_post_cutover_dedup_key_to_transactions_table.php). Both
     * createInvoicePaymentCOA() calls below are frozen to a fixed post-cutover instant so their
     * header INSERTs actually collide, exactly as this test's own docblock above requires — see
     * PostingServiceRepostPaymentIdTest's equivalent test for the fuller version of this same
     * reasoning (why PeriodGuard's assertOpen() is unaffected by freezing Carbon::now() here).
     */
    public function test_on_path_second_call_same_payment_different_partials_is_refused(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);
        Charge::create([
            'name' => 'MyFatoorah', 'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 2.50, 'charge_type' => 'Flat Rate', 'self_charge' => 2.50,
            'extra_charge' => 0, 'paid_by' => 'Company', 'company_id' => $company->id,
        ]);

        $partial1 = InvoicePartial::create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id, 'amount' => 50.00, 'status' => 'unpaid', 'service_charge' => 0,
            'expiry_date' => now()->addDays(7), 'type' => 'split', 'payment_gateway' => 'MyFatoorah',
        ]);
        $partial2 = InvoicePartial::create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id, 'amount' => 50.00, 'status' => 'unpaid', 'service_charge' => 0,
            'expiry_date' => now()->addDays(7), 'type' => 'split', 'payment_gateway' => 'MyFatoorah',
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id, 'invoice_id' => $invoice->id, 'account_id' => null,
            'created_by' => $tenant['agent']->user_id, 'payment_method_id' => null,
            'amount' => 50.00, 'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);

        Carbon::setTestNow(Carbon::parse('2026-09-02 00:00:00'));

        try {
            $first = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
                $payment, 50.00, 'MyFatoorah', [$partial1->id], 'REF-ON-3c',
            ]);
            $this->assertTrue($first['success'] ?? false, $first['message'] ?? 'unexpected failure');

            try {
                $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
                    $payment, 50.00, 'MyFatoorah', [$partial2->id], 'REF-ON-3d',
                ]);
                $this->fail('Expected a DuplicatePaymentReferenceException to propagate.');
            } catch (PostingException $e) {
                $this->assertInstanceOf(\App\Exceptions\Accounting\DuplicatePaymentReferenceException::class, $e);
            }
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(1, Transaction::where('company_id', $company->id)->where('payment_id', $payment->id)->count());
    }

    public function test_on_path_zero_fee_gateway_omits_the_third_line(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);
        // Deliberately NO Charge row and NO PaymentMethod for MyFatoorah — ChargeService::
        // calculate()'s own "no charge configuration found" branch returns accountingFee = 0.

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id, 'invoice_id' => $invoice->id, 'account_id' => null,
            'created_by' => $tenant['agent']->user_id, 'payment_method_id' => null,
            'amount' => 100.00, 'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-ON-4',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $entries = JournalEntry::where('transaction_id', $result['transaction_id'])->get();
        $this->assertCount(2, $entries, 'a zero-fee gateway must post a balanced 2-line document, never a 0-amount third line');
        $this->assertEqualsWithDelta((float) $entries->sum('debit'), (float) $entries->sum('credit'), 0.0005);
        $this->assertEqualsWithDelta(100.00, (float) $entries->sum('credit'), 0.001);
    }

    /**
     * RETARGETED (W2.1, KEY: seeders+leaves, residual 1 BLOCKER fix): this test used to pin the
     * BUG it was written to document — KNET had no per-gateway fee-expense child in the base
     * CoaSeeder chart (only MyFatoorah/Tap/Hesabe did), so GATEWAY_FEE_EXPENSE_KNET was reported
     * as a permanent gap and a non-zero KNET fee always threw UnmappedPurposeException where HEAD
     * (the legacy closure, which resolves the fee account from Charge::acc_fee_id, not from
     * SystemAccountsSeeder) succeeded — an engine-ON regression for 2 of the 5 gateways. Fixed by
     * seeding a dedicated 'KNET Charges' (5144) / 'uPayment Charges' (5145) child under 'Payment
     * Gateway Charges' (5140) for every new company (CoaSeeder), and by making
     * SystemAccountsSeeder::resolveGatewayFeeExpense()'s per-gateway fallback unconditional for a
     * company whose chart pre-dates that (EnsureSystemLeaves backfills the two leaves there). This
     * test now proves the FIX: a non-zero KNET fee posts a real, balanced 3-line document, exactly
     * like the existing MyFatoorah fee test below.
     *
     * Red-on-revert proof (not committed as a test — see the residual 1 report): reverting either
     * half of the fix independently (dropping the CoaSeeder 5144 row, or restoring the old
     * `if ($pool->children()->exists())`-gated fallback) reproduces UnmappedPurposeException here
     * again.
     */
    public function test_on_path_knet_fee_purpose_code_now_maps_and_posts_a_balanced_document(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $knetFeeLeafId = $this->resolvedAccountId($company, 'GATEWAY_FEE_EXPENSE_KNET');
        $this->assertNotNull(
            $knetFeeLeafId,
            'Residual 1 fix: GATEWAY_FEE_EXPENSE_KNET must now be mapped on a freshly-seeded chart.'
        );
        $knetFeeLeaf = Account::find($knetFeeLeafId);
        $this->assertSame('5144', $knetFeeLeaf->code, "PATTERN NAME: 'KNET Charges' must be code 5144.");
        $this->assertSame('KNET Charges', $knetFeeLeaf->name);
        $this->assertSame('Payment Gateway Charges', $knetFeeLeaf->parent->name);

        [$invoice, $invoiceDetail, $client] = $this->makeInvoice($tenant, 100.00);
        Charge::create([
            'name' => 'KNET', 'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 3.00, 'charge_type' => 'Flat Rate', 'self_charge' => 3.00,
            'extra_charge' => 0, 'paid_by' => 'Company', 'company_id' => $company->id,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $tenant['agent']->id,
            'client_id' => $client->id, 'invoice_id' => $invoice->id, 'account_id' => null,
            'created_by' => $tenant['agent']->user_id, 'payment_method_id' => null,
            'amount' => 100.00, 'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);
        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'KNET', null, 'REF-ON-5',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $entries = JournalEntry::where('transaction_id', $result['transaction_id'])->get();
        $this->assertCount(3, $entries, 'a non-zero KNET fee must post a balanced 3-line document (receivable / gateway clearing / gateway fee expense).');
        $this->assertEqualsWithDelta((float) $entries->sum('debit'), (float) $entries->sum('credit'), 0.0005);
        $this->assertEqualsWithDelta(100.00, (float) $entries->sum('credit'), 0.001);

        $feeLine = $entries->firstWhere('account_id', $knetFeeLeafId);
        $this->assertNotNull($feeLine, 'The fee leg must land on the resolved GATEWAY_FEE_EXPENSE_KNET leaf (5144).');
        $this->assertEqualsWithDelta(3.00, (float) $feeLine->debit, 0.001);

        $this->assertSame('paid', $invoice->fresh()->status);
    }
}
