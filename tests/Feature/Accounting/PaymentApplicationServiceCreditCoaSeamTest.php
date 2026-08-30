<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\CreditApplicationTotalMismatchException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Transaction;
use App\Services\Accounting\CreditApplicationInput;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\PaymentApplicationService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * KEY: pas-credit. Cuts {@see PaymentApplicationService::createCreditPaymentCOA()} onto {@see
 * \App\Services\Accounting\PostingSeam} via the shared {@see
 * \App\Services\Accounting\CreditApplicationDraftBuilder} (design calls E1-E3, W2b draft-builder
 * build). Read that method in full before touching this file.
 *
 * OFF path (test_off_path_*): the legacy closure is HEAD's own body, byte-identical in every
 * row/value/order it can produce — this file's OFF-path tests reproduce the exact legacy account
 * hierarchy (Liabilities > Advances > Client > Payment Gateway; Accounts Receivable > Clients)
 * both legacy `createCreditPaymentCOA()` copies resolve by name/parent_id, never a purpose-code
 * chart.
 *
 * ON path (test_on_path_*): extends AccountingTestCase for the C1 trial-balance invariant. REAL
 * CoaSeeder::run() + SystemAccountsSeeder::run() are used for every ON-path test that actually
 * posts, never a hand-inserted system_accounts row.
 */
