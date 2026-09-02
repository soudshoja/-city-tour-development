<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\FrozenAccountException;
use App\Models\Account;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\MyFatoorahPayment;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\PaymentIdempotencyKey;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * W1 cutover (R3 route-to-legacy decision, 2026-08-26): CheckMyFatoorahPayments::handle() now
 * calls App\Services\Accounting\PostingSeam instead of hand-rolling its one-sided JournalEntry
 * (doc 11 R2.2b). Four scenarios, each proved by real DB rows / real Log::spy() assertions rather
 * than mocking PostingService (it is `final`, matching PostingSeamTest's own rationale):
 *
 *   (a) flags OFF  -> byte-identical HEAD rows (this class does NOT replace
 *       CheckMyFatoorahPaymentsLedgerBalanceTest, which already pins the ledger-derived-balance
 *       fix on the same legacy block; this test additionally proves the seam refactor left that
 *       block's OUTPUT untouched, plus the new $alreadyPosted idempotency-key clause).
 *   (b) flags ON   -> a balanced 2-line engine document; running the command twice does not
 *       double-post.
 *   (c) flags ON, engine throws (FrozenAccountException) -> Log::critical, payment left
 *       unposted, zero rows.
 *   (d) flag flipped OFF between two payments in ONE run -> first engine, second legacy.
 *
 * Uses plain TestCase + CreatesTenantFixtures (NOT AccountingTestCase): the OFF-path legacy
 * block is, by design (R2.2b, untouched here), a deliberately ONE-SIDED JournalEntry — running
 * AccountingTestCase's tearDown() invariant suite (which asserts Σdebit=Σcredit per company)
 * against a company that also has that one-sided legacy row would fail on a correct test,
 * exactly as CheckMyFatoorahPaymentsLedgerBalanceTest itself already avoids doing.
 */
class CheckMyFatoorahPaymentsSeamTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        // config() is process-global for the duration of the test run — same defensive reset as
        // PostingSeamTest/PostingEngineGateTest.
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * The exact Liabilities -> Client -> Payment Gateway tree HEAD's legacy closure resolves by
     * name/root_id/parent_id (see CheckMyFatoorahPaymentsLedgerBalanceTest, which this mirrors).
     */
    private function makeLegacyPaymentGatewayAccount(Company $company): Account
    {
        $liabilitiesAccount = Account::create([
            'name' => 'Liabilities', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);
        $clientAdvance = Account::create([
            'name' => 'Client', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $liabilitiesAccount->id,
            'root_id' => $liabilitiesAccount->id,
        ]);

        return Account::create([
            'name' => 'Payment Gateway', 'level' => 3, 'actual_balance' => 0.00,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $clientAdvance->id,
            'root_id' => $liabilitiesAccount->id,
        ]);
    }

    /**
     * Engine-side accounts + the two system_accounts purpose mappings the ON path resolves via
     * AccountResolver (GATEWAY_CLEARING_MYFATOORAH, CLIENT_ADVANCE — both part of the P1
     * vocabulary; see SystemAccountsSeeder::resolveGatewayClearing()/resolveControls() and
     * config('accounting.purpose_codes')).
     *
     * W1.1 fix (P1 policy call, 2026-08-26): every payment fixture in this file has
     * `invoice_id: null`, so the credit leg's purpose code is CLIENT_ADVANCE, not
     * RECEIVABLE_CONTROL — see CheckMyFatoorahPayments::handle()'s own
     * `$creditPurposeCode = $payment->invoice_id !== null ? 'RECEIVABLE_CONTROL' :
     * 'CLIENT_ADVANCE';`. This mirrors, at the system_accounts-mapping level, exactly what the
     * legacy closure this replaces always did for every one of these fixtures: credit the
     * client-advance LIABILITY, never the receivable control.
     *
     * W1.2 fix (Task B, SEEDERS-IN-TESTS rule): was a factory-created Account per purpose code
     * plus a hand-inserted `system_accounts` row for each — exactly the shape the project rule
     * calls out ("Hand-inserted system_accounts rows hid two HIGH regressions in W1.1"). Now runs
     * the REAL `CoaSeeder` + `SystemAccountsSeeder` for this company and resolves both leaves off
     * the seeder's own mapping, same technique as `seedRealAccounting()` below in this file.
     *
     * @return array{0: Account, 1: Account} [gatewayClearing, clientAdvance]
     */
    private function mapEngineAccounts(Company $company): array
    {
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        $gatewayClearingId = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'GATEWAY_CLEARING_MYFATOORAH')
            ->value('account_id');
        $clientAdvanceId = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'CLIENT_ADVANCE')
            ->value('account_id');

        $this->assertNotNull($gatewayClearingId, 'SystemAccountsSeeder must map GATEWAY_CLEARING_MYFATOORAH for a freshly-seeded company.');
        $this->assertNotNull($clientAdvanceId, 'SystemAccountsSeeder must map CLIENT_ADVANCE for a freshly-seeded company.');

        return [Account::findOrFail($gatewayClearingId), Account::findOrFail($clientAdvanceId)];
    }

    private function fakeMyFatoorahPaidResponse(string $invoiceReference, float $amount, string $authCode, int $invoiceId = 999): void
    {
        Http::fake([
            '*/getPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceStatus' => 'Paid',
                    'InvoiceValue' => $amount,
                    'InvoiceId' => $invoiceId,
                    'InvoiceReference' => $invoiceReference,
                    'InvoiceTransactions' => [['AuthorizationId' => $authCode]],
                    'UserDefinedField' => json_encode(['process' => 'invoice']),
                ],
            ], 200),
        ]);
    }

    /**
     * (a) Flags OFF: exactly HEAD's rows. Expectations built by reading
     * `git show HEAD:app/Console/Commands/CheckMyFatoorahPayments.php`'s own JournalEntry::create()
     * call and actual_balance decrement, not from the refactored code — see PROOF in the task
     * response for the revert/run/restore cycle that confirms this goes red on a HEAD revert.
     */
    public function test_flags_off_writes_exactly_the_head_legacy_rows(): void
    {
        config(['accounting.engine.enabled' => false]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $paymentGateway = $this->makeLegacyPaymentGatewayAccount($company);

        // Pre-existing ledger balance (500.000) so the written 'balance' column is provably
        // ledger-derived (+ amount), not actual_balance-derived — same technique as
        // CheckMyFatoorahPaymentsLedgerBalanceTest.
        DB::table('journal_entries')->insert([
            'name' => $paymentGateway->name,
            'transaction_id' => null,
            'company_id' => $company->id,
            'account_id' => $paymentGateway->id,
            'branch_id' => $tenant['branch']->id,
            'transaction_date' => now(),
            'description' => 'pre-existing ledger balance fixture',
            'debit' => 0,
            'credit' => 500.000,
            'balance' => null,
            'voucher_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-SEAM-OFF-1',
            'voucher_number' => 'V-SEAM-OFF-1',
            'status' => 'initiate',
            'amount' => 75.500,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-SEAM-OFF-1', 75.500, 'AUTH-SEAM-OFF-1');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SEAM-OFF-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        // Exactly one Transaction row, legacy shape: payment_id + reference_type = 'Payment',
        // no idempotency_key -- the OFF path never reaches PostingService at all.
        $transactionRow = DB::table('transactions')->where('payment_id', $payment->id)->first();
        $this->assertNotNull($transactionRow, 'Expected the legacy Transaction row keyed by payment_id.');
        $this->assertSame('Payment', $transactionRow->reference_type);
        $this->assertNull($transactionRow->idempotency_key);
        $this->assertSame($company->id, (int) $transactionRow->company_id);
        $this->assertEqualsWithDelta(75.500, (float) $transactionRow->amount, 0.001);

        // Exactly one JournalEntry row: the one-sided credit to Payment Gateway (R2.2b,
        // deliberately unchanged), ledger-derived balance, ORIGINAL COLUMNS untouched.
        $entry = JournalEntry::where('account_id', $paymentGateway->id)
            ->where('transaction_id', $transactionRow->id)
            ->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(0.0, (float) $entry->debit, 0.001);
        $this->assertEqualsWithDelta(75.500, (float) $entry->credit, 0.001);
        $this->assertEqualsWithDelta(500.000 + 75.500, (float) $entry->balance, 0.001);
        $this->assertSame('receivable', $entry->type);
        $this->assertSame($payment->voucher_number, $entry->voucher_number);
        $this->assertSame('Advance Payment in voucher number: '.$payment->voucher_number, $entry->description);

        // Legacy actual_balance decrement, arithmetic UNCHANGED: 0.00 - 75.500.
        $this->assertEqualsWithDelta(0.00 - 75.500, (float) $paymentGateway->fresh()->actual_balance, 0.001);

        // The engine was never reached at all for this company.
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(1, DB::table('journal_entries')->where('company_id', $company->id)->whereNotNull('transaction_id')->count());
    }

    /**
     * (b) Flags ON: a balanced two-line engine document (Dr GATEWAY_CLEARING_MYFATOORAH,
     * Cr CLIENT_ADVANCE), idempotency_key set, posting_status='posted'. Running the command
     * a second time (payment reset back to 'initiate', simulating a retry) must NOT double-post
     * — proves the new $alreadyPosted idempotency-key clause actually protects the engine path.
     */
    public function test_flags_on_writes_a_balanced_engine_document_and_a_retry_does_not_double_post(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$gatewayClearing, $clientAdvance] = $this->mapEngineAccounts($company);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-SEAM-ON-1',
            'voucher_number' => 'V-SEAM-ON-1',
            'status' => 'initiate',
            'amount' => 42.750,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-SEAM-ON-1', 42.750, 'AUTH-SEAM-ON-1');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SEAM-ON-1'])->run();
        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        $idempotencyKey = "gateway:myfatoorah:payment:{$payment->id}:partials:none";

        $txn = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        $this->assertNotNull($txn, 'Expected exactly one engine-posted transaction keyed by idempotency_key.');
        $this->assertSame('posted', $txn->posting_status);
        $this->assertEqualsWithDelta(42.750, (float) $txn->total_debit, 0.001);
        $this->assertEqualsWithDelta((float) $txn->total_debit, (float) $txn->total_credit, 0.0005);

        // W1.2 (Task A): the engine draft now sets DocumentDraft::$paymentId too, so the
        // engine-posted transaction carries payment_id = $payment->id — but never under the
        // legacy reference_type='Payment' shape (the engine's own reference_type is 'Receipt').
        $this->assertSame(1, DB::table('transactions')->where('payment_id', $payment->id)->count());
        $this->assertSame(
            0,
            DB::table('transactions')->where('payment_id', $payment->id)->where('reference_type', 'Payment')->count(),
            'The legacy payment_id-keyed (reference_type=Payment) shape must never be written on the ON path.'
        );

        $lines = DB::table('journal_entries')->where('transaction_id', $txn->id)->get();
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('account_id', $gatewayClearing->id);
        $creditLine = $lines->firstWhere('account_id', $clientAdvance->id);
        $this->assertNotNull($debitLine, 'Expected a debit line on the GATEWAY_CLEARING_MYFATOORAH account.');
        $this->assertNotNull($creditLine, 'Expected a credit line on the CLIENT_ADVANCE account.');
        $this->assertEqualsWithDelta(42.750, (float) $debitLine->debit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $debitLine->credit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $creditLine->debit, 0.001);
        $this->assertEqualsWithDelta(42.750, (float) $creditLine->credit, 0.001);
        // Task B (W1.2): without an explicit $ledgerType, journal_entries.type falls back to
        // $transactionType ('GATEWAYDEBITED'), which matches neither of
        // AccountingController's screen filters (whereIn('type', ['payable','expenses']) /
        // ['receivable','income']) — invisible on every accounting screen. The gateway clearing
        // account is a receivable from the gateway, so it must persist type='receivable'.
        $this->assertSame('receivable', $debitLine->type);
        $this->assertSame('receivable', $creditLine->type);

        $sumDebit = (float) $lines->sum('debit');
        $sumCredit = (float) $lines->sum('credit');
        $this->assertEqualsWithDelta($sumDebit, $sumCredit, 0.0005, 'Engine document must be balanced.');

        // ── Retry: reset the payment back to 'initiate' (simulating a retry after the status
        // write failed to stick, or a re-run with an explicit invoiceId) and run again.
        $payment->refresh();
        $payment->status = 'initiate';
        $payment->save();

        $exitCode2 = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SEAM-ON-1'])->run();
        $this->assertSame(0, $exitCode2);
        $this->assertSame('completed', $payment->fresh()->status);

        $this->assertSame(
            1,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->count(),
            'A retry must not create a second transaction under the same idempotency key.'
        );
        $this->assertSame(
            2,
            DB::table('journal_entries')->where('transaction_id', $txn->id)->count(),
            'A retry must not add extra journal_entries lines to the same transaction.'
        );
    }

    /**
     * (c) Flags ON, the engine throws a genuine PostingException (FrozenAccountException, via a
     * disabled CLIENT_ADVANCE account): Log::critical (from the seam) AND this command's own
     * Log::error (with exception class + idempotency key, not a generic string) both fire; the
     * payment is left unposted (still 'initiate') with zero transactions/journal_entries rows —
     * no partial write.
     */
    public function test_flags_on_with_frozen_account_logs_critical_and_leaves_payment_unposted(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$gatewayClearing, $clientAdvance] = $this->mapEngineAccounts($company);
        $clientAdvance->disabled = true;
        $clientAdvance->save();

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-SEAM-FROZEN-1',
            'voucher_number' => 'V-SEAM-FROZEN-1',
            'status' => 'initiate',
            'amount' => 10.000,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-SEAM-FROZEN-1', 10.000, 'AUTH-SEAM-FROZEN-1');

        Log::spy();

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SEAM-FROZEN-1'])->run();

        // The command itself still exits SUCCESS — a single payment's failure is caught per-loop
        // and logged, never allowed to abort the whole batch (matches HEAD's own catch(\Throwable)).
        $this->assertSame(0, $exitCode);
        $this->assertSame('initiate', $payment->fresh()->status, 'A failed post must leave the payment unposted, not "completed".');

        $this->assertSame(0, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(0, DB::table('journal_entries')->where('company_id', $company->id)->count());

        $idempotencyKey = "gateway:myfatoorah:payment:{$payment->id}:partials:none";

        Log::shouldHaveReceived('critical')->once()->with(
            'accounting.engine_failure',
            Mockery::on(fn (array $ctx) => $ctx['feeder'] === 'myfatoorah.payment'
                && $ctx['company_id'] === $company->id
                && $ctx['idempotency_key'] === $idempotencyKey
                && $ctx['exception_class'] === FrozenAccountException::class)
        );

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to finalize MyFatoorah payment',
            Mockery::on(fn (array $ctx) => $ctx['payment_id'] === $payment->id
                && $ctx['idempotency_key'] === $idempotencyKey
                && $ctx['exception_class'] === FrozenAccountException::class)
        );
    }

    /**
     * (d) One command invocation, two payments, the engine flag flips OFF (via the real
     * `accounting:engine --disable` operator command) between them: the first payment posts
     * through the engine, the second through legacy. Proves the seam re-checks the flag on every
     * call within a single batch run, not once per command invocation.
     *
     * The flip happens INSIDE the Http::fake() closure, triggered by the second payment's own
     * getPaymentStatus request — i.e. strictly before that payment's accounting work runs, but
     * strictly after the first payment's accounting work has already committed.
     */
    public function test_flag_flipped_off_between_two_payments_in_one_run_first_engine_second_legacy(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        // W1.2 fix: the legacy closure resolves its "Liabilities"/"Client"/"Payment Gateway"
        // chain by NAME (Account::where('name', 'like', '%Liabilities%')->first(), no
        // orderBy — see CheckMyFatoorahPayments::handle()'s own legacy closure), so
        // makeLegacyPaymentGatewayAccount()'s own 3-level tree must be created BEFORE
        // mapEngineAccounts() seeds a real, separate "Liabilities" root via CoaSeeder for the
        // SAME company — otherwise that ->first() picks CoaSeeder's own Liabilities root instead
        // of this test's, and the second (legacy-path) payment silently posts into the wrong
        // account tree instead of $legacyPaymentGateway.
        $legacyPaymentGateway = $this->makeLegacyPaymentGatewayAccount($company);
        [$gatewayClearing, $clientAdvance] = $this->mapEngineAccounts($company);

        $payment1 = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-FLIP-1',
            'voucher_number' => 'V-FLIP-1',
            'status' => 'initiate',
            'amount' => 20.000,
        ]);
        $payment2 = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-FLIP-2',
            'voucher_number' => 'V-FLIP-2',
            'status' => 'initiate',
            'amount' => 30.000,
        ]);

        Http::fake([
            '*/getPaymentStatus' => function ($request) use ($company) {
                $body = json_decode($request->body(), true) ?? [];
                $key = $body['Key'] ?? null;

                if ($key === 'MF-INV-FLIP-2') {
                    // Flip strictly before payment 2's own accounting work runs (this closure
                    // executes when the command calls Http::post() for payment 2, before the
                    // command proceeds to that payment's DB::beginTransaction() block).
                    Artisan::call('accounting:engine', ['company' => $company->id, '--disable' => true]);
                }

                $amount = $key === 'MF-INV-FLIP-1' ? 20.000 : 30.000;

                return Http::response([
                    'IsSuccess' => true,
                    'Data' => [
                        'InvoiceStatus' => 'Paid',
                        'InvoiceValue' => $amount,
                        'InvoiceId' => $key === 'MF-INV-FLIP-1' ? 111 : 222,
                        'InvoiceReference' => $key.'-REF',
                        'InvoiceTransactions' => [['AuthorizationId' => 'AUTH-'.$key]],
                        'UserDefinedField' => json_encode(['process' => 'invoice']),
                    ],
                ], 200);
            },
        ]);

        // No invoiceId filter -> processes every 'initiate' MyFatoorah payment, in id order.
        $exitCode = $this->artisan('app:myfatoorah-check-status')->run();
        $this->assertSame(0, $exitCode);

        $this->assertSame('completed', $payment1->fresh()->status);
        $this->assertSame('completed', $payment2->fresh()->status);

        // The flag really did flip, persisted in the DB, for real.
        $this->assertFalse((bool) DB::table('companies')->where('id', $company->id)->value('posting_engine_enabled'));

        // Payment 1: engine path — idempotency_key set. W1.2 (Task A): payment_id is now set on
        // the engine row too, but never under the legacy reference_type='Payment' shape.
        $idemp1 = "gateway:myfatoorah:payment:{$payment1->id}:partials:none";
        $engineTxn = DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idemp1)->first();
        $this->assertNotNull($engineTxn, 'Expected payment 1 to post through the engine.');
        $this->assertSame('posted', $engineTxn->posting_status);
        $this->assertSame((int) $payment1->id, (int) $engineTxn->payment_id, 'Task A: the engine header now carries payment_id.');
        $this->assertSame(
            0,
            DB::table('transactions')->where('payment_id', $payment1->id)->where('reference_type', 'Payment')->count(),
            'The legacy payment_id-keyed (reference_type=Payment) shape must never be written for payment 1.'
        );

        $engineLines = DB::table('journal_entries')->where('transaction_id', $engineTxn->id)->get();
        $this->assertCount(2, $engineLines);
        $engineDebitLine = $engineLines->firstWhere('account_id', $gatewayClearing->id);
        $engineCreditLine = $engineLines->firstWhere('account_id', $clientAdvance->id);
        $this->assertNotNull($engineDebitLine, 'Expected payment 1\'s debit line on GATEWAY_CLEARING_MYFATOORAH.');
        $this->assertNotNull($engineCreditLine, 'Expected payment 1\'s credit line on CLIENT_ADVANCE.');
        $this->assertEqualsWithDelta(20.000, (float) $engineDebitLine->debit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $engineDebitLine->credit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $engineCreditLine->debit, 0.001);
        $this->assertEqualsWithDelta(20.000, (float) $engineCreditLine->credit, 0.001);

        // Payment 2: legacy path — payment_id/reference_type shape, no idempotency_key.
        $legacyTxn = DB::table('transactions')
            ->where('payment_id', $payment2->id)
            ->where('reference_type', 'Payment')
            ->first();
        $this->assertNotNull($legacyTxn, 'Expected payment 2 to post through legacy after the flip.');
        $this->assertNull($legacyTxn->idempotency_key);
        $this->assertSame(
            0,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', "gateway:myfatoorah:payment:{$payment2->id}:partials:none")->count(),
            'Payment 2 must never have reached the engine after the flip.'
        );

        $legacyEntry = JournalEntry::where('account_id', $legacyPaymentGateway->id)
            ->where('transaction_id', $legacyTxn->id)
            ->first();
        $this->assertNotNull($legacyEntry);
        $this->assertEqualsWithDelta(0.0, (float) $legacyEntry->debit, 0.001);
        $this->assertEqualsWithDelta(30.000, (float) $legacyEntry->credit, 0.001);
    }

    /**
     * Item 4's specific consistency requirement, isolated from PostingService's own idempotency
     * safety net: post payment via the ENGINE, then disable the engine (kill switch), then retry
     * the SAME payment. UPDATED (W1.2, Task A): PostingService's header write now sets
     * `payment_id` too, when the draft provides it (as MyFatoorah's engine draft does) — but it
     * does so under `reference_type = 'Receipt'` (engine-derived), never legacy's `'Payment'`
     * label. The OLD guard (`Transaction::where('payment_id', ...)->where('reference_type',
     * 'Payment')`) alone would STILL see this payment as "never posted" — its own
     * `reference_type = 'Payment'` filter never matches an engine row — and let the retry fall
     * through to the LEGACY closure, producing a SECOND, real, one-sided journal entry for money
     * that was already posted once through the engine. This is exactly the "duplicate money"
     * scenario the seam-review verifier flagged for the kill-switch flip. The
     * `Transaction::where('idempotency_key', ...)` clause must recognise the engine-posted row
     * and skip the legacy closure entirely.
     */
    public function test_already_posted_guard_recognizes_engine_posted_payment_after_engine_disabled_preventing_legacy_double_post(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [$gatewayClearing, $clientAdvance] = $this->mapEngineAccounts($company);
        $legacyPaymentGateway = $this->makeLegacyPaymentGatewayAccount($company);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-KILLSWITCH-1',
            'voucher_number' => 'V-KILLSWITCH-1',
            'status' => 'initiate',
            'amount' => 15.000,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-KILLSWITCH-1', 15.000, 'AUTH-KILLSWITCH-1');

        $exitCode1 = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-KILLSWITCH-1'])->run();
        $this->assertSame(0, $exitCode1);
        $this->assertSame('completed', $payment->fresh()->status);

        $idempotencyKey = "gateway:myfatoorah:payment:{$payment->id}:partials:none";
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->count());

        // Kill switch pulled, THEN the payment retries (e.g. a webhook redelivery, or an operator
        // re-running with an explicit invoiceId while the status happened to still read 'initiate').
        Artisan::call('accounting:engine', ['company' => $company->id, '--disable' => true]);
        $payment->refresh();
        $payment->status = 'initiate';
        $payment->save();

        $exitCode2 = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-KILLSWITCH-1'])->run();
        $this->assertSame(0, $exitCode2);
        $this->assertSame('completed', $payment->fresh()->status);

        // Still exactly ONE transaction for this payment, total, across BOTH runs — the guard
        // must have recognised the engine-posted row and skipped the legacy closure on retry.
        $this->assertSame(
            1,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->count(),
            'The original engine-posted transaction must be untouched.'
        );
        // W1.2 (Task A): the ORIGINAL engine post already carries payment_id, so this must stay
        // exactly 1 (not 2) across both runs, AND never under the legacy reference_type='Payment'
        // shape — that combination is what would prove a second, legacy transaction was created.
        $this->assertSame(
            1,
            DB::table('transactions')->where('payment_id', $payment->id)->count(),
            'Exactly the original engine-posted transaction may carry payment_id — the retry must NOT have added a second row.'
        );
        $this->assertSame(
            0,
            DB::table('transactions')->where('payment_id', $payment->id)->where('reference_type', 'Payment')->count(),
            'The retry (engine now disabled) must NOT have fallen through to the legacy closure and created a second, one-sided transaction for the same payment.'
        );
        $this->assertSame(
            0,
            JournalEntry::where('account_id', $legacyPaymentGateway->id)->count(),
            'No legacy journal entry must exist for the legacy Payment Gateway account — the retry never reached the legacy closure.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // M1 (W1.1 fix — REGRESSION vs HEAD, W1 lead report §3 myfatoorah): a payment whose agent
    // has been hard-deleted (agent_id NULL — nullable()->nullOnDelete(), Agent has no
    // SoftDeletes, a real reachable state) must still finish. Before this fix, W1's
    // unconditional `$payment->agent->branch->company->id` hoist threw (Laravel's
    // HandleExceptions converts the "Attempt to read property on null" PHP warning into a
    // thrown ErrorException), the outer catch(\Throwable) rolled the whole DB transaction
    // back, and the payment was stranded at 'initiate' forever — proved red on revert (see
    // PROOF in the task response). Only the POSTING block is skipped now, on BOTH flag states
    // — the payment itself still completes.
    //
    // TASK B (W1.2, owner decision, applied 2026-08-26): finalizing the payment here is NOT a
    // restoration of HEAD's own behaviour (HEAD threw and stranded the payment at 'initiate'
    // forever, above) — it is a deliberately different, chosen outcome: the gateway fact (money
    // collected) is real, so the payment finalizes, but the gap is now LOUD
    // (Log::critical('accounting.payment_unattributed', ...), not the WARNING this branch used
    // to log) so an accountant can find and post it manually. Asserted below on BOTH flag
    // states, since $companyId is resolved (and found null) before either path is chosen.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function makePaymentWithMissingAgent(array $tenant, string $ref, float $amount = 50.000): Payment
    {
        return Payment::factory()->create([
            'agent_id' => null,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => $ref,
            'voucher_number' => 'V-'.$ref,
            'status' => 'initiate',
            'amount' => $amount,
        ]);
    }

    public function test_agent_missing_still_completes_the_payment_with_no_posting_flags_off(): void
    {
        config(['accounting.engine.enabled' => false]);

        $tenant = $this->createTenant();
        $payment = $this->makePaymentWithMissingAgent($tenant, 'MF-INV-NOAGENT-OFF-1');

        $this->fakeMyFatoorahPaidResponse('MF-REF-NOAGENT-OFF-1', 50.000, 'AUTH-NOAGENT-OFF-1');

        Log::spy();

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-NOAGENT-OFF-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            'completed',
            $payment->fresh()->status,
            'A payment whose agent is missing must still finish, not be stranded at initiate.'
        );
        $this->assertNotNull(
            MyFatoorahPayment::where('payment_int_id', $payment->id)->first(),
            'Expected the MyFatoorahPayment row to be written even when posting is skipped.'
        );
        $this->assertSame(0, DB::table('transactions')->where('payment_id', $payment->id)->count());
        $this->assertSame(0, DB::table('journal_entries')->count());

        // Task B (W1.2): loud, not a warning — an accountant must be able to find this.
        Log::shouldHaveReceived('critical')->once()->with(
            'accounting.payment_unattributed',
            Mockery::on(fn (array $ctx) => $ctx['payment_id'] === $payment->id
                && $ctx['voucher_number'] === $payment->voucher_number
                && (float) $ctx['amount'] === (float) $payment->amount
                && $ctx['reason'] === 'agent missing (agent_id NULL)')
        );
    }

    public function test_agent_missing_still_completes_the_payment_with_no_posting_flags_on(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--enable' => true]);

        $payment = $this->makePaymentWithMissingAgent($tenant, 'MF-INV-NOAGENT-ON-1');

        $this->fakeMyFatoorahPaidResponse('MF-REF-NOAGENT-ON-1', 50.000, 'AUTH-NOAGENT-ON-1');

        Log::spy();

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-NOAGENT-ON-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            'completed',
            $payment->fresh()->status,
            'A payment whose agent is missing must still finish, not be stranded at initiate.'
        );
        $this->assertNotNull(MyFatoorahPayment::where('payment_int_id', $payment->id)->first());
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());

        // Task B (W1.2): the company-unresolvable path is independent of the engine flag —
        // $companyId is resolved (and found null) before either the engine or legacy branch is
        // chosen — so the same critical log must fire on the ON path too.
        Log::shouldHaveReceived('critical')->once()->with(
            'accounting.payment_unattributed',
            Mockery::on(fn (array $ctx) => $ctx['payment_id'] === $payment->id
                && $ctx['voucher_number'] === $payment->voucher_number
                && (float) $ctx['amount'] === (float) $payment->amount
                && $ctx['reason'] === 'agent missing (agent_id NULL)')
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // M2 (P2 policy — KEEP, W1 lead report §3/§6): myfatoorah_payments.payment_id now stores
    // the REAL gateway PaymentId on both flag states — HEAD's $transaction variable-shadowing
    // bug meant this column was NULL whenever the posting block ran (an Eloquent Transaction
    // model was read as an array offset). See the M2 comment at
    // CheckMyFatoorahPayments::handle()'s write site.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function fakeMyFatoorahPaidResponseWithPaymentId(
        string $invoiceReference,
        float $amount,
        string $authCode,
        string $gatewayPaymentId,
        int $invoiceId = 999
    ): void {
        Http::fake([
            '*/getPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceStatus' => 'Paid',
                    'InvoiceValue' => $amount,
                    'InvoiceId' => $invoiceId,
                    'InvoiceReference' => $invoiceReference,
                    'InvoiceTransactions' => [['AuthorizationId' => $authCode, 'PaymentId' => $gatewayPaymentId]],
                    'UserDefinedField' => json_encode(['process' => 'invoice']),
                ],
            ], 200),
        ]);
    }

    public function test_myfatoorah_payments_payment_id_stores_the_real_gateway_payment_id_flags_off(): void
    {
        config(['accounting.engine.enabled' => false]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->makeLegacyPaymentGatewayAccount($company);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-PID-OFF-1',
            'voucher_number' => 'V-PID-OFF-1',
            'status' => 'initiate',
            'amount' => 12.000,
        ]);

        $this->fakeMyFatoorahPaidResponseWithPaymentId('MF-REF-PID-OFF-1', 12.000, 'AUTH-PID-OFF-1', '555666777');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-PID-OFF-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        $mf = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();
        $this->assertNotNull($mf);
        $this->assertSame('555666777', $mf->payment_id, 'Expected the real gateway PaymentId, not NULL (the HEAD $transaction-shadowing bug).');
    }

    public function test_myfatoorah_payments_payment_id_stores_the_real_gateway_payment_id_flags_on(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->mapEngineAccounts($company);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-PID-ON-1',
            'voucher_number' => 'V-PID-ON-1',
            'status' => 'initiate',
            'amount' => 12.000,
        ]);

        $this->fakeMyFatoorahPaidResponseWithPaymentId('MF-REF-PID-ON-1', 12.000, 'AUTH-PID-ON-1', '888999000');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-PID-ON-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        $mf = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();
        $this->assertNotNull($mf);
        $this->assertSame('888999000', $mf->payment_id, 'Expected the real gateway PaymentId on the ON path too.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // M4 (W1.1 fix, W1 lead report §3 myfatoorah): $alreadyPosted must be soft-delete AWARE
    // (->withTrashed()) so a soft-deleted transaction (legacy shape OR engine-posted) still
    // counts as "already posted" on the next run — otherwise a retry after a reversal either
    // collides on the unique(company_id, idempotency_key) index (engine shape) or genuinely
    // double-posts a one-sided legacy entry for money already recorded once.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_already_posted_guard_recognizes_a_soft_deleted_legacy_transaction(): void
    {
        config(['accounting.engine.enabled' => false]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->makeLegacyPaymentGatewayAccount($company);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-SOFTDEL-LEG-1',
            'voucher_number' => 'V-SOFTDEL-LEG-1',
            'status' => 'initiate',
            'amount' => 19.000,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-SOFTDEL-LEG-1', 19.000, 'AUTH-SOFTDEL-LEG-1');

        $exitCode1 = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SOFTDEL-LEG-1'])->run();
        $this->assertSame(0, $exitCode1);
        $this->assertSame('completed', $payment->fresh()->status);

        $legacyTxn = DB::table('transactions')->where('payment_id', $payment->id)->first();
        $this->assertNotNull($legacyTxn);

        // Soft-delete it, simulating a reversal / admin cleanup.
        Transaction::find($legacyTxn->id)->delete();
        $this->assertNotNull(Transaction::withTrashed()->find($legacyTxn->id)->deleted_at);

        $payment->refresh();
        $payment->status = 'initiate';
        $payment->save();

        $exitCode2 = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SOFTDEL-LEG-1'])->run();
        $this->assertSame(0, $exitCode2);
        $this->assertSame('completed', $payment->fresh()->status);

        // M4: the soft-deleted row must still count as "already posted" — no second legacy
        // transaction/journal entry.
        $this->assertSame(
            1,
            DB::table('transactions')->where('payment_id', $payment->id)->count(),
            'A soft-deleted legacy transaction must still count as "already posted".'
        );
        $this->assertSame(
            1,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            'No second legacy journal entry must have been written.'
        );
    }

    public function test_already_posted_guard_recognizes_a_soft_deleted_engine_transaction(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->mapEngineAccounts($company);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-SOFTDEL-ENG-1',
            'voucher_number' => 'V-SOFTDEL-ENG-1',
            'status' => 'initiate',
            'amount' => 33.000,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-SOFTDEL-ENG-1', 33.000, 'AUTH-SOFTDEL-ENG-1');

        $exitCode1 = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SOFTDEL-ENG-1'])->run();
        $this->assertSame(0, $exitCode1);
        $this->assertSame('completed', $payment->fresh()->status);

        $idempotencyKey = "gateway:myfatoorah:payment:{$payment->id}:partials:none";
        $engineTxn = DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($engineTxn);

        // Soft-delete the engine-posted transaction (e.g. a reversal).
        Transaction::find($engineTxn->id)->delete();
        $this->assertNotNull(Transaction::withTrashed()->find($engineTxn->id)->deleted_at);

        $payment->refresh();
        $payment->status = 'initiate';
        $payment->save();

        $exitCode2 = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-SOFTDEL-ENG-1'])->run();
        $this->assertSame(0, $exitCode2);
        $this->assertSame('completed', $payment->fresh()->status);

        // M4: exactly ONE transaction must ever exist under this idempotency key — the
        // soft-deleted one must have been recognised as "already posted" so neither the engine
        // nor legacy ran again (which would otherwise collide on the unique(company_id,
        // idempotency_key) index, or, on the OFF path, double-post for real).
        $this->assertSame(
            1,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->count(),
            'A soft-deleted engine transaction must still count as "already posted" — no second post.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // P1 policy (W1.1 — 2026-08-26, W1 lead report §3 myfatoorah "account-role
    // reclassification" finding): invoice_id NULL -> Cr CLIENT_ADVANCE (a liability, matching
    // what the legacy closure has always posted for every real dev row sampled); invoice_id SET
    // -> Cr RECEIVABLE_CONTROL (a contra-receivable). Proved with the REAL seeders
    // (CoaSeeder::run() + SystemAccountsSeeder::run()) — never hand-inserted system_accounts
    // rows — so the resolved leaf's ROOT is asserted directly off the real seeded chart, not a
    // synthetic fixture.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function seedRealAccounting(int $companyId): void
    {
        CoaSeeder::run($companyId);
        (new SystemAccountsSeeder())->run();
    }

    public function test_on_path_with_real_seeders_credits_client_advance_liability_when_invoice_id_is_null(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->seedRealAccounting($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-REALSEED-ADV-1',
            'voucher_number' => 'V-REALSEED-ADV-1',
            'status' => 'initiate',
            'amount' => 77.000,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-REALSEED-ADV-1', 77.000, 'AUTH-REALSEED-ADV-1');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-REALSEED-ADV-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        $idempotencyKey = "gateway:myfatoorah:payment:{$payment->id}:partials:none";
        $txn = DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($txn, 'Expected the engine to post using the REAL seeded chart of accounts.');

        $creditLine = JournalEntry::where('transaction_id', $txn->id)->where('credit', '>', 0)->first();
        $this->assertNotNull($creditLine);

        $creditAccount = Account::with('root')->find($creditLine->account_id);
        $this->assertSame('Payment Gateway', $creditAccount->name);
        $this->assertSame('2632', $creditAccount->code);
        $this->assertNotNull($creditAccount->root, 'Expected the resolved leaf to have a root account.');
        $this->assertSame(
            'Liabilities',
            $creditAccount->root->name,
            'CLIENT_ADVANCE (invoice_id NULL) must resolve to a Liabilities-rooted leaf, never a receivable.'
        );

        $debitLine = JournalEntry::where('transaction_id', $txn->id)->where('debit', '>', 0)->first();
        $this->assertNotNull($debitLine);
        $this->assertEqualsWithDelta((float) $debitLine->debit, (float) $creditLine->credit, 0.0005, 'Document must be balanced.');
    }

    public function test_on_path_with_real_seeders_credits_receivable_control_when_invoice_id_is_set(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->seedRealAccounting($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-REALSEED-REC-1',
            'voucher_number' => 'V-REALSEED-REC-1',
            'status' => 'initiate',
            'amount' => 88.000,
        ]);

        $this->fakeMyFatoorahPaidResponse('MF-REF-REALSEED-REC-1', 88.000, 'AUTH-REALSEED-REC-1');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-REALSEED-REC-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        $idempotencyKey = "gateway:myfatoorah:payment:{$payment->id}:partials:none";
        $txn = DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($txn, 'Expected the engine to post using the REAL seeded chart of accounts.');

        $creditLine = JournalEntry::where('transaction_id', $txn->id)->where('credit', '>', 0)->first();
        $this->assertNotNull($creditLine);
        $this->assertSame($invoice->id, $creditLine->invoice_id, 'Expected line-level invoice attribution to carry through.');

        $creditAccount = Account::with('root')->find($creditLine->account_id);
        $this->assertSame('Clients', $creditAccount->name);
        $this->assertSame('1351', $creditAccount->code);
        $this->assertNotNull($creditAccount->root, 'Expected the resolved leaf to have a root account.');
        $this->assertSame(
            'Assets',
            $creditAccount->root->name,
            'RECEIVABLE_CONTROL (invoice_id set) must resolve to the Assets-rooted Accounts Receivable "Clients" leaf, never the Refund Payable one.'
        );

        $debitLine = JournalEntry::where('transaction_id', $txn->id)->where('debit', '>', 0)->first();
        $this->assertNotNull($debitLine);
        $this->assertEqualsWithDelta((float) $debitLine->debit, (float) $creditLine->credit, 0.0005, 'Document must be balanced.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W7.Y fix (gate item 2, BLOCKER): `process === 'topup'` branch coverage. Previously ZERO --
    // every fixture above uses `'process' => 'invoice'`. addCredit() (called a few lines above
    // this command's own posting block, for `process === 'topup'`) fully owns posting for a
    // topup payment on BOTH flag states; this command must NOT ALSO post its own MYFATOORAH
    // draft for the same payment -- see CheckMyFatoorahPayments::handle()'s own new branch.
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function fakeMyFatoorahPaidTopupResponse(string $invoiceReference, float $amount, string $authCode, int $invoiceId = 999): void
    {
        Http::fake([
            '*/getPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceStatus' => 'Paid',
                    'InvoiceValue' => $amount,
                    'InvoiceId' => $invoiceId,
                    'InvoiceReference' => $invoiceReference,
                    'InvoiceTransactions' => [['AuthorizationId' => $authCode]],
                    'UserDefinedField' => json_encode(['process' => 'topup']),
                ],
            ], 200),
        ]);
    }

    /**
     * The exact scenario item 2 names: a topup payment whose gateway webhook was lost, later
     * reconciled by this command's own status-check cron, engine ON. Must post EXACTLY ONE
     * document (addCredit()'s own), crediting CLIENT_ADVANCE exactly the payment amount ONCE --
     * not twice (addCredit()'s document + a spurious second MYFATOORAH draft from this command).
     */
    public function test_flags_on_topup_branch_addcredit_owns_posting_no_second_document(): void
    {
        config(['accounting.engine.enabled' => true]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-TOPUP-ON-1',
            'voucher_number' => 'V-TOPUP-ON-1',
            'status' => 'initiate',
            'amount' => 40.000,
            // Nonzero gateway_fee up front -> addCredit()'s own "imported payment" branch, whose
            // bearer is pinned to $payment->paymentMethod?->paid_by ?? 'Company' (no PaymentMethod
            // row here) -- deterministic 'Company' bearer, keeping this fixture focused on item 2's
            // own double-post question rather than re-exercising item 3's bearer split.
            'gateway_fee' => 3.000,
            'service_charge' => 0,
        ]);

        $this->fakeMyFatoorahPaidTopupResponse('MF-REF-TOPUP-ON-1', 40.000, 'AUTH-TOPUP-ON-1');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-TOPUP-ON-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        // addCredit()'s own key -- exactly ONE document under it.
        $addCreditKey = PaymentIdempotencyKey::forClientCreditTopup($tenant['client']->id, $tenant['agent']->id, $payment->id, 40.0);
        $this->assertSame(
            1,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $addCreditKey)->count(),
            'addCredit() must have posted its own document.'
        );

        // The command's OWN myfatoorah key must NEVER have posted a second document for a topup.
        $commandKey = "gateway:myfatoorah:payment:{$payment->id}:partials:none";
        $this->assertSame(
            0,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $commandKey)->count(),
            'The command must not post its own MYFATOORAH draft for a topup payment -- addCredit() owns posting.'
        );

        $this->assertSame(1, Credit::where('payment_id', $payment->id)->where('type', Credit::TOPUP)->count());
        $this->assertSame(
            1,
            DB::table('transactions')->where('company_id', $company->id)->count(),
            'Exactly ONE document total for this payment -- no spurious second MYFATOORAH draft.'
        );

        $clientAdvance = app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $company->id);
        $net = (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('credit')
            - (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('debit');
        $this->assertEqualsWithDelta(40.0, $net, 0.0005, 'CLIENT_ADVANCE must be credited exactly ONCE by the topup amount, never twice.');
    }

    /**
     * OFF-path sanity companion: the topup branch's early-exit is unconditional (not
     * engine-flag-gated), so this must hold on OFF too -- addCredit()'s own legacy closure posts,
     * and the command's legacy $legacy closure must never ALSO run for this payment.
     */
    public function test_flags_off_topup_branch_addcredit_owns_posting_no_second_document(): void
    {
        config(['accounting.engine.enabled' => false]);

        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->makeLegacyPaymentGatewayAccount($company);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-TOPUP-OFF-1',
            'voucher_number' => 'V-TOPUP-OFF-1',
            'status' => 'initiate',
            'amount' => 25.000,
            'gateway_fee' => 2.000,
            'service_charge' => 0,
        ]);

        $this->fakeMyFatoorahPaidTopupResponse('MF-REF-TOPUP-OFF-1', 25.000, 'AUTH-TOPUP-OFF-1');

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-TOPUP-OFF-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        $this->assertSame(1, Credit::where('payment_id', $payment->id)->where('type', Credit::TOPUP)->count());
        // addCredit()'s own legacy Transaction (payment_id + reference_type='Payment') -- exactly
        // one, no second one from this command's own legacy closure.
        $this->assertSame(1, DB::table('transactions')->where('payment_id', $payment->id)->where('reference_type', 'Payment')->count());
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
    }
}
