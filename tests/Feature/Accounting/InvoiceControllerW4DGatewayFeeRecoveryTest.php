<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\InvoiceController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Payment;
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
 * KEY: invoice-w4d. W4.D (.planning/accounting-waves/w4/w4-brief.md item 3; target-spec.md §B) —
 * FIX ROUND 2, against the independent verifier's BLOCKING/MAJOR findings on the first cut:
 *
 *   1. BLOCKING — an un-deleted inline duplicate of createGatewayProfitEntries()'s own
 *      double-booking bug lived in InvoiceController::recalculateInvoiceCOA() (its "Gateway
 *      profit entries" block), unconditionally reachable from EVERY real gateway payment via
 *      PaymentController::createInvoicePaymentCOA()'s "Recalculate profit after each payment"
 *      call site. Deleted whole (no replacement posting there).
 *   2. MAJOR — the first cut's createGatewayFeeRecoveryEntries() posted only from FIVE
 *      invoice-creation/edit call sites, all deriving their figures from
 *      `$invoice->invoicePartials`, which is EMPTY until a payment exists — so the mechanism was
 *      dead for a typical invoice. All five call sites are deleted; the method now posts from
 *      exactly one place, PaymentController::createInvoicePaymentCOA(), the real collection-time
 *      feeder, per Accounting Gap/22-plan-amendments.md rev 3 §4.1 gateway_fee row / ruling B10
 *      ("a DBN/FEE_RECOVERY document dated the payment").
 *   3. MAJOR — test coverage gap: the first cut's tests invoked the private method directly via
 *      reflection with a hand-fed Charge object, never exercising the real call path. This file's
 *      tests below instead reflect into the PRIVATE `PaymentController::createInvoicePaymentCOA()`
 *      itself — the actual, only production call path for both defects above — so a regression of
 *      either defect fails these tests.
 *
 * `InvoiceControllerProfitLossPostingTest::
 * test_on_path_gateway_markup_credits_gateway_fee_recovery_4131_not_markup_income()` covers the
 * new method's own posting shape (purpose codes, balance) in isolation via direct reflection; this
 * file covers the WIRING — that the real payment-collection flow actually calls it, dates it to
 * the payment, gates it on the real resolved `paid_by`, and that the duplicate booking in
 * recalculateInvoiceCOA() is gone.
 */
class InvoiceControllerW4DGatewayFeeRecoveryTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * Builds a company/agent/client/task/invoice fixture PLUS a legacy-shaped "sale" transaction
     * (Transaction with NO idempotency_key, one JournalEntry row linking it to the invoice) so
     * that InvoiceController::recalculateInvoiceCOA()'s own guard
     * (`whereNull('transactions.idempotency_key')`) finds something to process — otherwise it
     * returns early and the "no more duplicate gateway-profit posting" assertion would be
     * vacuously true for the wrong reason (the loop never ran at all).
     *
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Task, 5: Invoice, 6: InvoiceDetail}
     */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
        ]);

        // recalculateInvoiceCOA()'s "Profit entries" step posts Dr Agent Salaries UNCONDITIONALLY
        // and Cr agent->profit_account_id only when it is set -- without it the profit pair is a
        // deliberately one-sided legacy write (documented pre-existing behaviour, not something
        // this fix round touches) and the fixture's own transaction would never balance, tripping
        // AccountingInvariants for a reason unrelated to this test's actual assertions. CoaSeeder
        // always seeds "Agent Profit Payable" (2230).
        $agentProfitPayable = Account::where('company_id', $company->id)->where('name', 'Agent Profit Payable')->first();
        if ($agentProfitPayable) {
            $agent->profit_account_id = $agentProfitPayable->id;
            $agent->save();
        }

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(2), // distinct from the payment date below
        ]);

        // invoice_number MUST match the invoice's own — createInvoicePaymentCOA() looks this
        // InvoiceDetail up by `InvoiceDetail::where('invoice_number', $invoice->invoice_number)`
        // (InvoiceDetailFactory's own default is a DIFFERENT random value).
        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_price' => 100.000,
            'supplier_price' => 60.000,
        ]);

        $clientsAccount = Account::where('company_id', $company->id)->where('name', 'Clients')->firstOrFail();
        $revenueAccount = Account::where('company_id', $company->id)->where('name', 'Hotel Booking Revenue')->firstOrFail();

        // Legacy-shaped "sale" transaction — no idempotency_key, matching what a company still on
        // the OFF path would have from InvoiceController::addJournalEntry()'s own legacy body.
        $saleTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 100.000,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);

        JournalEntry::create([
            'transaction_id' => $saleTransaction->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $clientsAccount->id,
            'task_id' => $task->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => 'Invoice amount for '.$task->reference,
            'debit' => 100.000,
            'credit' => 0,
            'balance' => 0,
            'name' => $client->full_name,
            'type' => 'receivable',
            'currency' => 'KWD',
            'exchange_rate' => 1.00,
            'amount' => 100.000,
        ]);

        JournalEntry::create([
            'transaction_id' => $saleTransaction->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $revenueAccount->id,
            'task_id' => $task->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => 'Invoice created for (Income): '.$task->reference,
            'debit' => 0,
            'credit' => 100.000,
            'balance' => 0,
            'name' => $revenueAccount->name,
            'type' => 'income',
            'currency' => 'KWD',
            'exchange_rate' => 1.00,
            'amount' => 100.000,
        ]);

        return [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail];
    }

    private function makeClientBorneCharge(Company $company, string $gatewayName): Charge
    {
        // Percent charge with markup AND a non-whole percentage (so rounding_profit > 0):
        // contract (API cost) = 2% => 2.000 on a 100 base; self_charge (API+markup) = 3.3% =>
        // 3.300 ceil'd to 4.000 (rounding_profit = 0.700); extra_charge flat 0.500.
        // accounting_fee = 2.000 + 0.500 = 2.500; markup_profit = (3.3-2)/100*100 = 1.300.
        // grossUpAmount = 2.500 + 1.300 + 0.700 = 4.500 == total_charge (4.000 + 0.500) — the two
        // must agree, since accountingFee+markup+rounding is just total_charge decomposed.
        //
        // acc_fee_bank_id/acc_fee_id: createInvoicePaymentCOA()'s OWN legacy closure (unrelated to
        // this lane) resolves these via Account::find() and throws "One or more required financial
        // accounts not found" if either is unset -- any real Account row satisfies that pre-existing
        // check; the actual amount posted there is irrelevant to what THIS test asserts.
        return Charge::create([
            'company_id' => $company->id,
            'name' => $gatewayName,
            'type' => 'Payment Gateway',
            'paid_by' => 'Client',
            'amount' => 2.000,
            'charge_type' => 'Percent',
            'self_charge' => 3.300,
            'extra_charge' => 0.500,
            'is_active' => true,
            'acc_fee_bank_id' => Account::factory()->create(['company_id' => $company->id])->id,
            'acc_fee_id' => Account::factory()->create(['company_id' => $company->id])->id,
        ]);
    }

    private function makeCompanyBorneCharge(Company $company, string $gatewayName): Charge
    {
        return Charge::create([
            'company_id' => $company->id,
            'name' => $gatewayName,
            'type' => 'Payment Gateway',
            'acc_fee_bank_id' => Account::factory()->create(['company_id' => $company->id])->id,
            'acc_fee_id' => Account::factory()->create(['company_id' => $company->id])->id,
            'paid_by' => 'Company',
            'amount' => 2.000,
            'charge_type' => 'Percent',
            'self_charge' => 2.000,
            'extra_charge' => 0,
            'is_active' => true,
        ]);
    }

    private function makePayment(Company $company, Agent $agent, Client $client, Invoice $invoice, string $gatewayName): Payment
    {
        return Payment::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => null, // factory default (1) has no matching row for this fixture's company
            'created_by' => $agent->user_id, // factory default (1) has no matching row for this fixture
            'amount' => 100.000,
            'payment_gateway' => $gatewayName,
            'payment_method_id' => null,
            'status' => 'pending',
        ]);
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (0) Symbols.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_no_createGatewayProfitEntries_symbol_remains_and_no_edit_time_call_sites(): void
    {
        $this->assertFalse(
            method_exists(InvoiceController::class, 'createGatewayProfitEntries'),
            'createGatewayProfitEntries() must be deleted whole per W4.D.'
        );

        $this->assertTrue(
            method_exists(InvoiceController::class, 'createGatewayFeeRecoveryEntries'),
            'Its replacement, createGatewayFeeRecoveryEntries(), must exist.'
        );

        $source = file_get_contents(app_path('Http/Controllers/InvoiceController.php'));

        $this->assertStringNotContainsString(
            '$this->createGatewayProfitEntries(',
            $source,
            'No call site may invoke the deleted method.'
        );

        // Fix round 2: the gross-up must NOT be called from any invoice-creation/edit site — per
        // Accounting Gap/22-plan-amendments.md rev 3 §4.1 gateway_fee row / ruling B10, it "cannot
        // post with the invoice". It must post from exactly one call site, inside
        // PaymentController::createInvoicePaymentCOA().
        $this->assertSame(
            0,
            substr_count($source, '$this->createGatewayFeeRecoveryEntries('),
            'createGatewayFeeRecoveryEntries() must have NO call sites left inside InvoiceController itself — it is only ever called from PaymentController::createInvoicePaymentCOA(), via app(InvoiceController::class).'
        );

        $paymentControllerSource = file_get_contents(app_path('Http/Controllers/PaymentController.php'));
        $this->assertSame(
            1,
            substr_count($paymentControllerSource, '->createGatewayFeeRecoveryEntries('),
            'PaymentController::createInvoicePaymentCOA() must call createGatewayFeeRecoveryEntries() exactly once.'
        );
    }

    public function test_recalculateInvoiceCOA_no_longer_double_books_gateway_profit(): void
    {
        [$company, , $agent, , , $invoice] = $this->makeFixture();
        config(['accounting.engine.enabled' => false]);
        $this->trackCompanyForInvariants($company->id);

        $controller = app(InvoiceController::class);
        $controller->recalculateInvoiceCOA($invoice);

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('invoice_id', $invoice->id)->where('description', 'like', 'Gateway profit on%')->count(),
            'recalculateInvoiceCOA() must never again post the deleted double-booking pair ("Gateway profit on ..."), even when called directly (e.g. from PaymentController on every payment).'
        );

        // Sanity: recalculateInvoiceCOA() actually ran its per-detail loop (not a vacuous pass —
        // it must have written at least the profit/commission entries it always writes).
        $this->assertGreaterThan(
            0,
            DB::table('journal_entries')->where('invoice_id', $invoice->id)->count(),
            'Fixture sanity: recalculateInvoiceCOA() must have processed the invoice detail (the guard at its own top must have found the legacy sale transaction this fixture seeded).'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (a) OFF path — real collection flow via PaymentController::createInvoicePaymentCOA().
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_client_borne_payment_posts_dated_gross_up_and_no_duplicate_gateway_profit(): void
    {
        [$company, $branch, $agent, $client, , $invoice, $invoiceDetail] = $this->makeFixture();
        config(['accounting.engine.enabled' => false]);
        $this->trackCompanyForInvariants($company->id);

        $gatewayName = 'W4DGateway';
        $this->makeClientBorneCharge($company, $gatewayName);
        $payment = $this->makePayment($company, $agent, $client, $invoice, $gatewayName);

        $paymentController = app(\App\Http\Controllers\PaymentController::class);

        $beforeCall = now();
        $result = $this->callPrivate($paymentController, 'createInvoicePaymentCOA', [
            $payment,
            104.500, // finalPaidAmount: invoice's 100 + the 4.500 gross-up the client was charged
            $gatewayName,
            null,
            null,
        ]);

        $this->assertTrue($result['success'] ?? false, 'createInvoicePaymentCOA() must still succeed.');

        $client = $client->fresh();
        $receivableAccount = Account::where('company_id', $company->id)->where('name', 'Clients')->firstOrFail();
        $gatewayIncomeAccount = Account::where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->firstOrFail();

        $recoveryLines = DB::table('journal_entries')
            ->where('invoice_id', $invoice->id)
            ->where('description', 'like', 'Gateway fee recovered from client%')
            ->get();

        $this->assertCount(2, $recoveryLines, 'Exactly one balanced Dr/Cr pair for the payment-time gross-up.');

        $debitLine = $recoveryLines->firstWhere('account_id', $receivableAccount->id);
        $creditLine = $recoveryLines->firstWhere('account_id', $gatewayIncomeAccount->id);

        $this->assertNotNull($debitLine, 'Dr leg must hit RECEIVABLE_CONTROL (Clients).');
        $this->assertNotNull($creditLine, 'Cr leg must hit GATEWAY_FEE_RECOVERY (Gateway Fee Recovery, 4131).');
        $this->assertEqualsWithDelta(4.500, (float) $debitLine->debit, 0.0005);
        $this->assertEqualsWithDelta(4.500, (float) $creditLine->credit, 0.0005);

        // Dated the PAYMENT (today), never the invoice (backdated 2 days in the fixture).
        $this->assertNotEquals(
            $invoice->invoice_date->toDateString(),
            \Carbon\Carbon::parse($debitLine->transaction_date)->toDateString(),
            'The gross-up must be dated the payment, never the invoice date (Accounting Gap/22 rev 3 ruling B10).'
        );
        $this->assertEquals(
            $beforeCall->toDateString(),
            \Carbon\Carbon::parse($debitLine->transaction_date)->toDateString()
        );

        // The BLOCKING regression check: recalculateInvoiceCOA() is called unconditionally at the
        // end of createInvoicePaymentCOA() — it must NOT have posted the old double-booking pair.
        $this->assertSame(
            0,
            DB::table('journal_entries')->where('invoice_id', $invoice->id)->where('description', 'like', 'Gateway profit on%')->count(),
            'The real payment-collection flow must never post the deleted double-booking pair via recalculateInvoiceCOA().'
        );
    }

    public function test_off_path_company_borne_payment_posts_no_gross_up(): void
    {
        [$company, , $agent, $client, , $invoice] = $this->makeFixture();
        config(['accounting.engine.enabled' => false]);
        $this->trackCompanyForInvariants($company->id);

        $gatewayName = 'W4DGatewayCompany';
        $this->makeCompanyBorneCharge($company, $gatewayName);
        $payment = $this->makePayment($company, $agent, $client, $invoice, $gatewayName);

        $paymentController = app(\App\Http\Controllers\PaymentController::class);

        $result = $this->callPrivate($paymentController, 'createInvoicePaymentCOA', [
            $payment,
            100.000, // finalPaidAmount: no gross-up when the company bears the fee
            $gatewayName,
            null,
            null,
        ]);

        $this->assertTrue($result['success'] ?? false);

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('invoice_id', $invoice->id)->where('description', 'like', 'Gateway fee recovered from client%')->count(),
            'bearer=company must post NO gross-up recovery document — unchanged from before this lane.'
        );

        $this->assertSame(
            0,
            DB::table('journal_entries')->where('invoice_id', $invoice->id)->where('description', 'like', 'Gateway profit on%')->count(),
            'bearer=company must also never trigger the deleted double-booking pair.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) ON path — real collection flow, engine ON.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_client_borne_payment_posts_gross_up_via_posting_seam(): void
    {
        [$company, , $agent, $client, , $invoice] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        // ON path needs a REAL configured gateway key -- GATEWAY_CLEARING_{key} is only mapped
        // for config('accounting.purpose_codes.gateways')'s five real gateways (the main RV
        // document's own requirement, unrelated to this lane's own fee-recovery purpose codes).
        $gatewayName = 'Tap';
        $this->makeClientBorneCharge($company, $gatewayName);
        $payment = $this->makePayment($company, $agent, $client, $invoice, $gatewayName);

        $paymentController = app(\App\Http\Controllers\PaymentController::class);

        $result = $this->callPrivate($paymentController, 'createInvoicePaymentCOA', [
            $payment,
            104.500,
            $gatewayName,
            null,
            null,
        ]);

        $this->assertTrue($result['success'] ?? false);

        $idempotencyKey = \App\Services\Accounting\PaymentIdempotencyKey::forGatewayFeeRecovery($gatewayName, $payment->id);

        $recoveryTransaction = Transaction::where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($recoveryTransaction, 'ON path must post the gross-up as its own idempotency-keyed document.');
        $this->assertSame('DBN', $recoveryTransaction->doc_type ?? $recoveryTransaction->transaction_type ?? null, 'Doc type must be DBN per the plan-of-record (Accounting Gap/22 rev 3 ruling B10).');

        $lines = DB::table('journal_entries')->where('transaction_id', $recoveryTransaction->id)->get();
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0005, 'Document must balance.');
        $this->assertEqualsWithDelta(4.500, (float) $lines->sum('debit'), 0.0005);

        $receivableId = DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'RECEIVABLE_CONTROL')->value('account_id');
        $recoveryIncomeId = DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_FEE_RECOVERY')->value('account_id');

        $this->assertNotNull($receivableId);
        $this->assertNotNull($recoveryIncomeId);
        $this->assertNotNull($lines->firstWhere('account_id', $receivableId));
        $this->assertNotNull($lines->firstWhere('account_id', $recoveryIncomeId));

        // Re-running the SAME payment event must not duplicate the document (idempotency).
        $paymentController2 = app(\App\Http\Controllers\PaymentController::class);
        $this->callPrivate($paymentController2, 'createInvoicePaymentCOA', [
            $payment->fresh(),
            104.500,
            $gatewayName,
            null,
            null,
        ]);

        $this->assertSame(
            1,
            Transaction::where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->count(),
            'The SAME (gateway, payment, partials) event must never post the gross-up document twice.'
        );
    }

    public function test_on_path_company_borne_payment_posts_no_gross_up(): void
    {
        [$company, , $agent, $client, , $invoice] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $gatewayName = 'Hesabe'; // real configured gateway -- see the client-borne test's own note
        $this->makeCompanyBorneCharge($company, $gatewayName);
        $payment = $this->makePayment($company, $agent, $client, $invoice, $gatewayName);

        $paymentController = app(\App\Http\Controllers\PaymentController::class);

        $result = $this->callPrivate($paymentController, 'createInvoicePaymentCOA', [
            $payment,
            100.000,
            $gatewayName,
            null,
            null,
        ]);

        $this->assertTrue($result['success'] ?? false);

        $idempotencyKey = \App\Services\Accounting\PaymentIdempotencyKey::forGatewayFeeRecovery($gatewayName, $payment->id);
        $this->assertNull(
            Transaction::where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->first(),
            'bearer=company must post no gross-up document at all, ON path included.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (c) FIX ROUND 3 — prior gateway-failure row on this SAME payment_id must not block the
    // fee-recovery document. Reproduces the independent verifier's BLOCKING finding: a realistic
    // retry/duplicate-webhook scenario (a Tap/Knet/MyFatoorah failure callback already recorded
    // via PaymentController::firstOrCreateFailureTransaction() for this payment_id, see that
    // method's own docblock) followed by a real success for the SAME payment_id, client-borne
    // fee, used to 1062 on transactions_payment_id_reference_type_unique because both rows
    // claimed (payment_id, 'Payment') — rolling back the ENTIRE payment posting, not just the
    // fee-recovery leg. createGatewayFeeRecoveryEntries() now writes 'Refund' instead (see its
    // own docblock, FIX ROUND 3), which no failure-marker write can ever occupy.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function invokePrivate(object $object, string $method, array $args): mixed
    {
        return $this->callPrivate($object, $method, $args);
    }

    /**
     * Simulates one prior gateway-failure callback for $payment->id via the exact production
     * helper and search-attribute shape used by handleMyFatoorahError()/handleTapCallback()/
     * handleKnetResponse() — see PaymentControllerTransactionDedupTest, which documents and
     * exercises this same helper directly.
     */
    private function seedPriorGatewayFailureTransaction(Payment $payment): Transaction
    {
        $paymentController = app(\App\Http\Controllers\PaymentController::class);

        return $this->invokePrivate($paymentController, 'firstOrCreateFailureTransaction', [
            ['payment_id' => $payment->id, 'reference_type' => 'Payment'],
            [
                'branch_id' => $payment->agent->branch_id,
                'company_id' => $payment->company_id,
                'entity_id' => $payment->company_id,
                'entity_type' => 'company',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'description' => 'Gateway payment failed (prior redelivery)',
                'invoice_id' => $payment->invoice_id,
                'payment_reference' => 'PRIOR-FAILURE',
                'transaction_date' => now()->subMinutes(5),
            ],
        ]);
    }

    public function test_off_path_prior_gateway_failure_row_does_not_block_client_borne_fee_recovery(): void
    {
        [$company, $branch, $agent, $client, , $invoice, $invoiceDetail] = $this->makeFixture();
        config(['accounting.engine.enabled' => false]);
        $this->trackCompanyForInvariants($company->id);

        $gatewayName = 'W4DGatewayCollisionOff';
        $this->makeClientBorneCharge($company, $gatewayName);
        $payment = $this->makePayment($company, $agent, $client, $invoice, $gatewayName);

        // Realistic prior event: a failure callback already landed for this SAME payment_id
        // before the eventual success (retry / duplicate webhook — the scenario
        // PaymentControllerTransactionDedupTest is built around).
        $failureRow = $this->seedPriorGatewayFailureTransaction($payment);
        $this->assertSame('Payment', $failureRow->reference_type);

        $paymentController = app(\App\Http\Controllers\PaymentController::class);

        $result = $this->callPrivate($paymentController, 'createInvoicePaymentCOA', [
            $payment,
            104.500,
            $gatewayName,
            null,
            null,
        ]);

        $this->assertTrue(
            $result['success'] ?? false,
            'A pre-existing (payment_id, "Payment") failure-marker row must never roll back the whole payment posting: '.($result['message'] ?? 'no message')
        );

        // The failure-marker row must survive untouched.
        $this->assertSame(
            1,
            Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count(),
            'The pre-existing failure-marker row must still occupy (payment_id, "Payment") — untouched.'
        );

        // The fee-recovery document must have posted, keyed 'Refund' (never 'Payment').
        $recoveryTransaction = Transaction::where('payment_id', $payment->id)
            ->where('reference_type', 'Refund')
            ->first();
        $this->assertNotNull($recoveryTransaction, 'The fee-recovery Transaction must post under reference_type=Refund, coexisting with the failure marker.');

        $receivableAccount = Account::where('company_id', $company->id)->where('name', 'Clients')->firstOrFail();
        $gatewayIncomeAccount = Account::where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->firstOrFail();

        $lines = DB::table('journal_entries')->where('transaction_id', $recoveryTransaction->id)->get();
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0005, 'Document must balance.');
        $this->assertNotNull($lines->firstWhere('account_id', $receivableAccount->id));
        $this->assertNotNull($lines->firstWhere('account_id', $gatewayIncomeAccount->id));

        // The real receipt itself (the whole point: it must NOT have been rolled back) must
        // also exist for this payment.
        $this->assertSame(
            1,
            Transaction::where('payment_id', $payment->id)->where('reference_type', 'Invoice')->count(),
            'The OFF-path receipt Transaction (reference_type=Invoice) must have been committed, not rolled back by the fee-recovery collision.'
        );
    }

    public function test_on_path_prior_gateway_failure_row_does_not_block_client_borne_fee_recovery(): void
    {
        [$company, , $agent, $client, , $invoice] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $gatewayName = 'Tap';
        $this->makeClientBorneCharge($company, $gatewayName);
        $payment = $this->makePayment($company, $agent, $client, $invoice, $gatewayName);

        $failureRow = $this->seedPriorGatewayFailureTransaction($payment);
        $this->assertSame('Payment', $failureRow->reference_type);

        $paymentController = app(\App\Http\Controllers\PaymentController::class);

        $result = $this->callPrivate($paymentController, 'createInvoicePaymentCOA', [
            $payment,
            104.500,
            $gatewayName,
            null,
            null,
        ]);

        $this->assertTrue(
            $result['success'] ?? false,
            'ON path: a pre-existing (payment_id, "Payment") failure-marker row must never surface as an uncaught DuplicatePaymentReferenceException: '.($result['message'] ?? 'no message')
        );

        $idempotencyKey = \App\Services\Accounting\PaymentIdempotencyKey::forGatewayFeeRecovery($gatewayName, $payment->id);
        $recoveryTransaction = Transaction::where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($recoveryTransaction, 'ON path must still post the gross-up document despite the prior failure marker.');
        $this->assertSame('Refund', $recoveryTransaction->reference_type);

        // Failure marker survives untouched, distinct row.
        $this->assertSame(
            1,
            Transaction::where('payment_id', $payment->id)->where('reference_type', 'Payment')->count()
        );
        $this->assertNotSame($failureRow->id, $recoveryTransaction->id);
    }
}