class PaymentApplicationServiceCreditCoaSeamTest extends AccountingTestCase
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

    private function makeInvoice(array $tenant, float $amount = 100.0): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => $amount,
            'sub_amount' => $amount,
            'currency' => 'KWD',
        ]);
    }

    /**
     * Company::TOPUP credit source with $amount available, mirroring
     * PaymentApplicationTenantIsolationTest::createCreditFor().
     */
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
            'description' => 'Topup credit for pas-credit seam test',
        ]);
    }

    /** Legacy account hierarchy both `createCreditPaymentCOA()` copies resolve by name/parent_id. */
    private function makeLegacyAccounts(int $companyId): void
    {
        $liabilities = Account::create(['name' => 'Liabilities', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId, 'parent_id' => null]);
        $advances = Account::create(['name' => 'Advances', 'level' => 2, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId, 'parent_id' => $liabilities->id]);
        $client = Account::create(['name' => 'Client', 'level' => 3, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId, 'parent_id' => $advances->id]);
        Account::create(['name' => 'Payment Gateway', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId, 'parent_id' => $client->id]);

        $accountsReceivable = Account::create(['name' => 'Accounts Receivable', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId, 'parent_id' => null]);
        Account::create(['name' => 'Clients', 'level' => 2, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId, 'parent_id' => $accountsReceivable->id]);
    }

    private function makeOnPathTenant(): array
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        return $tenant;
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path — HEAD parity.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_full_credit_application_matches_head_shape(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->makeLegacyAccounts($company->id);
        $invoice = $this->makeInvoice($tenant, 100.0);
        $credit = $this->makeTopupCredit($tenant, 100.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $result = $service->applyPaymentsToInvoice($invoice->id, [
            ['credit_id' => $credit->id, 'amount' => 100.0],
        ], 'full');

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $transaction = Transaction::where('invoice_id', $invoice->id)
            ->where('reference_type', 'Payment')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertNull($transaction->idempotency_key, 'OFF path never sets an idempotency key');
        $this->assertNull($transaction->payment_id);

        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $entries);

        $debitLine = $entries->firstWhere('type', 'payable');
        $creditLine = $entries->firstWhere('type', 'receivable');
        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEqualsWithDelta(100.0, (float) $debitLine->debit, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $creditLine->credit, 0.001);
        $this->assertSame('Payment Gateway', $debitLine->name);
        $this->assertSame('Clients', $creditLine->name);

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_off_path_two_applications_matches_head_shape(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->makeLegacyAccounts($company->id);
        $invoice = $this->makeInvoice($tenant, 130.0);
        $creditA = $this->makeTopupCredit($tenant, 80.0);
        $creditB = $this->makeTopupCredit($tenant, 50.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $result = $service->applyPaymentsToInvoice($invoice->id, [
            ['credit_id' => $creditA->id, 'amount' => 80.0],
            ['credit_id' => $creditB->id, 'amount' => 50.0],
        ], 'full');

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $transaction = Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->first();
        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();

        // HEAD shape: N debit lines (one per applied payment) + 1 credit line.
        $this->assertCount(3, $entries);
        $debitEntries = $entries->where('type', 'payable');
        $creditEntries = $entries->where('type', 'receivable');
        $this->assertCount(2, $debitEntries);
        $this->assertCount(1, $creditEntries);
        $this->assertEqualsWithDelta(130.0, (float) $debitEntries->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(130.0, (float) $creditEntries->sum('credit'), 0.001);
    }

    /**
     * B-1 (W2c fix): the negative-amount case HEAD has always tolerated silently must remain
     * tolerated on the OFF path now that the old `isEnabledFor()` pre-check is gone — see this
     * method's own docblock (W2b lead report §5, B-1). Exercised directly against the protected
     * method with a hand-built $appliedPayments/$paymentApplication pair, mirroring this file's
     * own existing mismatch-probe shape.
     */
    public function test_off_path_negative_amount_is_tolerated_exactly_like_head(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->makeLegacyAccounts($company->id);
        $invoice = $this->makeInvoice($tenant, 50.0);
        $credit = $this->makeTopupCredit($tenant, 50.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $paymentApplication = PaymentApplication::create([
            'payment_id' => $credit->payment_id,
            'credit_id' => $credit->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => null,
            'amount' => -50.0,
            'applied_by' => $tenant['user']->id,
            'applied_at' => now(),
            'notes' => 'negative amount OFF-path parity probe',
        ]);

        $appliedPayments = [[
            'payment_application_id' => $paymentApplication->id,
            'credit_id' => $credit->id,
            'payment_id' => $credit->payment_id,
            'refund_id' => null,
            'voucher_number' => 'TOPUP',
            'amount_applied' => -50.0,
            'remaining_balance' => 0.0,
            'invoice_partial_id' => null,
        ]];

        Log::spy();

        $result = $this->invokePrivate($service, 'createCreditPaymentCOA', [$invoice, $appliedPayments, -50.0]);

        $this->assertInstanceOf(Transaction::class, $result, 'HEAD tolerates this silently — it must not throw on the OFF path.');
        $this->assertEqualsWithDelta(-50.0, (float) $result->amount, 0.0005);
        $this->assertNull($result->idempotency_key, 'A legacy transaction never carries an idempotency_key — proves the ENGINE did not run.');

        $entries = JournalEntry::where('transaction_id', $result->id)->get();
        $this->assertCount(1, $entries, 'the negative debit is skipped by the legacy `<= 0` guard; only the unconditional credit line is written.');
        $this->assertSame('receivable', $entries->first()->type);
        $this->assertEqualsWithDelta(-50.0, (float) $entries->first()->credit, 0.0005);

        Log::shouldHaveReceived('warning')->once()->with(
            'accounting.builder_validation_offpath',
            Mockery::on(function (array $context) use ($company, $invoice) {
                return $context['feeder'] === 'payment-application.credit-apply'
                    && $context['company_id'] === $company->id
                    && $context['invoice_id'] === $invoice->id
                    && $context['exception_class'] === CreditApplicationTotalMismatchException::class;
            })
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path — balanced document via the shared draft builder, correct leaves, idempotent.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_full_credit_application_posts_a_balanced_document(): void
    {
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $invoice = $this->makeInvoice($tenant, 100.0);
        $credit = $this->makeTopupCredit($tenant, 100.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $result = $service->applyPaymentsToInvoice($invoice->id, [
            ['credit_id' => $credit->id, 'amount' => 100.0],
        ], 'full');

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $paymentApplicationId = PaymentApplication::where('invoice_id', $invoice->id)->value('id');
        $this->assertNotNull($paymentApplicationId);

        $transaction = Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->first();
        $this->assertNotNull($transaction);
        $this->assertNull($transaction->payment_id, 'trap 1 — header payment_id must stay NULL');
        $this->assertSame(
            PaymentIdempotencyKey::forCreditApplication($invoice->id, [[CreditApplicationInput::SOURCE_PAYMENT_APPLICATION, $paymentApplicationId]]),
            $transaction->idempotency_key
        );

        $advanceLeaf = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2632')->first();
        $receivableLeaf = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->first();
        $this->assertNotNull($advanceLeaf);
        $this->assertNotNull($receivableLeaf);

        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $entries);

        $debitLine = $entries->firstWhere('debit', '>', 0);
        $creditLine = $entries->firstWhere('credit', '>', 0);
        $this->assertSame($advanceLeaf->id, $debitLine->account_id);
        $this->assertSame('payable', $debitLine->type);
        $this->assertSame($receivableLeaf->id, $creditLine->account_id);
        $this->assertSame('receivable', $creditLine->type);
        $this->assertEqualsWithDelta(100.0, (float) $debitLine->debit, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $creditLine->credit, 0.001);
        $this->assertNull($debitLine->balance, 'E5 — journal_entries.balance stays NULL on the ON path');

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_on_path_two_applications_post_three_balanced_lines(): void
    {
        $tenant = $this->makeOnPathTenant();
        $invoice = $this->makeInvoice($tenant, 130.0);
        $creditA = $this->makeTopupCredit($tenant, 80.0);
        $creditB = $this->makeTopupCredit($tenant, 50.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $result = $service->applyPaymentsToInvoice($invoice->id, [
            ['credit_id' => $creditA->id, 'amount' => 80.0],
            ['credit_id' => $creditB->id, 'amount' => 50.0],
        ], 'full');

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unexpected failure');

        $applicationIds = PaymentApplication::where('invoice_id', $invoice->id)->pluck('id')->sort()->values()->all();
        $this->assertCount(2, $applicationIds);

        $transaction = Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->first();
        $this->assertSame(
            PaymentIdempotencyKey::forCreditApplication(
                $invoice->id,
                array_map(fn (int $id) => [CreditApplicationInput::SOURCE_PAYMENT_APPLICATION, $id], $applicationIds)
            ),
            $transaction->idempotency_key
        );

        $entries = JournalEntry::where('transaction_id', $transaction->id)->get();
        $this->assertCount(3, $entries);
        $this->assertEqualsWithDelta((float) $entries->sum('debit'), (float) $entries->sum('credit'), 0.0005);
        $this->assertEqualsWithDelta(130.0, (float) $entries->sum('credit'), 0.001);
    }

    /**
     * Idempotency: retrying the identical business event (same invoice, same set of
     * `payment_applications` ids) must never double-post. Exercised by calling the protected
     * `createCreditPaymentCOA()` directly twice with the identical $appliedPayments/$totalAmount.
     * On the ON path this is PostingService::post()'s OWN idempotency-key lookup (step 1) —
     * NOT PostingSeam's S1 (a bare `null` return), which is an OFF-path-only case (see
     * PostingSeam::post()'s own docblock: "the ONE case where post() can return a bare null").
     * The second call must return the SAME Transaction the first call created, and no second
     * JournalEntry pair may appear.
     */
    public function test_on_path_repeating_the_same_application_set_is_idempotent(): void
    {
        $tenant = $this->makeOnPathTenant();
        $invoice = $this->makeInvoice($tenant, 50.0);
        $credit = $this->makeTopupCredit($tenant, 50.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $paymentApplication = PaymentApplication::create([
            'payment_id' => $credit->payment_id,
            'credit_id' => $credit->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => null,
            'amount' => 50.0,
            'applied_by' => $tenant['user']->id,
            'applied_at' => now(),
            'notes' => 'S1 idempotency probe',
        ]);

        $appliedPayments = [[
            'payment_application_id' => $paymentApplication->id,
            'credit_id' => $credit->id,
            'payment_id' => $credit->payment_id,
            'refund_id' => null,
            'voucher_number' => 'TOPUP',
            'amount_applied' => 50.0,
            'remaining_balance' => 0.0,
            'invoice_partial_id' => null,
        ]];

        $first = $this->invokePrivate($service, 'createCreditPaymentCOA', [$invoice, $appliedPayments, 50.0]);
        $this->assertNotNull($first, 'first post must succeed and return a Transaction');

        $countAfterFirst = JournalEntry::where('transaction_id', $first->id)->count();
        $this->assertSame(2, $countAfterFirst);

        $second = $this->invokePrivate($service, 'createCreditPaymentCOA', [$invoice, $appliedPayments, 50.0]);
        $this->assertNotNull($second, 'engine idempotency lookup must still return the existing document');
        $this->assertSame($first->id, $second->id, 'repeating the identical application set must return the SAME transaction, not a new one');

        $this->assertSame(
            1,
            Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->count(),
            'no second Transaction may be created for the identical application set'
        );
        $this->assertSame($countAfterFirst, JournalEntry::where('transaction_id', $first->id)->count());
    }

    /**
     * S1 (PostingSeam docblock, W1.1 FIX ROUND), now genuinely ARMED for this feeder (W2c fix,
     * B-1). W2b's first cut of this method pre-checked `isEnabledFor()` and returned `$legacy()`
     * directly on OFF — bypassing {@see \App\Services\Accounting\PostingSeam::post()} and, with
     * it, S1 entirely (the "S1 bypass" the W2b orchestrator lead report flagged): an earlier call
     * posted through the engine, then a kill-switch flip took this company back to the OFF path,
     * and the OLD code would have run `$legacy()` a second time for the SAME event. Removing the
     * pre-check means every call now reaches `PostingSeam::post()`, so S1 gets a chance to fire.
     */
    public function test_off_path_s1_skips_legacy_after_kill_switch_flip(): void
    {
        $tenant = $this->makeOnPathTenant();
        $company = $tenant['company'];
        $invoice = $this->makeInvoice($tenant, 50.0);
        $credit = $this->makeTopupCredit($tenant, 50.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $paymentApplication = PaymentApplication::create([
            'payment_id' => $credit->payment_id,
            'credit_id' => $credit->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => null,
            'amount' => 50.0,
            'applied_by' => $tenant['user']->id,
            'applied_at' => now(),
            'notes' => 'S1 kill-switch probe',
        ]);

        $appliedPayments = [[
            'payment_application_id' => $paymentApplication->id,
            'credit_id' => $credit->id,
            'payment_id' => $credit->payment_id,
            'refund_id' => null,
            'voucher_number' => 'TOPUP',
            'amount_applied' => 50.0,
            'remaining_balance' => 0.0,
            'invoice_partial_id' => null,
        ]];

        $first = $this->invokePrivate($service, 'createCreditPaymentCOA', [$invoice, $appliedPayments, 50.0]);
        $this->assertNotNull($first, 'First post must succeed and return the engine Transaction.');

        $transactionCountAfterFirst = Transaction::where('invoice_id', $invoice->id)->count();
        $journalCountAfterFirst = JournalEntry::count();

        // Kill-switch flip: this company goes back to the OFF path.
        Artisan::call('accounting:engine', ['company' => $company->id, '--disable' => true]);

        Log::spy();

        $second = $this->invokePrivate($service, 'createCreditPaymentCOA', [$invoice, $appliedPayments, 50.0]);

        $this->assertNull($second, 'S1: a retried post under the identical key, now routed OFF by the flag flip, must return a bare null — the legacy closure must never run for an already-posted event.');
        $this->assertSame(
            $transactionCountAfterFirst,
            Transaction::where('invoice_id', $invoice->id)->count(),
            'S1 must prevent the legacy closure from double-posting a second transaction.'
        );
        $this->assertSame($journalCountAfterFirst, JournalEntry::count());

        Log::shouldHaveReceived('warning')->once()->with(
            'accounting.legacy_skip_already_posted',
            Mockery::on(fn (array $context) => $context['feeder'] === 'payment-application.credit-apply')
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Mismatch and engine-failure: whole application rolled back.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Design call E1: a caller-total that disagrees with the sum of posted debits throws
     * CreditApplicationTotalMismatchException BEFORE anything is written — exercised directly
     * against the protected method (this specific mismatch cannot arise through the public
     * applyPaymentsToInvoice() API, since its own $creditApplied is always computed as the exact
     * sum of the same $appliedPayments array it hands to createCreditPaymentCOA()).
     */
    public function test_on_path_caller_total_mismatch_throws_and_writes_nothing(): void
    {
        $tenant = $this->makeOnPathTenant();
        $invoice = $this->makeInvoice($tenant, 100.0);
        $credit = $this->makeTopupCredit($tenant, 50.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $paymentApplication = PaymentApplication::create([
            'payment_id' => $credit->payment_id,
            'credit_id' => $credit->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => null,
            'amount' => 50.0,
            'applied_by' => $tenant['user']->id,
            'applied_at' => now(),
            'notes' => 'mismatch probe',
        ]);

        $appliedPayments = [[
            'payment_application_id' => $paymentApplication->id,
            'credit_id' => $credit->id,
            'payment_id' => $credit->payment_id,
            'refund_id' => null,
            'voucher_number' => 'TOPUP',
            'amount_applied' => 50.0,
            'remaining_balance' => 0.0,
            'invoice_partial_id' => null,
        ]];

        try {
            // Deliberately mismatched caller total (999.0 != sum of amount_applied, 50.0).
            $this->invokePrivate($service, 'createCreditPaymentCOA', [$invoice, $appliedPayments, 999.0]);
            $this->fail('Expected CreditApplicationTotalMismatchException was not thrown.');
        } catch (CreditApplicationTotalMismatchException $e) {
            $this->assertSame($invoice->id, $e->invoiceId);
            $this->assertEqualsWithDelta(999.0, $e->callerTotalAmount, 0.001);
            $this->assertEqualsWithDelta(50.0, $e->postedDebitTotal, 0.001);
        }

        $this->assertSame(0, Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->count());
        $this->assertSame(0, JournalEntry::query()->count());
    }

    /**
     * Engine failure (here: no SystemAccountsSeeder run, so CLIENT_ADVANCE/RECEIVABLE_CONTROL
     * are unmapped for this company -> AccountResolver throws UnmappedPurposeException, a
     * PostingException) must roll back the WHOLE applyPaymentsToInvoice() application — every
     * PaymentApplication/Credit(INVOICE)/InvoicePartial row created earlier in the same
     * DB::transaction(), not just the COA half — exercised through the real public entry point.
     */
    public function test_on_path_engine_failure_rolls_back_the_whole_application(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        // Engine ON for this company, but deliberately NO SystemAccountsSeeder — every purpose
        // code is unmapped.
        CoaSeeder::run($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        $invoice = $this->makeInvoice($tenant, 100.0);
        $credit = $this->makeTopupCredit($tenant, 100.0);

        $this->actingAs($tenant['user']);
        $service = new PaymentApplicationService();

        $result = $service->applyPaymentsToInvoice($invoice->id, [
            ['credit_id' => $credit->id, 'amount' => 100.0],
        ], 'full');

        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('Failed to apply payments', $result['message'] ?? '');

        $this->assertSame(0, PaymentApplication::where('invoice_id', $invoice->id)->count());
        $this->assertSame(0, Credit::where('invoice_id', $invoice->id)->where('type', Credit::INVOICE)->count());
        $this->assertSame(0, InvoicePartial::where('invoice_id', $invoice->id)->count());
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(100.0, (float) $credit->fresh()->amount, 0.001, 'the original TOPUP row must be untouched');
    }
}
