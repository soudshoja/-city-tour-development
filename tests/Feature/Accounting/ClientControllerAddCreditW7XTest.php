<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\ClientController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\PaymentIdempotencyKey;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w7x. W7.X (.planning/accounting-waves/w7/w7-final-gate.md §1a, BLOCKER 1) --
 * ClientController::addCredit() through the seam. Previously the single largest raw
 * ledger-writer gap in the codebase: called unconditionally, with zero PostingSeam awareness,
 * from 13 live gateway-callback sites regardless of the engine flag. See addCredit()'s own
 * docblock for the full cutover shape.
 */
class ClientControllerAddCreditW7XTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Branch}
     */
    private function makeCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        return [$company, $branch];
    }

    /**
     * @return array{0: Agent, 1: Client}
     */
    private function makeAgentAndClient(Branch $branch): array
    {
        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return [$agent, $client];
    }

    /**
     * Charge row wiring the 'Tap' gateway to a real, seeded FK chain: acc_bank_id ->
     * 'Kuwait International Bank' (1201, under Bank Accounts), acc_fee_bank_id -> 'Payment
     * Gateway' (1300, under Assets -- the SAME leaf GATEWAY_CLEARING_TAP resolves to, since it
     * has no per-gateway children in a freshly-seeded company -- see
     * SystemAccountsSeeder::resolveGatewayClearing()'s own "no per-gateway split ... the pool
     * itself is the leaf" fallback), acc_fee_id -> 'TAP Charges' (5141, the fee expense leaf).
     */
    private function makeTapCharge(int $companyId): Charge
    {
        $bankAccount = Account::where('company_id', $companyId)->where('name', 'Kuwait International Bank')->firstOrFail();
        $paymentGatewayAsset = Account::where('company_id', $companyId)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();
        $tapCharges = Account::where('company_id', $companyId)->where('name', 'TAP Charges')->firstOrFail();

        return Charge::factory()->create([
            'name' => 'Tap Gateway',
            'company_id' => $companyId,
            'acc_bank_id' => $bankAccount->id,
            'acc_fee_bank_id' => $paymentGatewayAsset->id,
            'acc_fee_id' => $tapCharges->id,
        ]);
    }

    private function makePayment(Agent $agent, Client $client, float $amount, float $gatewayFee): Payment
    {
        return Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $agent->user_id,
            'amount' => $amount,
            'gateway_fee' => $gatewayFee,
            'service_charge' => 0,
            'payment_gateway' => 'Tap',
            'payment_method_id' => null,
            'status' => 'completed',
            'completed' => 0,
        ]);
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path -- byte parity vs the pre-W7.X legacy body.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_matches_legacy_exactly(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        config(['accounting.engine.enabled' => false]);

        $payment = $this->makePayment($agent, $client, 100.0, 5.0);

        $response = app(ClientController::class)->addCredit($payment);

        $this->assertSame('success', $response['status']);
        $this->assertEqualsWithDelta(100.0, (float) $response['data']['credit'], 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $response['data']['accounting_fee'], 0.0005);
        $this->assertEqualsWithDelta(95.0, (float) $response['data']['asset_amount'], 0.0005);

        // Legacy shape, verbatim: Credit row + 1 Transaction + 3 JournalEntry rows (ENTRY1 bank,
        // ENTRY2 fee expense, ENTRY4 client advance -- ENTRY3 income is skipped: paidBy defaults
        // to 'Company' with no PaymentMethod set, so recordIncome is false), NEITHER carrying an
        // idempotency_key (the engine never ran).
        $this->assertSame(1, Credit::where('payment_id', $payment->id)->where('type', Credit::TOPUP)->count());
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('idempotency_key')->count(),
            'OFF path must never populate idempotency_key -- that column is an engine-only concept.'
        );

        $bankPaymentFee = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();
        $bankCOAFee = Account::where('company_id', $company->id)->where('name', 'TAP Charges')->firstOrFail();
        $clientAdvance = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Client'))->firstOrFail();
        $incomeAccount = Account::where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->first();

        $this->assertSame(3, JournalEntry::where('company_id', $company->id)->count(), 'ENTRY1 + ENTRY2 + ENTRY4 -- ENTRY3 (income) is skipped when the company bears the fee.');

        $bankLine = JournalEntry::where('account_id', $bankPaymentFee->id)->where('company_id', $company->id)->first();
        $feeLine = JournalEntry::where('account_id', $bankCOAFee->id)->where('company_id', $company->id)->first();
        $advanceLine = JournalEntry::where('account_id', $clientAdvance->id)->where('company_id', $company->id)->first();

        $this->assertNotNull($bankLine);
        $this->assertNotNull($feeLine);
        $this->assertNotNull($advanceLine);
        $this->assertEqualsWithDelta(95.0, (float) $bankLine->debit, 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $feeLine->debit, 0.0005);
        $this->assertEqualsWithDelta(100.0, (float) $advanceLine->credit, 0.0005);

        if ($incomeAccount) {
            $this->assertSame(0, JournalEntry::where('account_id', $incomeAccount->id)->where('company_id', $company->id)->count());
        }

        // Legacy actual_balance mutations, preserved byte-for-byte.
        $this->assertEqualsWithDelta(95.0, (float) $bankPaymentFee->fresh()->actual_balance, 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $bankCOAFee->fresh()->actual_balance, 0.0005);
        $this->assertEqualsWithDelta(100.0, (float) $clientAdvance->fresh()->actual_balance, 0.0005);
    }

    public function test_off_path_duplicate_payment_id_is_blocked_before_any_write(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);
        config(['accounting.engine.enabled' => false]);

        $payment = $this->makePayment($agent, $client, 40.0, 2.0);

        $first = app(ClientController::class)->addCredit($payment);
        $this->assertSame('success', $first['status']);

        $second = app(ClientController::class)->addCredit($payment);
        // Production contract (254bb45a8 / VOU-2026-03849): a benign duplicate retry
        // (webhook + browser callback + reconciler racing the same payment) returns
        // success with already_added=true, not an error -- the write-blocking is what
        // matters, not the label. See DIAG-2.
        $this->assertSame('success', $second['status']);
        $this->assertTrue($second['already_added']);

        $this->assertSame(1, Credit::where('payment_id', $payment->id)->count());
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path -- Dr GATEWAY_CLEARING_{gateway} / Cr CLIENT_ADVANCE.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_posts_one_balanced_document_and_nets_client_advance_by_the_topup_amount(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payment = $this->makePayment($agent, $client, 100.0, 5.0);

        $response = app(ClientController::class)->addCredit($payment);

        $this->assertSame('success', $response['status']);

        $key = PaymentIdempotencyKey::forClientCreditTopup($client->id, $agent->id, $payment->id, 100.0);
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($posted, 'ON path must post a real engine document under the (client, agent, payment, amount) key.');
        $this->assertSame('RV', $posted->doc_type);

        $clearing = app(AccountResolver::class)->resolve('GATEWAY_CLEARING_TAP', $company->id);
        $clientAdvance = app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $company->id);
        $feeExpense = app(AccountResolver::class)->resolve('GATEWAY_FEE_EXPENSE_TAP', $company->id);

        $clearingDebit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('debit');
        $clearingCredit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('credit');
        $advanceDebit = (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('debit');
        $advanceCredit = (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('credit');
        $feeExpenseDebit = (float) DB::table('journal_entries')->where('account_id', $feeExpense->id)->sum('debit');

        // Verify-fix (lead finding, w7-final-gate.md §1a BLOCKER 1): clearing must be debited for
        // the NET amount (100 - 5 fee = 95), not the gross 100 -- otherwise the fee never clears
        // and GATEWAY_CLEARING_TAP accumulates a permanent 5 residual once
        // PaymentReleaseToCompanyBankAccProcess later credits it only 95 for this same payment.
        // See test_on_path_and_release_close_gateway_clearing_to_zero_across_a_full_cycle() below
        // for the end-to-end proof.
        $this->assertEqualsWithDelta(95.0, $clearingDebit - $clearingCredit, 0.0005, 'GATEWAY_CLEARING_TAP must net a 95 DEBIT (net of the 5 gateway fee).');
        $this->assertEqualsWithDelta(100.0, $advanceCredit - $advanceDebit, 0.0005, 'CLIENT_ADVANCE must net a 100 CREDIT (the full topup amount).');
        $this->assertEqualsWithDelta(5.0, $feeExpenseDebit, 0.0005, 'GATEWAY_FEE_EXPENSE_TAP must carry the 5 gateway fee (net + fee = gross, so the document balances).');

        $this->assertSame(3, JournalEntry::where('transaction_id', $posted->id)->count(), 'Exactly one balanced three-line document (clearing, fee expense, client advance).');
        $this->assertSame(1, Credit::where('payment_id', $payment->id)->where('type', Credit::TOPUP)->count());

        // Engine-sole-writer: the legacy closure never ran on the ON path (it would have created
        // a SECOND Transaction row for this payment_id -- exactly one exists).
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    /**
     * Verify-fix (lead finding, w7-final-gate.md §1a BLOCKER 1): end-to-end proof that
     * GATEWAY_CLEARING_TAP nets to EXACTLY ZERO across one full topup -> release cycle. Before the
     * fix, addCredit()'s ON draft debited clearing for the GROSS topup amount while
     * PaymentReleaseToCompanyBankAccProcess's own ON draft only ever credits it for the NET amount
     * (gross minus the gateway fee), leaving a permanent fee-sized residual stuck in
     * GATEWAY_CLEARING_TAP every cycle, forever, with nothing on the ON path to relieve it.
     */
    public function test_on_path_and_release_close_gateway_clearing_to_zero_across_a_full_cycle(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payment = $this->makePayment($agent, $client, 100.0, 5.0);
        $payment->payment_date = now()->format('Y-m-d');
        $payment->save();

        $response = app(ClientController::class)->addCredit($payment);
        $this->assertSame('success', $response['status']);

        \Illuminate\Support\Facades\Artisan::call('app:payment-release-to-company-bankacc-process');

        $this->assertSame(1, (int) $payment->fresh()->completed, 'Release must actually pick up and settle this payment for the cycle to be complete.');

        $clearing = app(AccountResolver::class)->resolve('GATEWAY_CLEARING_TAP', $company->id);
        $clearingDebit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('debit');
        $clearingCredit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('credit');

        $this->assertEqualsWithDelta(
            0.0,
            $clearingDebit - $clearingCredit,
            0.0005,
            'GATEWAY_CLEARING_TAP must net to ZERO after topup + release settle the same 100/5 payment -- any nonzero residual means the fee is stuck in clearing forever.'
        );
    }

    public function test_on_path_falls_back_to_cash_in_hand_when_payment_has_no_gateway(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payment = $this->makePayment($agent, $client, 30.0, 1.0);
        // 'payment_gateway' is a NOT NULL column -- '' is the "no gateway" sentinel addCredit()'s
        // own `$payment->payment_gateway ? ... : null` falsy check treats the same as null.
        $payment->payment_gateway = '';
        $payment->save();

        $response = app(ClientController::class)->addCredit($payment);
        $this->assertSame('success', $response['status']);

        $key = PaymentIdempotencyKey::forClientCreditTopup($client->id, $agent->id, $payment->id, 30.0);
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->firstOrFail();

        $cashInHand = app(AccountResolver::class)->resolve('CASH_IN_HAND', $company->id);
        $this->assertSame(
            1,
            JournalEntry::where('transaction_id', $posted->id)->where('account_id', $cashInHand->id)->where('debit', 30)->count()
        );
    }

    /**
     * W7.Y fix (gate item 1, BLOCKER): {@see PaymentIdempotencyKey::forClientCreditTopup()} now
     * embeds `$payment->id` -- REWRITTEN from the old "two different Payment rows for the same
     * (client, agent, amount) collapse to one document" behaviour this test used to enshrine as
     * intended (that was gate item 1's own BLOCKER: it meant a client topping up the identical
     * amount twice on two different days silently lost the second top-up's ledger document,
     * understating CLIENT_ADVANCE forever). Two GENUINELY distinct `Payment` rows for the same
     * (client, agent, amount) tuple must now each post their OWN document; only a re-post of the
     * SAME `Payment` row (the real "duplicate gateway webhook for one real top-up" scenario the
     * key still guards against) collapses to one.
     */
    public function test_on_path_two_different_payments_with_the_same_identity_each_post_their_own_document(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payment1 = $this->makePayment($agent, $client, 50.0, 2.0);
        $payment2 = $this->makePayment($agent, $client, 50.0, 2.0);

        $r1 = app(ClientController::class)->addCredit($payment1);
        $r2 = app(ClientController::class)->addCredit($payment2);

        $this->assertSame('success', $r1['status']);
        $this->assertSame('success', $r2['status']);

        $key1 = PaymentIdempotencyKey::forClientCreditTopup($client->id, $agent->id, $payment1->id, 50.0);
        $key2 = PaymentIdempotencyKey::forClientCreditTopup($client->id, $agent->id, $payment2->id, 50.0);
        $this->assertNotSame($key1, $key2, 'Two distinct Payment rows must derive two distinct keys.');

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key1)->count(),
            'Payment 1 must post its own document.'
        );
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key2)->count(),
            'Payment 2 -- a genuinely separate top-up event -- must ALSO post its own document, not be silently swallowed by Payment 1\'s key.'
        );

        $clientAdvance = app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $company->id);
        $net = (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('credit')
            - (float) DB::table('journal_entries')->where('account_id', $clientAdvance->id)->sum('debit');
        $this->assertEqualsWithDelta(100.0, $net, 0.0005, 'The net CLIENT_ADVANCE balance must reflect BOTH 50 top-ups (100 total), not one collapsed into the other.');

        $this->assertSame(2, Credit::where('client_id', $client->id)->where('type', Credit::TOPUP)->count());
        $this->assertSame(
            2,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->whereIn('idempotency_key', [$key1, $key2])->count(),
            'Exactly two distinct posted documents, one per payment.'
        );
    }

    /**
     * W7.Y fix (gate item 1, BLOCKER): a RE-POST of the exact SAME `Payment` row (a real duplicate
     * gateway callback for one real top-up event, the case this key must still collapse) must
     * still dedupe to exactly one document -- the SAME-payment guarantee the bare (client, agent,
     * amount) key used to (over-)provide is preserved by keying on (client, agent, payment,
     * amount) instead, since the payment id is identical on both calls.
     */
    public function test_on_path_reposting_the_same_payment_still_posts_exactly_one_document(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payment = $this->makePayment($agent, $client, 50.0, 2.0);

        $key = PaymentIdempotencyKey::forClientCreditTopup($client->id, $agent->id, $payment->id, 50.0);

        // Post the SAME draft twice directly through the seam (bypassing addCredit()'s own
        // top-of-method payment_id dedupe, which already refuses a second addCredit() call for
        // the identical Payment row before ever reaching the seam) -- proving the KEY ITSELF, not
        // just that upstream guard, still collapses a genuine re-post of one payment to one
        // document, exactly like every other feeder's idempotency key in this codebase.
        app(ClientController::class)->addCredit($payment);
        $seam = app(\App\Services\Accounting\PostingSeam::class);
        $draft = new \App\Services\Accounting\DocumentDraft(
            companyId: (int) $company->id,
            branchId: (int) $agent->branch->id,
            docType: 'RV',
            subType: 'TOPUP',
            docDate: now(),
            narration: 'retry',
            lines: [
                new \App\Services\Accounting\LineDraft(
                    purposeCode: 'CASH_IN_HAND',
                    accountId: null,
                    side: 'debit',
                    amount: 50.0,
                    currency: 'KWD',
                    originalAmount: 50.0,
                    exchangeRate: 1.0,
                    transactionType: 'RECEIPT',
                    description: 'retry',
                ),
                new \App\Services\Accounting\LineDraft(
                    purposeCode: 'CLIENT_ADVANCE',
                    accountId: null,
                    side: 'credit',
                    amount: 50.0,
                    currency: 'KWD',
                    originalAmount: 50.0,
                    exchangeRate: 1.0,
                    transactionType: 'CUSTOMERCREDITED',
                    description: 'retry',
                ),
            ],
            idempotencyKey: $key,
        );
        $seam->post($draft, fn () => null, 'client.add_credit.retry_test');

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->count(),
            'A re-post under the SAME payment-scoped key must still dedupe to exactly one document.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path -- client bears the gateway fee (gate item 3): the 4-line
    // Dr GATEWAY_CLEARING(A+g-f) . Dr GATEWAY_FEE_EXPENSE(f) . Cr CLIENT_ADVANCE(A)
    // . Cr GATEWAY_FEE_RECOVERY(g) shape.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Unlike {@see makePayment()} (which always sets a nonzero `gateway_fee` up front, forcing
     * addCredit()'s "imported payment" branch, whose bearer is hard-pinned to
     * `$payment->paymentMethod?->paid_by ?? 'Company'` -- always 'Company' with no PaymentMethod
     * row), this fixture leaves `gateway_fee`/`service_charge` falsy so addCredit() takes the
     * "fresh compute" branch and derives the bearer from `ChargeService::calculate()` against a
     * real `Charge` row -- see {@see makeTapChargeClientBears()}.
     */
    private function makeClientBornePayment(Agent $agent, Client $client, float $amount): Payment
    {
        return Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $agent->user_id,
            'amount' => $amount,
            'gateway_fee' => 0,
            'service_charge' => 0,
            'payment_gateway' => 'Tap',
            'payment_method_id' => null,
            'status' => 'completed',
            'completed' => 0,
        ]);
    }

    /**
     * Deterministic client-bears Charge fixture: charge_type Percent, self_charge (back-office,
     * client-facing) 5%, amount (contract/accounting cost) 4% -- for A=100 this yields g=5
     * (ChargeService::calculate()'s 'gatewayFee', the client-facing total_charge) and f=4
     * ('accountingFee', the real processor cost), two DISTINCT nonzero values so a test asserting
     * the 4-line shape can tell each leg's amount apart unambiguously.
     */
    private function makeTapChargeClientBears(int $companyId): Charge
    {
        $bankAccount = Account::where('company_id', $companyId)->where('name', 'Kuwait International Bank')->firstOrFail();
        $paymentGatewayAsset = Account::where('company_id', $companyId)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();
        $tapCharges = Account::where('company_id', $companyId)->where('name', 'TAP Charges')->firstOrFail();

        return Charge::factory()->create([
            'name' => 'Tap Gateway',
            'company_id' => $companyId,
            'acc_bank_id' => $bankAccount->id,
            'acc_fee_bank_id' => $paymentGatewayAsset->id,
            'acc_fee_id' => $tapCharges->id,
            'paid_by' => 'Client',
            'charge_type' => 'Percent',
            'amount' => 4.0,
            'self_charge' => 5.0,
            'extra_charge' => 0,
        ]);
    }

    /**
     * W7.Y fix (gate item 3, BLOCKER): the LOCKED bearer matrix
     * (.planning/accounting-waves/bearer-matrix-design.md:44, O1) requires a fourth line --
     * Cr GATEWAY_FEE_RECOVERY -- when the client bears the gateway fee. Before this fix, addCredit()'s
     * ON draft posted the IDENTICAL 3-line company-bears shape regardless of $paidBy, silently
     * discarding g (the client-facing fee) entirely -- it only ever surfaced in the response array.
     */
    public function test_on_path_client_bears_fee_posts_the_four_line_shape_and_nets_fee_recovery_by_g(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapChargeClientBears($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payment = $this->makeClientBornePayment($agent, $client, 100.0);

        $response = app(ClientController::class)->addCredit($payment);
        $this->assertSame('success', $response['status']);
        $this->assertSame('Client', $response['data']['paid_by']);
        $this->assertEqualsWithDelta(5.0, (float) $response['data']['gateway_fee'], 0.0005);
        $this->assertEqualsWithDelta(4.0, (float) $response['data']['accounting_fee'], 0.0005);

        $key = PaymentIdempotencyKey::forClientCreditTopup($client->id, $agent->id, $payment->id, 100.0);
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->firstOrFail();

        $this->assertSame(4, JournalEntry::where('transaction_id', $posted->id)->count(), 'Client-bears must post FOUR lines: clearing, fee expense, client advance, fee recovery.');

        $clearing = app(AccountResolver::class)->resolve('GATEWAY_CLEARING_TAP', $company->id);
        $clientAdvance = app(AccountResolver::class)->resolve('CLIENT_ADVANCE', $company->id);
        $feeExpense = app(AccountResolver::class)->resolve('GATEWAY_FEE_EXPENSE_TAP', $company->id);
        $feeRecovery = app(AccountResolver::class)->resolve('GATEWAY_FEE_RECOVERY', $company->id);

        $clearingDebit = (float) DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $clearing->id)->sum('debit');
        $advanceCredit = (float) DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $clientAdvance->id)->sum('credit');
        $feeExpenseDebit = (float) DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $feeExpense->id)->sum('debit');
        $feeRecoveryCredit = (float) DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $feeRecovery->id)->sum('credit');

        // A=100, f=4, g=5: Dr clearing (A+g-f=101) . Dr fee expense (f=4) . Cr advance (A=100)
        // . Cr fee recovery (g=5). Balances: 101+4 = 105 = 100+5.
        $this->assertEqualsWithDelta(101.0, $clearingDebit, 0.0005, 'GATEWAY_CLEARING_TAP must be debited A+g-f = 100+5-4 = 101.');
        $this->assertEqualsWithDelta(4.0, $feeExpenseDebit, 0.0005, 'GATEWAY_FEE_EXPENSE_TAP must carry f = 4 regardless of bearer.');
        $this->assertEqualsWithDelta(100.0, $advanceCredit, 0.0005, 'CLIENT_ADVANCE must credit the base amount A = 100, never A+g.');
        $this->assertEqualsWithDelta(5.0, $feeRecoveryCredit, 0.0005, 'GATEWAY_FEE_RECOVERY must net credit exactly g = 5.');

        $totalDebit = $clearingDebit + $feeExpenseDebit;
        $totalCredit = $advanceCredit + $feeRecoveryCredit;
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.0005, 'Document must balance.');
    }

    /**
     * W7.Y fix (gate item 3, CRITICAL CONSISTENCY REQUIREMENT): end-to-end proof that
     * GATEWAY_CLEARING_TAP nets to EXACTLY ZERO across one full topup -> release cycle for a
     * CLIENT-BEARS payment too (not just the company-bears variant {@see
     * test_on_path_and_release_close_gateway_clearing_to_zero_across_a_full_cycle()} already
     * covers). Before this fix, addCredit()'s client-bears draft posted the SAME 3-line
     * company-bears shape (no g anywhere), and even after adding the 4th line, the release
     * command's own shared $totalAmount computation would have credited clearing for only A-f,
     * stranding g in GATEWAY_CLEARING_TAP forever -- see PaymentReleaseToCompanyBankAccProcess's
     * own fee-recovery lookup fix for the other half of this proof.
     */
    public function test_client_bears_fee_and_release_close_gateway_clearing_to_zero_across_a_full_cycle(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapChargeClientBears($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $payment = $this->makeClientBornePayment($agent, $client, 100.0);
        $payment->payment_date = now()->format('Y-m-d');
        $payment->save();

        $response = app(ClientController::class)->addCredit($payment);
        $this->assertSame('success', $response['status']);

        \Illuminate\Support\Facades\Artisan::call('app:payment-release-to-company-bankacc-process');

        $this->assertSame(1, (int) $payment->fresh()->completed, 'Release must actually pick up and settle this client-borne payment for the cycle to be complete.');

        $clearing = app(AccountResolver::class)->resolve('GATEWAY_CLEARING_TAP', $company->id);
        $clearingDebit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('debit');
        $clearingCredit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('credit');

        $this->assertEqualsWithDelta(
            0.0,
            $clearingDebit - $clearingCredit,
            0.0005,
            'GATEWAY_CLEARING_TAP must net to ZERO after a client-borne topup + release settle the same payment -- any nonzero residual means g (the client-facing fee) is stuck in clearing forever.'
        );
    }
}
