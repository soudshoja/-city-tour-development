<?php

namespace Tests\Feature\Accounting;

use App\Enums\ChargeType;
use App\Exceptions\Accounting\PostingException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Http\Controllers\PaymentController;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HesabeCrypt;
use App\Support\PaymentGateway\Knet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * KEY: entry-points (B2). Regression coverage for D1/D4/D5 on the six gateway entry points
 * that surround the now-seam-wrapped PaymentController::createInvoicePaymentCOA() (KEY:
 * coa-seam / B1, landed).
 *
 * Every gateway entry point is invoked DIRECTLY on the controller instance (never via the HTTP
 * kernel / named route), exactly matching the existing convention in
 * tests/Feature/Security/PaymentControllerHotfixTest.php — several of these routes sit behind
 * auth middleware for anything other than the gateway's own callback, and direct invocation
 * sidesteps that entirely while still exercising the real method body byte for byte.
 *
 * "Engine failure" is forced the same way for every gateway, independent of any single
 * gateway's own fee-purpose-code mapping (see PaymentControllerCoaSeamTest's own
 * GATEWAY_FEE_EXPENSE_KNET-specific trick for a narrower version of this): the engine is
 * turned ON (global config + per-company flag) but SystemAccountsSeeder is deliberately never
 * run, so system_accounts is empty and AccountResolver::resolve('RECEIVABLE_CONTROL') — the
 * very first purpose code every draft in this file resolves — throws UnmappedPurposeException
 * (a PostingException) before a single line is built. This is gateway-agnostic and needs no
 * Charge/fee fixture to be meaningful.
 *
 * Tap is NOT covered here. handleTapCallback's own Tap::getCharge() call is a raw curl request
 * (App\Http\Traits\HttpRequestTrait), not Laravel's Http facade, so it cannot be faked with
 * Http::fake() without touching App\Support\PaymentGateway\Tap.php (out of this lane's scope)
 * — confirmed no existing test in the suite exercises handleTapCallback or the Tap gateway
 * class either. handleTapCallback's DB::transaction/createInvoicePaymentCOA/D4-catch/D5-guard
 * shape is byte-for-byte identical to handleKnetResponse's (both built from the same HF-4
 * pattern), which IS fully covered below — see the W2 entry-points report for the diff proof.
 */
class PaymentControllerEntryPointsTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * @return array{company: Company, branch: Branch, agent: Agent, client: Client}
     */
    private function makeTenant(): array
    {
        $company = Company::factory()->create();
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'phone_number' => '99999999',
            'country_code' => '+965',
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return compact('company', 'branch', 'agent', 'client');
    }

    /**
     * @return array{0: Invoice, 1: InvoiceDetail}
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
            // margin = 0, matching PaymentControllerCoaSeamTest's own convention, so
            // InvoiceController::recalculateInvoiceCOA()'s profit-share branch is a no-op and
            // cannot interfere with this file's own assertions.
            'supplier_price' => $taskPrice,
            'markup_price' => 0,
        ]);

        return [$invoice, $invoiceDetail];
    }

    private function makePayment(array $tenant, Invoice $invoice, float $amount, string $status = 'initiate', bool $withAgent = true): Payment
    {
        return Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            // bool, not `?int $agentId = null`: a caller-supplied `null` would silently be
            // replaced by the tenant's own agent via `??`, which is exactly the D5 fixture
            // this parameter exists to build — a NULL agent_id, matching
            // payments.agent_id's own nullable()->nullOnDelete() shape.
            'agent_id' => $withAgent ? $tenant['agent']->id : null,
            'client_id' => $tenant['client']->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenant['agent']->user_id,
            'payment_method_id' => null,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    private function enableEngineWithoutSystemAccounts(Company $company): void
    {
        // Deliberately NO SystemAccountsSeeder::run() — see class docblock. RECEIVABLE_CONTROL
        // stays unmapped, so any draft this file builds fails at the very first purpose code.
        config(['accounting.engine.enabled' => true]);
        \Illuminate\Support\Facades\Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // KNET — handleKnetResponse(). RETURN-URL (route('knet.response') is Knet.php's only
    // responseURL; no separate notification URL exists for this gateway).
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function buildKnetRequest(Company $company, Payment $payment, Invoice $invoice, array $fields, string $knetKey): Request
    {
        // udf5 (partialId) is deliberately OMITTED, not sent empty: publicReceiptNotice()'s
        // $partialId parameter is strictly `?int`, but handleKnetResponse's own
        // `$partialId = $responseData['udf5'] ?? null;` never casts it — unlike Hesabe's
        // `intval($raw)` equivalent — so an empty-but-present udf5 (a live, pre-existing,
        // out-of-scope gap, flagged in the W2 entry-points report, not fixed here) throws a
        // TypeError before ever reaching this lane's own D1/D4/D5 code. Omitting the key
        // reproduces the ordinary "full, non-partial payment" case parse_str would also
        // produce when KNET's own real response omits it.
        $queryString = http_build_query(array_merge([
            'udf1' => (string) $payment->id,
            'udf2' => $payment->voucher_number,
            'udf3' => (string) $company->id,
            'udf4' => $invoice->invoice_number,
        ], $fields));

        $knet = new Knet($company->id);
        $reflection = new \ReflectionMethod($knet, 'encryptAES');
        $reflection->setAccessible(true);
        $trandata = $reflection->invoke($knet, $queryString, $knetKey);

        return Request::create('/knet-response', 'GET', [
            'trandata' => $trandata,
            'company_id' => $company->id,
        ]);
    }

    private function seedKnetCharge(Company $company, string $key): void
    {
        Charge::create([
            'name' => 'KNET',
            'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 0,
            'charge_type' => 'Flat Rate',
            'self_charge' => 0,
            'extra_charge' => 0,
            'paid_by' => 'Company',
            'company_id' => $company->id,
            'is_active' => true,
            'tran_portal_id' => 'TID-TEST',
            'tran_portal_password' => 'PWD-TEST',
            'terminal_resource_key' => $key,
        ]);
    }

    public function test_knet_engine_failure_redirects_to_failed_with_class_and_key_and_rolls_back(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);
        $knetKey = 'KNETTESTKEY12345';
        $this->seedKnetCharge($company, $knetKey);
        $this->enableEngineWithoutSystemAccounts($company);

        $request = $this->buildKnetRequest($company, $payment, $invoice, [
            'result' => 'CAPTURED',
            'amt' => '100.000',
            'paymentid' => 'KPAY-1',
            'tranid' => 'KTRAN-1',
            'trackid' => 'KTRACK-1',
        ], $knetKey);

        $controller = app(PaymentController::class);
        $response = $controller->handleKnetResponse($request);

        $this->assertTrue($response->isRedirect(route('payment.failed')));
        $error = $response->getSession()->get('error');
        $this->assertStringContainsString('Accounting posting failed (UnmappedPurposeException)', $error);
        $this->assertStringContainsString('gateway:knet:payment:'.$payment->id, $error);
        $this->assertStringContainsString('Payment not completed.', $error);

        $this->assertSame('initiate', $payment->fresh()->status, 'the DB::transaction() must have rolled the payment completion back whole');
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }

    public function test_knet_engine_failure_reverted_catch_becomes_a_generic_message(): void
    {
        // MUTATION-PROBE: simulates "revert the catch(PostingException) insertion" per the
        // brief's own acceptance criterion, by asserting what WOULD happen if the
        // PostingException fell through to the generic \Throwable catch instead — i.e. this
        // pins that the two branches produce genuinely different, distinguishable output, so a
        // future revert of the new catch is guaranteed to change this test's own assertions
        // rather than pass silently.
        Http::fake();
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);
        $knetKey = 'KNETTESTKEY67890';
        $this->seedKnetCharge($company, $knetKey);
        $this->enableEngineWithoutSystemAccounts($company);

        $request = $this->buildKnetRequest($company, $payment, $invoice, [
            'result' => 'CAPTURED',
            'amt' => '100.000',
            'paymentid' => 'KPAY-2',
            'tranid' => 'KTRAN-2',
            'trackid' => 'KTRACK-2',
        ], $knetKey);

        $controller = app(PaymentController::class);
        $response = $controller->handleKnetResponse($request);
        $error = $response->getSession()->get('error');

        $this->assertNotSame('Something went wrong. Please contact support.', $error);
    }

    public function test_knet_flags_off_matches_baseline_behaviour(): void
    {
        Http::fake();
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);
        $knetKey = 'KNETTESTKEYOFF01';
        $this->seedKnetCharge($company, $knetKey);
        // config(['accounting.engine.enabled' => false]) is the default; engine stays OFF.

        // Legacy-only accounts, mirroring PaymentControllerCoaSeamTest's OFF-path
        // fixtures — no CoaSeeder/SystemAccountsSeeder involved.
        \App\Models\Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayAsset = \App\Models\Account::create(['name' => 'Gateway Asset', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayExpense = \App\Models\Account::create(['name' => 'Gateway Expense', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        Charge::where('company_id', $company->id)->where('name', 'KNET')->update([
            'acc_fee_bank_id' => $gatewayAsset->id,
            'acc_fee_id' => $gatewayExpense->id,
        ]);

        $request = $this->buildKnetRequest($company, $payment, $invoice, [
            'result' => 'CAPTURED',
            'amt' => '100.000',
            'paymentid' => 'KPAY-3',
            'tranid' => 'KTRAN-3',
            'trackid' => 'KTRACK-3',
        ], $knetKey);

        $controller = app(PaymentController::class);
        $response = $controller->handleKnetResponse($request);

        $this->assertFalse($response->isRedirect(route('payment.failed')), (string) $response->getSession()->get('error'));
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Invoice')->count());
    }

    /**
     * Residual 13 fix (W2.1). D5's own guard (`$payment->agent?->branch?->company?->id`,
     * Log::critical when null) was correct in isolation but NOT reachable through this real
     * request for a null-agent payment — a DIFFERENT, pre-existing, unguarded
     * `$payment->agent->branch->company_id` chain inside publicReceiptNotice() (invoked
     * unconditionally, for BOTH the success and the failed branch, before either can run — see
     * handleKnetResponse's own `$receiptInfo = $this->publicReceiptNotice(...)` a few lines
     * above the failed-branch check) crashed first, caught by this method's own outer
     * `catch (\Throwable $e)` instead, with no critical log for the unattributed-payment case
     * this lane was built to alert on. The "M1 null guard" added directly inside
     * publicReceiptNotice() (mirroring D5's own shape) now fires FIRST for this exact request,
     * so the critical log this test used to prove never ran now does. The method still
     * degrades gracefully either way — no exception escapes handleKnetResponse, no accounting
     * row is created — only the diagnostic changed from silent to alerted.
     */
    public function test_knet_unattributed_agent_now_reaches_the_guard_in_public_receipt_notice(): void
    {
        Log::spy();
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        // payments.agent_id is nullable()->nullOnDelete() — the exact live shape after an
        // Agent is deleted out from under a payment still in flight.
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate', false);
        $this->assertNull($payment->agent_id);
        $knetKey = 'KNETTESTKEYD5001';
        $this->seedKnetCharge($company, $knetKey);

        $request = $this->buildKnetRequest($company, $payment, $invoice, [
            'result' => 'DECLINED',
        ], $knetKey);

        $controller = app(PaymentController::class);
        // Must not throw all the way out to the caller either way — this method's own outer
        // catch(\Throwable) is what actually absorbs the PaymentUnattributedException thrown
        // inside publicReceiptNotice(), not this lane's own D5 guard (which now never gets a
        // chance to run for THIS request — publicReceiptNotice() throws before reaching it).
        $response = $controller->handleKnetResponse($request);

        $this->assertNotNull($response);
        $this->assertTrue($response->isRedirect(route('payment.failed')));
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(function (string $message, array $context) use ($payment) {
                return $message === 'accounting.payment_unattributed'
                    && ($context['payment_id'] ?? null) === $payment->id;
            });
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Hesabe response (browser return URL) — handleHesabeResponse(). D1 fix: the
    // createInvoicePaymentCOA() call used to run in a SEPARATE, unlocked DB::beginTransaction()
    // started only after the lockForUpdate transaction had already committed the payment as
    // 'completed' — a COA failure left the payment completed with zero accounting rows. It now
    // runs inside the SAME lockForUpdate transaction.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function hesabeConfig(): array
    {
        return ['api_key' => str_repeat('k', 32), 'iv_key' => str_repeat('i', 16)];
    }

    private function buildHesabeResponseRequest(Payment $payment, array $data): Request
    {
        $cfg = $this->hesabeConfig();
        $wrapper = ['status' => true, 'response' => $data];
        $encrypted = HesabeCrypt::encrypt(json_encode($wrapper), $cfg['api_key'], $cfg['iv_key']);

        return Request::create('/hesabe-callback', 'GET', ['data' => $encrypted]);
    }

    public function test_hesabe_response_engine_failure_leaves_payment_uncompleted_with_zero_transactions(): void
    {
        Http::fake();
        config(['services.hesabe.api_key' => $this->hesabeConfig()['api_key'], 'services.hesabe.iv_key' => $this->hesabeConfig()['iv_key']]);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);
        $this->enableEngineWithoutSystemAccounts($company);

        $request = $this->buildHesabeResponseRequest($payment, [
            'orderReferenceNumber' => $payment->voucher_number,
            'variable1' => 'invoice',
            'variable2' => null,
            'transactionId' => 'HTXN-1',
            'trackID' => 'HTRACK-1',
            'amount' => 100.00,
            'paidOn' => now()->toDateTimeString(),
            'resultCode' => '000',
            // paymentToken deliberately omitted: skips the Hesabe::getPaymentStatus() call
            // entirely (the controller only makes it `if ($paymentToken)`), so this test needs
            // no gateway mock at all.
        ]);

        $controller = app(PaymentController::class);
        $response = $controller->handleHesabeResponse($request);

        $this->assertTrue($response->isRedirect(route('payment.failed')));
        $error = $response->getSession()->get('error');
        $this->assertStringContainsString('Accounting posting failed (UnmappedPurposeException)', $error);
        $this->assertStringContainsString('gateway:hesabe:payment:'.$payment->id, $error);

        // The D1 fix under test: before it, this exact scenario committed the first
        // (lockForUpdate) transaction — flipping the payment to 'completed' — and only THEN
        // attempted to post, in a transaction with no connection to the one that just
        // committed. Now both live in the one transaction, so a thrown PostingException rolls
        // the payment completion back too.
        $this->assertSame('initiate', $payment->fresh()->status);
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }

    /**
     * Residual 4 fix (W2.1). The legacy `$coaResult['success'] === false` branch now throws a
     * dedicated LegacyInvoiceCoaFailureException instead of a bare \RuntimeException, and the
     * catch here is narrowed to that concrete type. Proves the REDIRECT half still works for a
     * genuine legacy business-rule failure: no Charge row configured for this gateway, so
     * createInvoicePaymentCOA's own legacy closure throws "Charge record not found", caught by
     * createInvoicePaymentCOA's OWN catch(\Exception), surfaced as $coaResult['success'] =
     * false — never reaching this method's catch as a raw exception at all.
     *
     * R-1 fix (W2.2): createInvoicePaymentCOA()'s own catch(\Exception) can no longer tell a
     * business-rule message like this one apart from a QueryException's raw SQL -- both are
     * just "some \Exception" to it -- so it now hands back the SAME fixed sentence + voucher
     * correlation id for either. This test used to assert the raw "Charge record not found for
     * gateway: Hesabe" text was flashed verbatim; that assertion is exactly the shape the fix
     * closes. What must still hold is that the redirect/rollback behaviour is unchanged and the
     * flashed string is the new safe one, never the business-rule message itself.
     */
    public function test_hesabe_response_legacy_coa_failure_redirects_to_failed(): void
    {
        Http::fake();
        config(['services.hesabe.api_key' => $this->hesabeConfig()['api_key'], 'services.hesabe.iv_key' => $this->hesabeConfig()['iv_key']]);
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);
        // Deliberately NO Charge row for 'Hesabe'.

        $request = $this->buildHesabeResponseRequest($payment, [
            'orderReferenceNumber' => $payment->voucher_number,
            'variable1' => 'invoice',
            'variable2' => null,
            'transactionId' => 'HTXN-LEGACY-FAIL',
            'trackID' => 'HTRACK-LEGACY-FAIL',
            'amount' => 100.00,
            'paidOn' => now()->toDateTimeString(),
            'resultCode' => '000',
        ]);

        $controller = app(PaymentController::class);
        $response = $controller->handleHesabeResponse($request);

        $this->assertTrue($response->isRedirect(route('payment.failed')));
        $error = $response->getSession()->get('error');
        $this->assertStringContainsString('Error creating COA.', $error);
        $this->assertStringContainsString($payment->voucher_number, $error);
        $this->assertStringNotContainsString('Charge record not found', $error, 'the business-rule detail must stay in the log, never in the flash');
        $this->assertSame('initiate', $payment->fresh()->status);
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }

    /**
     * R-c fix (W2b). residual-5 (W2.1) moved $partialId's derivation off $data['variable2']
     * onto invoice_partials, and in doing so the diagnostic log of the raw gateway-echoed
     * variable2 got relocated to AFTER the `Payment::where('voucher_number', ...)` lookup and
     * its own "Payment record not found" early return -- so an unresolvable
     * orderReferenceNumber logged nothing, the exact loss class residual-5/residual-14 exist to
     * prevent. HEAD logged it BEFORE that lookup. This proves it is logged even when the
     * lookup fails.
     */
    public function test_hesabe_response_unresolvable_voucher_still_logs_raw_variable2(): void
    {
        Log::spy();
        Http::fake();
        config(['services.hesabe.api_key' => $this->hesabeConfig()['api_key'], 'services.hesabe.iv_key' => $this->hesabeConfig()['iv_key']]);

        $request = $this->buildHesabeResponseRequest(new Payment, [
            'orderReferenceNumber' => 'NO-SUCH-VOUCHER-NUMBER',
            'variable1' => 'invoice',
            'variable2' => '4242',
            'transactionId' => 'HTXN-UNRESOLVABLE',
            'trackID' => 'HTRACK-UNRESOLVABLE',
            'amount' => 100.00,
            'paidOn' => now()->toDateTimeString(),
            'resultCode' => '000',
        ]);

        $controller = app(PaymentController::class);
        $response = $controller->handleHesabeResponse($request);

        $this->assertTrue($response->isRedirect(route('payment.failed')));
        // No ->once() -- Log::info() is called several times over this flow (the raw request
        // dump, the decrypted callback dump, this one, then the not-found log); matching the
        // existing project convention (EnsureSystemLeavesTest::871, ChatControllerPostingTest),
        // withArgs() alone is the assertion that a call with THESE exact arguments occurred.
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context) => $message === 'Extracted Hesabe variable2 (partialId):' && ($context['raw'] ?? null) === '4242');
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context) => $message === 'Payment record not found' && ($context['voucher_number'] ?? null) === 'NO-SUCH-VOUCHER-NUMBER');
    }

    /**
     * Residual 4 fix (W2.1). Narrowing the catch from bare \RuntimeException to the concrete
     * LegacyInvoiceCoaFailureException means a genuine QueryException/PDOException — which also
     * extends \RuntimeException — no longer matches this catch and must propagate uncaught
     * (HTTP 500 in production) instead of being swallowed into a "Payment Failed" redirect with
     * the raw SQL and bindings written into the session flash. Forced here via an over-length
     * payment_reference: `$payment->payment_reference = $data['transactionId']; ...
     * $payment->save();` runs BEFORE createInvoicePaymentCOA is even called, inside the SAME
     * lockForUpdate transaction — outside createInvoicePaymentCOA's own try/catch entirely — so
     * this exercises the exact catch clause under test, not createInvoicePaymentCOA's separate
     * catch(\Exception).
     */
    public function test_hesabe_response_query_exception_propagates_uncaught_not_a_failed_redirect(): void
    {
        Http::fake();
        config(['services.hesabe.api_key' => $this->hesabeConfig()['api_key'], 'services.hesabe.iv_key' => $this->hesabeConfig()['iv_key']]);
        $tenant = $this->makeTenant();
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);

        $request = $this->buildHesabeResponseRequest($payment, [
            'orderReferenceNumber' => $payment->voucher_number,
            'variable1' => 'invoice',
            'variable2' => null,
            // Over 255 chars: payments.payment_reference is a plain string/varchar column, and
            // mysql_testing runs with 'strict' => true (config/database.php), so this 1406s
            // ("Data too long for column 'payment_reference'") rather than silently truncating.
            'transactionId' => str_repeat('Z', 300),
            'trackID' => 'HTRACK-QE',
            'amount' => 100.00,
            'paidOn' => now()->toDateTimeString(),
            'resultCode' => '000',
        ]);

        $controller = app(PaymentController::class);

        $this->expectException(\Illuminate\Database\QueryException::class);

        try {
            $controller->handleHesabeResponse($request);
        } finally {
            $this->assertSame('initiate', $payment->fresh()->status, 'the row-lock transaction must roll the whole closure back');
            $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
        }
    }

    /**
     * Residual 12 fix (W2.1). payment/failed.blade.php now renders session('error') when
     * present, falling back to the original fixed copy otherwise. Before this fix, D4's own
     * class+key message (built by this file's own PostingException catch, several tests
     * above) was flashed and then silently discarded — the page rendered nothing but the
     * fixed "Unfortunately, your payment could not be processed." string regardless. Reuses
     * the exact flashed message from a genuine RETURN-URL engine failure to prove the page
     * now shows it.
     */
    public function test_failed_page_renders_the_flashed_engine_failure_message(): void
    {
        Http::fake();
        config(['services.hesabe.api_key' => $this->hesabeConfig()['api_key'], 'services.hesabe.iv_key' => $this->hesabeConfig()['iv_key']]);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);
        $this->enableEngineWithoutSystemAccounts($company);

        $request = $this->buildHesabeResponseRequest($payment, [
            'orderReferenceNumber' => $payment->voucher_number,
            'variable1' => 'invoice',
            'variable2' => null,
            'transactionId' => 'HTXN-VIEW',
            'trackID' => 'HTRACK-VIEW',
            'amount' => 100.00,
            'paidOn' => now()->toDateTimeString(),
            'resultCode' => '000',
        ]);

        $controller = app(PaymentController::class);
        $redirect = $controller->handleHesabeResponse($request);
        $error = $redirect->getSession()->get('error');
        $this->assertStringContainsString('UnmappedPurposeException', $error);
        $this->assertStringContainsString('gateway:hesabe:payment:'.$payment->id, $error);

        $response = $this->withSession(['error' => $error])->get(route('payment.failed'));

        $response->assertOk();
        $response->assertSee('UnmappedPurposeException');
        $response->assertSee('gateway:hesabe:payment:'.$payment->id);
    }

    public function test_hesabe_response_flags_off_matches_baseline_behaviour(): void
    {
        Http::fake();
        config(['services.hesabe.api_key' => $this->hesabeConfig()['api_key'], 'services.hesabe.iv_key' => $this->hesabeConfig()['iv_key']]);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);

        \App\Models\Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayAsset = \App\Models\Account::create(['name' => 'Gateway Asset', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayExpense = \App\Models\Account::create(['name' => 'Gateway Expense', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        Charge::create([
            'name' => 'Hesabe', 'type' => ChargeType::PAYMENT_GATEWAY->value, 'amount' => 0,
            'charge_type' => 'Flat Rate', 'self_charge' => 0, 'extra_charge' => 0, 'paid_by' => 'Company',
            'company_id' => $company->id, 'acc_fee_bank_id' => $gatewayAsset->id, 'acc_fee_id' => $gatewayExpense->id,
        ]);

        $request = $this->buildHesabeResponseRequest($payment, [
            'orderReferenceNumber' => $payment->voucher_number,
            'variable1' => 'invoice',
            'variable2' => null,
            'transactionId' => 'HTXN-2',
            'trackID' => 'HTRACK-2',
            'amount' => 100.00,
            'paidOn' => now()->toDateTimeString(),
            'resultCode' => '000',
        ]);

        $controller = app(PaymentController::class);
        $response = $controller->handleHesabeResponse($request);

        $this->assertFalse($response->isRedirect(route('payment.failed')), (string) $response->getSession()->get('error'));
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Invoice')->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Hesabe webhook (server-to-server) — handleHesabeWebhook(). routes/api.php, POST, no auth
    // middleware. WEBHOOK classification: a genuine engine failure must propagate.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_hesabe_webhook_engine_failure_propagates_and_leaves_payment_uncompleted(): void
    {
        // A real base_url is required for handleHesabeWebhook()'s mandatory
        // transaction-enquiry HTTP call to reach the fake below at all (an
        // empty/null base_url produces a schemeless URL the client can't send).
        config(['services.hesabe.base_url' => 'https://fake-hesabe.test']);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);
        $this->enableEngineWithoutSystemAccounts($company);

        // handleHesabeWebhook() now confirms the transaction via Hesabe's own
        // GET /api/transaction/{token} enquiry before writing anything (see
        // HesabeWebhookVerificationTest) -- fake that enquiry to report the
        // SUCCESSFUL, amount-matching transaction the request body claims, so
        // this test still exercises the posting-engine-failure path below it.
        Http::fake([
            'fake-hesabe.test/api/transaction/*' => Http::response([
                'status' => true,
                'data' => [
                    'token' => 'HTOK-ENGFAIL',
                    'amount' => '100.000',
                    'reference_number' => $payment->voucher_number,
                    'status' => 'SUCCESSFUL',
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $request = Request::create('/payment/hesabe-webhook', 'POST', [
            'reference_number' => $payment->voucher_number,
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => 100.00,
            'payment_type' => 'card',
            'service_type' => 'invoice',
            'datetime' => now()->toDateTimeString(),
            'token' => 'HTOK-ENGFAIL',
        ]);

        $controller = app(PaymentController::class);

        try {
            $controller->handleHesabeWebhook($request);
            $this->fail('Expected a PostingException to propagate out of the webhook handler.');
        } catch (PostingException $e) {
            $this->assertInstanceOf(UnmappedPurposeException::class, $e);
        }

        $this->assertSame('initiate', $payment->fresh()->status, 'the manual DB::rollback() in the new catch must have undone the payment completion');
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }

    public function test_hesabe_webhook_flags_off_matches_baseline_behaviour(): void
    {
        // A real base_url is required for handleHesabeWebhook()'s mandatory
        // transaction-enquiry HTTP call to reach the fake below at all (an
        // empty/null base_url produces a schemeless URL the client can't send).
        config(['services.hesabe.base_url' => 'https://fake-hesabe.test']);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00);

        \App\Models\Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayAsset = \App\Models\Account::create(['name' => 'Gateway Asset', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayExpense = \App\Models\Account::create(['name' => 'Gateway Expense', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        Charge::create([
            'name' => 'Hesabe', 'type' => ChargeType::PAYMENT_GATEWAY->value, 'amount' => 0,
            'charge_type' => 'Flat Rate', 'self_charge' => 0, 'extra_charge' => 0, 'paid_by' => 'Company',
            'company_id' => $company->id, 'acc_fee_bank_id' => $gatewayAsset->id, 'acc_fee_id' => $gatewayExpense->id,
        ]);

        // handleHesabeWebhook() now confirms the transaction via Hesabe's own
        // GET /api/transaction/{orderReference}?isOrderReference=1 enquiry (no
        // token in this request) before writing anything -- fake it to confirm
        // the SUCCESSFUL, amount-matching transaction the request body claims.
        Http::fake([
            'fake-hesabe.test/api/transaction/*' => Http::response([
                'status' => true,
                'data' => [
                    'token' => 'HTOK-BASELINE',
                    'amount' => '100.000',
                    'reference_number' => $payment->voucher_number,
                    'status' => 'SUCCESSFUL',
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $request = Request::create('/payment/hesabe-webhook', 'POST', [
            'reference_number' => $payment->voucher_number,
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => 100.00,
            'payment_type' => 'card',
            'service_type' => 'invoice',
            'datetime' => now()->toDateTimeString(),
        ]);

        $controller = app(PaymentController::class);
        $response = $controller->handleHesabeWebhook($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Invoice')->count());
    }

    /**
     * Residual 5 fix (W2.1). handleHesabeResponse() and handleHesabeWebhook() must derive
     * $partialIds from the SAME source (invoice_partials by payment id) so a redelivery of
     * the same event through the OTHER handler resolves to the SAME PaymentIdempotencyKey and
     * is recognised as a retry, not a different document. This request deliberately omits
     * 'variable2' -- the field the response handler used to derive its own key from, before
     * this fix, giving 'partials:none' regardless of the invoice_partials row created below.
     * With the fix, it is ignored entirely and both handlers resolve the SAME partial id.
     * Proof: after the response handler posts successfully, resetting the payment back to
     * 'initiate' (simulating a redelivery the row lock would otherwise serialize away inside
     * one request) and running it through the webhook handler must resolve to the SAME
     * document, not throw DuplicatePaymentReferenceException -- which is exactly what a key
     * mismatch produces here, per unique(payment_id, reference_type).
     */
    public function test_hesabe_response_and_webhook_derive_the_same_key_for_the_same_payment(): void
    {
        Http::fake();
        config(['services.hesabe.api_key' => $this->hesabeConfig()['api_key'], 'services.hesabe.iv_key' => $this->hesabeConfig()['iv_key']]);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        \Database\Seeders\CoaSeeder::run($company->id);
        [$invoice] = $this->makeInvoice($tenant, 50.00);
        $payment = $this->makePayment($tenant, $invoice, 50.00);

        $partial = \App\Models\InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $tenant['client']->id,
            'amount' => 50.00,
            'status' => 'unpaid',
            'service_charge' => 0,
            'expiry_date' => now()->addDays(7),
            'type' => 'split',
            'payment_gateway' => 'Hesabe',
            'payment_id' => $payment->id,
        ]);

        config(['accounting.engine.enabled' => true]);
        \Illuminate\Support\Facades\Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        (new \Database\Seeders\SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);

        $request = $this->buildHesabeResponseRequest($payment, [
            'orderReferenceNumber' => $payment->voucher_number,
            'variable1' => 'invoice',
            // 'variable2' deliberately absent -- see docblock.
            'transactionId' => 'HTXN-KEYCHECK',
            'trackID' => 'HTRACK-KEYCHECK',
            'amount' => 50.00,
            'paidOn' => now()->toDateTimeString(),
            'resultCode' => '000',
        ]);

        $controller = app(PaymentController::class);
        $response = $controller->handleHesabeResponse($request);

        $this->assertFalse($response->isRedirect(route('payment.failed')), (string) $response->getSession()->get('error'));
        $this->assertSame('completed', $payment->fresh()->status);

        $expectedKey = \App\Services\Accounting\PaymentIdempotencyKey::forGatewayPayment('Hesabe', $payment->id, [$partial->id]);
        $transaction = Transaction::withoutGlobalScopes()->where('payment_id', $payment->id)->first();
        $this->assertNotNull($transaction, 'the response handler must post a document for this payment');
        $this->assertSame(
            $expectedKey,
            $transaction->idempotency_key,
            'the response handler must derive its key via invoice_partials, matching the webhook, not from variable2'
        );

        // Simulate a redelivery of the SAME event through the OTHER handler.
        $payment->update(['status' => 'initiate']);

        $webhookRequest = Request::create('/payment/hesabe-webhook', 'POST', [
            'reference_number' => $payment->voucher_number,
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => 50.00,
            'payment_type' => 'card',
            'service_type' => 'invoice',
            'datetime' => now()->toDateTimeString(),
        ]);

        $webhookResponse = $controller->handleHesabeWebhook($webhookRequest);

        // A key MISMATCH would attempt a genuinely new post under a different key here and
        // collide on transactions_payment_id_reference_type_unique --
        // DuplicatePaymentReferenceException, propagated uncaught by this WEBHOOK's own D4
        // catch (PostingException). Reaching a 200 instead proves the SAME key was derived.
        $this->assertSame(200, $webhookResponse->getStatusCode(), (string) $webhookResponse->getContent());
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('payment_id', $payment->id)->count());
        $this->assertSame($transaction->id, Transaction::withoutGlobalScopes()->where('payment_id', $payment->id)->value('id'));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // MyFatoorah webhook (server-to-server) — handleWebhookFatoorah(). routes/api.php, POST,
    // HMAC-signature verified, no auth middleware, no external HTTP call at all (unlike the
    // browser callback). WEBHOOK classification: propagate.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function myFatoorahWebhookRequest(Payment $payment, string $secret, string $invoiceStatus = 'PAID'): Request
    {
        $payload = [
            'Data' => [
                'Invoice' => [
                    'Id' => (string) $payment->payment_reference,
                    'Status' => $invoiceStatus,
                    'ExternalIdentifier' => '',
                    'UserDefinedField' => json_encode(['process' => 'invoice']),
                ],
                'Transaction' => [
                    'Status' => $invoiceStatus,
                    'PaymentId' => 'MFPAY-1',
                ],
                // NOT part of MyFatoorah's real webhook wire shape — flagged as a separate,
                // pre-existing, out-of-scope bug in the W2 entry-points report:
                // handleWebhookFatoorah() passes `$payload['Data']` straight into
                // processMyFatoorahPaymentCompletion(), which actually reads flat
                // InvoiceValue/InvoiceTransactions/InvoiceReference/InvoiceId/InvoiceStatus
                // keys (the shape MyFatoorah::getPaymentStatus() returns, not the webhook's
                // own nested Data.Invoice.*/Data.Transaction.* shape). On HEAD this means
                // every real successful-payment webhook delivery throws "Undefined array key
                // InvoiceValue" and 500s BEFORE ever reaching this lane's own D4 catch —
                // added here purely so this test can reach and prove that catch's own
                // mechanics in isolation from that unrelated defect.
                'InvoiceValue' => 100.00,
                'InvoiceTransactions' => [['AuthorizationId' => 'AUTH-WH-1']],
                'InvoiceReference' => (string) $payment->payment_reference,
                'InvoiceId' => (string) $payment->payment_reference,
                'InvoiceStatus' => $invoiceStatus === 'PAID' ? 'Paid' : $invoiceStatus,
            ],
        ];
        $body = json_encode($payload);

        $sigString = sprintf(
            'Invoice.Id=%s,Invoice.Status=%s,Transaction.Status=%s,Transaction.PaymentId=%s,Invoice.ExternalIdentifier=%s',
            $payload['Data']['Invoice']['Id'],
            $payload['Data']['Invoice']['Status'],
            $payload['Data']['Transaction']['Status'],
            $payload['Data']['Transaction']['PaymentId'],
            $payload['Data']['Invoice']['ExternalIdentifier']
        );
        $signature = base64_encode(hash_hmac('sha256', $sigString, $secret, true));

        $request = Request::create('/payment/webhook-fatoorah', 'POST', [], [], [], [], $body);
        $request->headers->set('MyFatoorah-Signature', $signature);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_myfatoorah_webhook_engine_failure_propagates_and_leaves_payment_uncompleted(): void
    {
        $secret = 'mf-webhook-test-secret';
        config(['services.myfatoorah.webhook_secret_key' => $secret]);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');
        $payment->payment_reference = 'MFINV-1';
        $payment->save();
        $this->enableEngineWithoutSystemAccounts($company);

        $request = $this->myFatoorahWebhookRequest($payment, $secret);
        $controller = app(PaymentController::class);

        try {
            $controller->handleWebhookFatoorah($request);
            $this->fail('Expected a PostingException to propagate out of the webhook handler.');
        } catch (PostingException $e) {
            $this->assertInstanceOf(UnmappedPurposeException::class, $e);
        }

        $this->assertSame('initiate', $payment->fresh()->status);
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }

    public function test_myfatoorah_webhook_flags_off_matches_baseline_behaviour(): void
    {
        $secret = 'mf-webhook-test-secret-2';
        config(['services.myfatoorah.webhook_secret_key' => $secret]);
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');
        $payment->payment_reference = 'MFINV-2';
        $payment->save();

        \App\Models\Account::create(['name' => 'Clients', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayAsset = \App\Models\Account::create(['name' => 'Gateway Asset', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $gatewayExpense = \App\Models\Account::create(['name' => 'Gateway Expense', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        Charge::create([
            'name' => 'MyFatoorah', 'type' => ChargeType::PAYMENT_GATEWAY->value, 'amount' => 0,
            'charge_type' => 'Flat Rate', 'self_charge' => 0, 'extra_charge' => 0, 'paid_by' => 'Company',
            'company_id' => $company->id, 'acc_fee_bank_id' => $gatewayAsset->id, 'acc_fee_id' => $gatewayExpense->id,
        ]);

        $request = $this->myFatoorahWebhookRequest($payment, $secret);
        $controller = app(PaymentController::class);
        $response = $controller->handleWebhookFatoorah($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->where('reference_type', 'Invoice')->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // MyFatoorah callback (browser return URL) — handleMyFatoorahCallback(), which calls the
    // shared processMyFatoorahPaymentCompletion(). RETURN-URL classification. The one external
    // call (MyFatoorah::getPaymentStatus()) uses Laravel's Http facade, so Http::fake() covers
    // it with no need to touch MyFatoorah.php.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_myfatoorah_callback_engine_failure_redirects_to_failed_with_class_and_key(): void
    {
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');
        $payment->payment_reference = 'MFINV-CB-1';
        $payment->save();
        $this->enableEngineWithoutSystemAccounts($company);

        Http::fake([
            '*/GetPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceId' => 999,
                    'InvoiceStatus' => 'Paid',
                    'InvoiceValue' => 100.00,
                    'InvoiceReference' => 'MFINV-CB-1',
                    'InvoiceTransactions' => [['AuthorizationId' => 'AUTH-1']],
                    'UserDefinedField' => json_encode(['process' => 'invoice', 'payment_id' => $payment->id]),
                ],
            ], 200),
        ]);

        $request = Request::create('/payments/callback', 'GET', ['paymentId' => 'MF-PAY-ID-1']);
        $controller = app(PaymentController::class);
        $response = $controller->handleMyFatoorahCallback($request);

        $this->assertTrue($response->isRedirect(route('payment.failed')));
        $error = $response->getSession()->get('error');
        $this->assertStringContainsString('Accounting posting failed (UnmappedPurposeException)', $error);
        $this->assertStringContainsString('gateway:myfatoorah:payment:'.$payment->id, $error);

        $this->assertSame('initiate', $payment->fresh()->status);
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // UPayment callback (browser return URL) — handleUPaymentCallback(). RETURN-URL
    // classification: the separate notificationUrl (handleUPaymentNoti) is a no-op stub that
    // never reaches createInvoicePaymentCOA. UPayment::getPaymentStatus() uses Http facade.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_upayment_callback_engine_failure_redirects_to_failed_with_class_and_key(): void
    {
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');
        $payment->payment_reference = 'UPTRACK-1';
        $payment->save();
        $this->enableEngineWithoutSystemAccounts($company);

        Http::fake([
            '*' => Http::response([
                'status' => true,
                'data' => [
                    'transaction' => [
                        'result' => 'CAPTURED',
                        'status' => 'done',
                        'total_price' => 100.00,
                        'payment_id' => 'UPAY-1',
                        'order_id' => 'ORD-1',
                        'invoice_id' => $invoice->id,
                        'track_id' => 'UPTRACK-1',
                        'payment_type' => 'card',
                        'payment_method' => 'visa',
                    ],
                ],
            ], 200),
        ]);

        $request = Request::create('/uPayment-callback', 'GET', ['trackId' => 'UPTRACK-1']);
        $controller = app(PaymentController::class);
        $response = $controller->handleUPaymentCallback($request);

        $this->assertTrue($response->isRedirect(route('payment.failed')));
        $error = $response->getSession()->get('error');
        $this->assertStringContainsString('Accounting posting failed (UnmappedPurposeException)', $error);
        $this->assertStringContainsString('gateway:upayment:payment:'.$payment->id, $error);

        $this->assertSame('initiate', $payment->fresh()->status);
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // processMyFatoorahPaymentCompletion() itself — the explicit catch(PostingException) added
    // for D4 there is functionally a no-op today (the generic catch already rolls back and
    // rethrows unconditionally for every case), but must still exist and must still behave
    // identically. Reflection-invoked directly, matching PaymentControllerHotfixTest's own
    // convention for this exact private method.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_process_myfatoorah_payment_completion_engine_failure_rolls_back_and_rethrows(): void
    {
        $tenant = $this->makeTenant();
        $company = $tenant['company'];
        [$invoice] = $this->makeInvoice($tenant, 100.00);
        $payment = $this->makePayment($tenant, $invoice, 100.00, 'initiate');
        $this->enableEngineWithoutSystemAccounts($company);

        $statusData = [
            'InvoiceValue' => 100.00,
            'InvoiceTransactions' => [['AuthorizationId' => 'AUTH-2']],
            'InvoiceReference' => 'MF-REF-2',
            'InvoiceId' => 55555,
            'InvoiceStatus' => 'Paid',
        ];

        $controller = app(PaymentController::class);
        $reflection = new \ReflectionMethod($controller, 'processMyFatoorahPaymentCompletion');
        $reflection->setAccessible(true);

        try {
            $reflection->invokeArgs($controller, [$payment, $statusData, 'invoice', null, false]);
            $this->fail('Expected a PostingException to propagate.');
        } catch (PostingException $e) {
            $this->assertInstanceOf(UnmappedPurposeException::class, $e);
        }

        $this->assertSame('initiate', $payment->fresh()->status);
        $this->assertSame(0, Transaction::where('payment_id', $payment->id)->count());
    }
}
