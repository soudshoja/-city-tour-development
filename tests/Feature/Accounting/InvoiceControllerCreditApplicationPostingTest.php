<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\CreditApplicationTotalMismatchException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Http\Controllers\InvoiceController;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Transaction;
use App\Services\Accounting\CreditApplicationInput;
use App\Services\Accounting\PaymentIdempotencyKey;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionMethod;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * KEY: ic-credit. Cuts InvoiceController::createCreditPaymentCOA() onto {@see
 * \App\Services\Accounting\PostingSeam} (R3 route-to-legacy decision), via the shared {@see
 * \App\Services\Accounting\CreditApplicationDraftBuilder} (design call E1/E2/E3 — W2b draft-builder
 * lane). Read InvoiceController::createCreditPaymentCOA() and its own docblock in full before
 * touching this file — the legacy closure inside it must stay byte-for-byte HEAD parity.
 *
 * $appliedPayments (as built by both of InvoiceController::savePartial()'s own producers) is
 * handled per W2c orchestrator ruling B-2: the `PaymentApplicationService::
 * linkPaymentsToInvoicePartial()` branch now threads a real `payment_applications.id` through
 * (source {@see CreditApplicationInput::SOURCE_PAYMENT_APPLICATION}), while the "no specific
 * allocations" fallback branch — which creates a bare Credit and no PaymentApplication row at
 * all — keys on the InvoicePartial's own id instead (source
 * {@see CreditApplicationInput::SOURCE_PARTIAL}). See the cutover method's own docblock.
 */
class InvoiceControllerCreditApplicationPostingTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * @return array{user: \App\Models\User, company: Company, branch: \App\Models\Branch, agent: \App\Models\Agent, client: \App\Models\Client}
     */
    private function makeTenantWithChart(): array
    {
        $tenant = $this->createTenant();
        CoaSeeder::run($tenant['company']->id);

        return $tenant;
    }

    /**
     * Pre-seeds the "invoice recognition" Transaction savePartial() looks for
     * (`reference_type = 'Invoice'`), so the call under test takes the `else` branch
     * (recalculateInvoiceCOA — a safe no-op with no journal_entries.invoice_id rows yet) instead of
     * the heavy Task-lookup branch, which is irrelevant to the credit-application cutover under
     * test.
     */
    private function makeInvoiceWithExistingTransaction(array $tenant, float $amount): Invoice
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => $amount,
            'sub_amount' => $amount,
            'currency' => 'KWD',
        ]);

        Transaction::create([
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'entity_id' => $tenant['company']->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $amount,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);

        return $invoice;
    }

    private function makeTopupCredit(array $tenant, float $amount): Credit
    {
        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'status' => 'completed',
        ]);

        return Credit::create([
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'client_id' => $tenant['client']->id,
            'payment_id' => $payment->id,
            'type' => Credit::TOPUP,
            'amount' => $amount,
            'description' => 'Topup credit for ic-credit posting test',
        ]);
    }

    private function savePartialRequest(Invoice $invoice, float $amount, array $allocations, Company $company): Request
    {
        return Request::create('/invoice/partial', 'POST', [
            'invoiceId' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'clientId' => $invoice->client_id,
            'date' => now()->toDateString(),
            'amount' => $amount,
            'type' => 'full',
            'gateway' => 'Credit',
            'credit' => true,
            'payment_allocations' => $allocations,
            'companyId' => $company->id,
        ]);
    }

    private function resolvedAccountId(Company $company, string $purposeCode): ?int
    {
        return DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', $purposeCode)
            ->value('account_id');
    }

    /** @param array<int, array<string, mixed>> $appliedPayments */
    private function invokeCreateCreditPaymentCOA(
        InvoiceController $controller,
        Invoice $invoice,
        array $appliedPayments,
        float $totalAmount,
        ?int $invoicePartialId
    ): mixed {
        $method = new ReflectionMethod(InvoiceController::class, 'createCreditPaymentCOA');
        $method->setAccessible(true);

        return $method->invoke($controller, $invoice, $appliedPayments, $totalAmount, $invoicePartialId);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path — HEAD parity, byte-for-byte.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Prove red by revert: reverting the cutover (calling JournalEntry::create()/
     * Transaction::create() directly, no seam) makes this pass identically — this test pins
     * CURRENT (pre- and post-seam-cutover) OFF-path behaviour via the REAL savePartial() ->
     * PaymentApplicationService::linkPaymentsToInvoicePartial() -> createCreditPaymentCOA() chain,
     * with two genuinely distinct applied payments (N=2).
     */
    public function test_off_path_two_real_applications_via_save_partial_matches_head_shape(): void
    {
        config(['accounting.engine.enabled' => false]);
        // Migration default: posting_engine_enabled = false — left untouched.

        $tenant = $this->makeTenantWithChart();
        (new SystemAccountsSeeder())->run(); // test-assertion convenience only — see class docblock.

        $liabilityAccountId = $this->resolvedAccountId($tenant['company'], 'CLIENT_ADVANCE');
        $receivableAccountId = $this->resolvedAccountId($tenant['company'], 'RECEIVABLE_CONTROL');
        $this->assertNotNull($liabilityAccountId);
        $this->assertNotNull($receivableAccountId);

        $liabilityBalanceBefore = (float) DB::table('accounts')->where('id', $liabilityAccountId)->value('actual_balance');
        $receivableBalanceBefore = (float) DB::table('accounts')->where('id', $receivableAccountId)->value('actual_balance');

        $invoice = $this->makeInvoiceWithExistingTransaction($tenant, 80.0);
        $credit1 = $this->makeTopupCredit($tenant, 50.0);
        $credit2 = $this->makeTopupCredit($tenant, 30.0);

        $request = $this->savePartialRequest($invoice, 80.0, [
            ['credit_id' => $credit1->id, 'amount' => 50.0],
            ['credit_id' => $credit2->id, 'amount' => 30.0],
        ], $tenant['company']);

        $this->actingAs($tenant['user']);
        $response = app(InvoiceController::class)->savePartial($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['success']);

        $transaction = DB::table('transactions')
            ->where('company_id', $tenant['company']->id)
            ->where('invoice_id', $invoice->id)
            ->where('reference_type', 'Payment')
            ->first();

        $this->assertNotNull($transaction, 'Legacy path must still write the credit-payment transactions header exactly as HEAD does.');
        $this->assertSame('debit', $transaction->transaction_type);
        $this->assertEquals(80.0, (float) $transaction->amount);
        // Legacy code literally writes 'Client' (capital C, InvoiceController.php:2813), but
        // transactions.entity_type is a MySQL ENUM('company','branch','agent','client') — MySQL
        // matches an inserted string against the enum list case-insensitively and stores the
        // list's OWN canonical casing, so the persisted value is lowercase 'client'. Pre-existing
        // HEAD behaviour, not something this cutover changed.
        $this->assertSame('client', $transaction->entity_type);
        $this->assertNull($transaction->payment_id, 'Legacy header never sets payment_id — trap 1.');
        $this->assertNull($transaction->idempotency_key, 'A legacy transaction never carries an idempotency_key — proves the ENGINE did not run.');

        $debitLines = DB::table('journal_entries')
            ->where('transaction_id', $transaction->id)
            ->where('account_id', $liabilityAccountId)
            ->orderBy('id')
            ->get();
        $creditLines = DB::table('journal_entries')
            ->where('transaction_id', $transaction->id)
            ->where('account_id', $receivableAccountId)
            ->get();

        $this->assertCount(2, $debitLines, 'N=2 debit lines, one per positive applied payment.');
        $this->assertCount(1, $creditLines, 'Exactly one credit line for the sum.');

        $this->assertEquals(50.0, (float) $debitLines[0]->debit);
        $this->assertEquals(0.0, (float) $debitLines[0]->credit);
        $this->assertSame('payable', $debitLines[0]->type);
        $this->assertEqualsWithDelta($liabilityBalanceBefore - 50.0, (float) $debitLines[0]->balance, 0.0005, 'HEAD DEFECT preserved on purpose: compound `actual_balance -=` arithmetic.');

        $this->assertEquals(30.0, (float) $debitLines[1]->debit);
        $this->assertEqualsWithDelta($liabilityBalanceBefore - 80.0, (float) $debitLines[1]->balance, 0.0005, 'Second debit compounds on top of the first, exactly like HEAD.');

        $this->assertEquals(80.0, (float) $creditLines[0]->credit);
        $this->assertEquals(0.0, (float) $creditLines[0]->debit);
        $this->assertSame('receivable', $creditLines[0]->type);
        $this->assertEqualsWithDelta($receivableBalanceBefore - 80.0, (float) $creditLines[0]->balance, 0.0005);
    }

    /**
     * Direct-call parity for the `if ($amountApplied <= 0) continue;` skip, both legacy copies
     * apply — not reachable via the real savePartial() flow (PaymentApplicationService never
     * produces a zero/negative applied amount for a legitimate allocation), so exercised directly
     * against the cutover method itself, which must still run the untouched legacy body when OFF.
     */
    public function test_off_path_direct_call_skips_zero_amount_application_exactly_like_head(): void
    {
        config(['accounting.engine.enabled' => false]);

        $tenant = $this->makeTenantWithChart();
        (new SystemAccountsSeeder())->run();
        $liabilityAccountId = $this->resolvedAccountId($tenant['company'], 'CLIENT_ADVANCE');
        $receivableAccountId = $this->resolvedAccountId($tenant['company'], 'RECEIVABLE_CONTROL');

        $invoice = $this->makeInvoiceWithExistingTransaction($tenant, 40.0);
        // W2c fix (B-2): CreditApplicationInput::$id must be a real, positive id — the direct
        // call below has no PaymentApplication row to key on, so it needs a real InvoicePartial
        // (source: CreditApplicationInput::SOURCE_PARTIAL) instead of the old bare-0 sentinel.
        $invoicePartial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $tenant['client']->id,
            'amount' => 40.0,
            'status' => 'paid',
            'expiry_date' => now()->toDateString(),
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'service_charge' => 0,
        ]);

        $appliedPayments = [
            ['credit_id' => 111, 'voucher_number' => 'Client Credit', 'amount_applied' => 40.0, 'invoice_partial_id' => null],
            ['credit_id' => 222, 'voucher_number' => 'Client Credit', 'amount_applied' => 0.0, 'invoice_partial_id' => null],
        ];

        $result = $this->invokeCreateCreditPaymentCOA(app(InvoiceController::class), $invoice, $appliedPayments, 40.0, $invoicePartial->id);

        $this->assertInstanceOf(Transaction::class, $result);

        $debitLines = DB::table('journal_entries')
            ->where('transaction_id', $result->id)
            ->where('account_id', $liabilityAccountId)
            ->get();
        $creditLines = DB::table('journal_entries')
            ->where('transaction_id', $result->id)
            ->where('account_id', $receivableAccountId)
            ->get();

        $this->assertCount(1, $debitLines, 'The zero-amount application must be skipped — only one debit line, matching HEAD.');
        $this->assertEquals(40.0, (float) $debitLines[0]->debit);
        $this->assertCount(1, $creditLines);
        $this->assertEquals(40.0, (float) $creditLines[0]->credit);
    }

    /**
     * B-1 (W2c fix): the negative-amount case HEAD has always tolerated silently must remain
     * tolerated on the OFF path after the seam cutover — `savePartial()`'s own validator is
     * `'amount' => 'required'` with no `numeric`/`min:`, so this is genuinely reachable, not
     * theoretical (W2b lead report §5, B-1). Before the fix, {@see CreditApplicationDraftBuilder::build()}'s
     * mismatch throw escaped uncaught on this exact input, turning HEAD's 200 into an HTTP 500.
     */
    public function test_off_path_negative_amount_is_tolerated_exactly_like_head(): void
    {
        config(['accounting.engine.enabled' => false]);

        $tenant = $this->makeTenantWithChart();
        (new SystemAccountsSeeder())->run();
        $liabilityAccountId = $this->resolvedAccountId($tenant['company'], 'CLIENT_ADVANCE');
        $receivableAccountId = $this->resolvedAccountId($tenant['company'], 'RECEIVABLE_CONTROL');

        $invoice = $this->makeInvoiceWithExistingTransaction($tenant, 50.0);
        $invoicePartial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $tenant['client']->id,
            'amount' => 50.0,
            'status' => 'paid',
            'expiry_date' => now()->toDateString(),
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'service_charge' => 0,
        ]);

        $appliedPayments = [
            ['credit_id' => 999, 'voucher_number' => 'Client Credit', 'amount_applied' => -50.0, 'invoice_partial_id' => null],
        ];

        Log::spy();

        // The caller total ITSELF is the negative figure — mirrors savePartial()'s own
        // array_sum(array_column($appliedPayments, 'amount_applied')) for exactly this input.
        $result = $this->invokeCreateCreditPaymentCOA(app(InvoiceController::class), $invoice, $appliedPayments, -50.0, $invoicePartial->id);

        $this->assertInstanceOf(Transaction::class, $result, 'HEAD tolerates this silently — it must not throw on the OFF path.');
        $this->assertEqualsWithDelta(-50.0, (float) $result->amount, 0.0005);
        $this->assertNull($result->idempotency_key, 'A legacy transaction never carries an idempotency_key — proves the ENGINE did not run.');

        $debitLines = DB::table('journal_entries')->where('transaction_id', $result->id)->where('account_id', $liabilityAccountId)->get();
        $creditLines = DB::table('journal_entries')->where('transaction_id', $result->id)->where('account_id', $receivableAccountId)->get();

        $this->assertCount(0, $debitLines, 'The negative application is skipped by the legacy `<= 0` guard, exactly like a zero amount.');
        $this->assertCount(1, $creditLines, 'HEAD still writes the unconditional credit line even with nothing on the debit side.');
        $this->assertEqualsWithDelta(-50.0, (float) $creditLines[0]->credit, 0.0005);

        Log::shouldHaveReceived('warning')->once()->with(
            'accounting.builder_validation_offpath',
            Mockery::on(function (array $context) use ($tenant, $invoice) {
                return $context['feeder'] === 'invoice.credit-apply'
                    && $context['company_id'] === $tenant['company']->id
                    && $context['invoice_id'] === $invoice->id
                    && $context['exception_class'] === CreditApplicationTotalMismatchException::class;
            })
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path — balanced document via purpose codes, null payment_id, expected idempotency key.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_posts_three_balanced_lines_with_null_payment_id_and_expected_key(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->makeTenantWithChart();
        $this->trackCompanyForInvariants($tenant['company']->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--enable' => true]);

        $liabilityAccountId = $this->resolvedAccountId($tenant['company'], 'CLIENT_ADVANCE');
        $receivableAccountId = $this->resolvedAccountId($tenant['company'], 'RECEIVABLE_CONTROL');
        $this->assertNotNull($liabilityAccountId);
        $this->assertNotNull($receivableAccountId);

        $invoice = $this->makeInvoiceWithExistingTransaction($tenant, 80.0);
        $credit1 = $this->makeTopupCredit($tenant, 50.0);
        $credit2 = $this->makeTopupCredit($tenant, 30.0);

        $request = $this->savePartialRequest($invoice, 80.0, [
            ['credit_id' => $credit1->id, 'amount' => 50.0],
            ['credit_id' => $credit2->id, 'amount' => 30.0],
        ], $tenant['company']);

        $this->actingAs($tenant['user']);
        $response = app(InvoiceController::class)->savePartial($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['success']);

        // W2c fix (B-2): this flow goes through PaymentApplicationService::
        // linkPaymentsToInvoicePartial(), which now threads a real payment_applications.id
        // through for each allocation — so the key is 'pa'-sourced on those real ids, not
        // 'partial'-sourced on the InvoicePartial's own id.
        $invoicePartial = InvoicePartial::where('invoice_id', $invoice->id)->firstOrFail();
        $paymentApplicationIds = PaymentApplication::where('invoice_partial_id', $invoicePartial->id)
            ->pluck('id')
            ->all();
        $this->assertCount(2, $paymentApplicationIds);
        $expectedKey = PaymentIdempotencyKey::forCreditApplication(
            $invoice->id,
            array_map(fn (int $id) => [CreditApplicationInput::SOURCE_PAYMENT_APPLICATION, $id], $paymentApplicationIds)
        );

        $transaction = DB::table('transactions')
            ->where('company_id', $tenant['company']->id)
            ->where('idempotency_key', $expectedKey)
            ->first();

        $this->assertNotNull($transaction, 'The engine path must post one transaction keyed on credit-apply:invoice:{id}:pa:{paymentApplicationIds}.');
        $this->assertSame('JV', $transaction->doc_type);
        $this->assertSame('Payment', $transaction->reference_type, 'sourceType is pinned to Payment — trap 1.');
        $this->assertNull($transaction->payment_id, 'paymentId is left null on every draft this builder produces — trap 1.');
        $this->assertSame($invoice->id, $transaction->invoice_id);

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(3, $lines, 'N=2 debit lines + 1 credit line.');

        $debitLines = $lines->where('account_id', $liabilityAccountId);
        $creditLines = $lines->where('account_id', $receivableAccountId);

        $this->assertCount(2, $debitLines);
        $this->assertCount(1, $creditLines);

        $totalDebit = (float) $debitLines->sum('debit');
        $totalCredit = (float) $creditLines->sum('credit');
        $this->assertEqualsWithDelta(80.0, $totalDebit, 0.0005);
        $this->assertEqualsWithDelta(80.0, $totalCredit, 0.0005);
        $this->assertEqualsWithDelta(0.0, $totalDebit - $totalCredit, 0.0005, 'Balanced document.');

        foreach ($debitLines as $line) {
            $this->assertSame('payable', $line->type, 'ledgerType carries HEAD\'s own legacy vocabulary — trap 4.');
        }
        foreach ($creditLines as $line) {
            $this->assertSame('receivable', $line->type);
        }
    }

    /**
     * S1 — presenting the identical business event (same invoice, same $appliedPayments, same
     * derived invoicePartialId -> same idempotency key) to the seam a second time must not create
     * a second transaction or duplicate journal_entries rows, and must return a bare null rather
     * than invoking the legacy closure.
     */
    /**
     * Engine-level idempotency: presenting the identical business event (same key) to the seam a
     * second time WHILE THE ENGINE IS STILL ON is handled by PostingService's own idempotency
     * check, which returns the EXISTING PostedDocument (never a bare null — see PostingSeam's own
     * docblock: bare null is the OFF-path-only S1 case, covered separately below). No second
     * transaction or duplicated journal_entries rows either way.
     */
    public function test_on_path_rerun_same_key_while_still_enabled_returns_the_existing_transaction(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->makeTenantWithChart();
        $this->trackCompanyForInvariants($tenant['company']->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--enable' => true]);

        [$invoice, $invoicePartial, $appliedPayments, $controller] = $this->makeSingleApplicationFixture($tenant, 40.0);

        $first = $this->invokeCreateCreditPaymentCOA($controller, $invoice, $appliedPayments, 40.0, $invoicePartial->id);
        $this->assertNotNull($first, 'First post must succeed and return the engine Transaction.');

        $transactionCountAfterFirst = DB::table('transactions')->where('company_id', $tenant['company']->id)->count();
        $lineCountAfterFirst = DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count();

        $second = $this->invokeCreateCreditPaymentCOA($controller, $invoice, $appliedPayments, 40.0, $invoicePartial->id);

        $this->assertNotNull($second, 'Engine-level dedup returns the pre-existing PostedDocument\'s Transaction, never null, while the engine stays ON.');
        $this->assertSame($first->id, $second->id, 'The retried call must resolve to the SAME transaction, not a new one.');
        $this->assertSame(
            $transactionCountAfterFirst,
            DB::table('transactions')->where('company_id', $tenant['company']->id)->count(),
            'A retried post with the same idempotency key must not create a second transaction.'
        );
        $this->assertSame(
            $lineCountAfterFirst,
            DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count(),
            'A retried post with the same idempotency key must not duplicate journal_entries rows.'
        );
    }

    /**
     * S1 (PostingSeam docblock, W1.1 FIX ROUND): if the engine already posted this exact
     * (company_id, idempotency_key) pair and a kill-switch flip then takes the company back to the
     * OFF path, a retried call must NOT re-run the legacy closure (that would double-post the same
     * real-world event) — it must return a bare null instead. This is the ONE case PostingSeam::
     * post() can return null.
     */
    public function test_on_path_rerun_same_key_after_kill_switch_flip_returns_null_via_s1(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->makeTenantWithChart();
        $this->trackCompanyForInvariants($tenant['company']->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--enable' => true]);

        [$invoice, $invoicePartial, $appliedPayments, $controller] = $this->makeSingleApplicationFixture($tenant, 40.0);

        $first = $this->invokeCreateCreditPaymentCOA($controller, $invoice, $appliedPayments, 40.0, $invoicePartial->id);
        $this->assertNotNull($first, 'First post must succeed and return the engine Transaction.');

        $transactionCountAfterFirst = DB::table('transactions')->where('company_id', $tenant['company']->id)->count();
        $lineCountAfterFirst = DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count();

        // Kill-switch flip: this company goes back to the OFF path.
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--disable' => true]);

        $second = $this->invokeCreateCreditPaymentCOA($controller, $invoice, $appliedPayments, 40.0, $invoicePartial->id);

        $this->assertNull($second, 'S1: a retried post under the identical key, now routed OFF by the flag flip, must return a bare null — the legacy closure must never run for an already-posted event.');
        $this->assertSame(
            $transactionCountAfterFirst,
            DB::table('transactions')->where('company_id', $tenant['company']->id)->count(),
            'S1 must prevent the legacy closure from double-posting a second transaction.'
        );
        $this->assertSame(
            $lineCountAfterFirst,
            DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count()
        );
    }

    /**
     * @return array{0: Invoice, 1: InvoicePartial, 2: array<int, array<string, mixed>>, 3: InvoiceController}
     */
    private function makeSingleApplicationFixture(array $tenant, float $amount): array
    {
        $invoice = $this->makeInvoiceWithExistingTransaction($tenant, $amount);
        $invoicePartial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $tenant['client']->id,
            'amount' => $amount,
            'status' => 'paid',
            'expiry_date' => now()->toDateString(),
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'service_charge' => 0,
        ]);

        $appliedPayments = [
            ['payment_id' => null, 'voucher_number' => 'Client Credit', 'amount_applied' => $amount],
        ];

        $controller = app(InvoiceController::class);
        $this->actingAs($tenant['user']);

        return [$invoice, $invoicePartial, $appliedPayments, $controller];
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Failure handling — loud, never a partial commit.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * A caller-supplied total that disagrees with the sum of the applications actually posted
     * (the builder's own refusal, design call E1) must propagate as a PostingException out of
     * whatever transaction wraps this call — savePartial()'s own DB::transaction() in production,
     * reproduced here directly so the InvoicePartial/PaymentApplication rows this test creates
     * inside the SAME wrapping transaction (mirroring savePartial()'s own shape) prove the
     * rollback boundary, not merely that the exception was thrown.
     */
    public function test_caller_total_mismatch_throws_and_rolls_back_invoice_partial_and_applications(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->makeTenantWithChart();
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--enable' => true]);

        $invoice = $this->makeInvoiceWithExistingTransaction($tenant, 80.0);
        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'status' => 'completed',
        ]);

        $invoicePartialCountBefore = InvoicePartial::count();
        $paymentApplicationCountBefore = PaymentApplication::count();
        $transactionCountBefore = DB::table('transactions')->where('company_id', $tenant['company']->id)->count();

        $controller = app(InvoiceController::class);
        $caught = null;

        try {
            DB::transaction(function () use ($controller, $invoice, $tenant, $payment) {
                // Mirrors what savePartial() would already have committed, in the SAME
                // transaction, before reaching STEP 2.
                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $tenant['client']->id,
                    'amount' => 80.0,
                    'status' => 'paid',
                    'expiry_date' => now()->toDateString(),
                    'type' => 'full',
                    'payment_gateway' => 'Credit',
                    'service_charge' => 0,
                ]);

                PaymentApplication::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_partial_id' => $invoicePartial->id,
                    'amount' => 80.0,
                    'applied_by' => $tenant['user']->id,
                    'applied_at' => now(),
                ]);

                $appliedPayments = [
                    ['payment_id' => $payment->id, 'voucher_number' => 'TOPUP', 'amount_applied' => 80.0],
                ];

                // Caller total (999) deliberately disagrees with the sum of applied payments
                // actually posted (80) — the exact class of caller bug
                // CreditApplicationTotalMismatchException exists to catch loudly.
                $this->invokeCreateCreditPaymentCOA($controller, $invoice, $appliedPayments, 999.0, $invoicePartial->id);
            });
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(CreditApplicationTotalMismatchException::class, $caught);
        $this->assertInstanceOf(\App\Exceptions\Accounting\PostingException::class, $caught);

        $this->assertSame($invoicePartialCountBefore, InvoicePartial::count(), 'No partial commit — the InvoicePartial created inside the failed transaction must be rolled back.');
        $this->assertSame($paymentApplicationCountBefore, PaymentApplication::count(), 'No partial commit — the PaymentApplication created inside the failed transaction must be rolled back.');
        $this->assertSame($transactionCountBefore, DB::table('transactions')->where('company_id', $tenant['company']->id)->count(), 'Nothing must be committed to transactions either.');
    }

    /**
     * An unmapped purpose code (a genuine engine correctness failure, e.g. RECEIVABLE_CONTROL not
     * yet resolved for this company) must propagate as a PostingException, logged CRITICAL by
     * PostingSeam, and leave nothing committed — never silently downgraded to the legacy closure
     * (R3: no silent double-post path).
     */
    public function test_unmapped_purpose_engine_failure_propagates_and_rolls_back(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->makeTenantWithChart();
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--enable' => true]);

        $this->assertNotNull($this->resolvedAccountId($tenant['company'], 'RECEIVABLE_CONTROL'), 'Sanity: real seeders must map RECEIVABLE_CONTROL before we delete it on purpose.');

        DB::table('system_accounts')
            ->where('company_id', $tenant['company']->id)
            ->where('purpose_code', 'RECEIVABLE_CONTROL')
            ->delete();

        $invoice = $this->makeInvoiceWithExistingTransaction($tenant, 40.0);
        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'status' => 'completed',
        ]);

        $invoicePartialCountBefore = InvoicePartial::count();
        $paymentApplicationCountBefore = PaymentApplication::count();
        $transactionCountBefore = DB::table('transactions')->where('company_id', $tenant['company']->id)->count();
        $journalCountBefore = DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count();

        Log::spy();

        $controller = app(InvoiceController::class);
        $caught = null;

        try {
            DB::transaction(function () use ($controller, $invoice, $tenant, $payment) {
                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $tenant['client']->id,
                    'amount' => 40.0,
                    'status' => 'paid',
                    'expiry_date' => now()->toDateString(),
                    'type' => 'full',
                    'payment_gateway' => 'Credit',
                    'service_charge' => 0,
                ]);

                PaymentApplication::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_partial_id' => $invoicePartial->id,
                    'amount' => 40.0,
                    'applied_by' => $tenant['user']->id,
                    'applied_at' => now(),
                ]);

                $appliedPayments = [
                    ['payment_id' => $payment->id, 'voucher_number' => 'TOPUP', 'amount_applied' => 40.0],
                ];

                $this->invokeCreateCreditPaymentCOA($controller, $invoice, $appliedPayments, 40.0, $invoicePartial->id);
            });
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(UnmappedPurposeException::class, $caught);

        Log::shouldHaveReceived('critical')->once()->with(
            'accounting.engine_failure',
            Mockery::on(function (array $context) use ($tenant) {
                return $context['feeder'] === 'invoice.credit-apply'
                    && $context['company_id'] === $tenant['company']->id
                    && $context['exception_class'] === UnmappedPurposeException::class;
            })
        );

        $this->assertSame($invoicePartialCountBefore, InvoicePartial::count(), 'A genuine engine correctness failure must not leave a partial InvoicePartial row.');
        $this->assertSame($paymentApplicationCountBefore, PaymentApplication::count(), 'A genuine engine correctness failure must not leave a partial PaymentApplication row.');
        $this->assertSame($transactionCountBefore, DB::table('transactions')->where('company_id', $tenant['company']->id)->count(), 'Never fall back to legacy on a genuine engine failure — R3.');
        $this->assertSame($journalCountBefore, DB::table('journal_entries')->where('company_id', $tenant['company']->id)->count());
    }
}
