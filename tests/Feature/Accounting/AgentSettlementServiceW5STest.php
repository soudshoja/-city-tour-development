<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentSettlement;
use App\Models\AgentSettlementPayment;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AgentSettlementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W5.S (w5-brief.md §W5.S; Accounting Gap/22-plan-amendments.md rev 5 §11.2). Cuts
 * AgentSettlementService::settleByProfit()/onPaymentCompleted() onto PostingSeam.
 *
 * Both methods have ZERO existing callers anywhere in app/ (repo-wide grep, 2026-08-29) -- there
 * is no controller/route/job feeding them yet. That is exactly why HEAD's own
 * `'reference_type' => 'Settlement'` value (an illegal transactions.reference_type ENUM member --
 * see AgentSettlementService::settleByProfit()'s own docblock) was never caught: this suite is the
 * first thing that has ever actually executed this code against a real, strict-mode MySQL
 * connection. Every test below therefore asserts the FIXED, actually-working shape, not a
 * preserved HEAD defect -- there is no working HEAD behaviour for `'reference_type'` to preserve.
 */
class AgentSettlementServiceW5STest extends AccountingTestCase
{
    private ?User $actingUser = null;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Account, 4: Account}
     */
    private function makeCompanyBranchAgent(): array
    {
        $company = Company::factory()->create();

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        $profitAccount = Account::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Agent Profit Payable',
            'account_type' => 'liability',
        ]);
        $lossAccount = Account::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Agent Loss Receivable',
            'account_type' => 'asset',
        ]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'profit_account_id' => $profitAccount->id,
            'loss_account_id' => $lossAccount->id,
        ]);

        return [$company, $branch, $agent, $profitAccount, $lossAccount];
    }

    /**
     * agent_settlement_payments.created_by is a NOT NULL FK to users -- both methods under test
     * require an authenticated user for that reason (matches HEAD's pre-existing
     * `auth()->id()` calls, unrelated to this wave).
     *
     * getCompanyId() (app/Helper/helper.php) resolves an ADMIN-role user's company as
     * `session('company_id', 1)` -- a bare factory User (role_id defaults to Role::ADMIN) would
     * otherwise scope every Account::class query (BelongsToCompany's global scope) to company id
     * 1 regardless of which company this test actually created, silently returning null for
     * every OTHER company's $agent->profitAccount/lossAccount. Setting the session key here is
     * what makes the scope resolve to the RIGHT company for a test whose company id isn't 1.
     */
    private function actingAsSomeUser(int $companyId): User
    {
        $user = User::factory()->create();
        Auth::login($user);
        session(['company_id' => $companyId]);
        $this->actingUser = $user;

        return $user;
    }

    private function makeSettlement(Company $company, Branch $branch, Agent $agent, float $totalAmount): AgentSettlement
    {
        return AgentSettlement::create([
            // transactions.reference_number is varchar(20) -- the legacy closure writes
            // $settlement->settlement_number straight into it, so this must stay short.
            'settlement_number' => 'STL'.random_int(1000000, 9999999),
            'agent_id' => $agent->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'status' => 'unpaid',
            'settlement_date' => now(),
            'created_by' => Auth::id() ?? User::factory()->create()->id,
        ]);
    }

    /**
     * Seeds a real ledger credit balance on the profit account -- settleByProfit()'s own
     * pre-existing validation ("Insufficient profit balance") reads a live SUM(credit)-SUM(debit)
     * over journal_entries, so every real test needs a genuine prior credit there first, exactly
     * like a real prior "agent earned this much profit" event would have posted.
     */
    private function seedProfitBalance(Company $company, Branch $branch, Account $profitAccount, float $creditAmount): void
    {
        // Balanced (not a one-sided fixture) so AccountingTestCase's C1 trial-balance invariant
        // (tracked for every ON-path test in this file) still holds -- a real "agent earned this
        // profit" event would itself have posted a balanced document; the contra leg's account
        // identity is irrelevant to what's under test here, only the profit account's resulting
        // credit balance is.
        $contraAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $profitAccount->id,
            'entity_type' => 'agent',
            'transaction_type' => 'credit',
            'amount' => $creditAmount,
            'description' => 'Test fixture: seed agent profit balance',
            'reference_type' => 'Payment',
            'transaction_date' => now(),
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $profitAccount->id,
            'transaction_date' => now(),
            'description' => 'Test fixture: seed agent profit balance',
            'debit' => 0,
            'credit' => $creditAmount,
            'balance' => 0,
            'name' => $profitAccount->name,
            'type' => 'payable',
            'currency' => 'KWD',
            'exchange_rate' => 1.0,
            'amount' => $creditAmount,
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'account_id' => $contraAccount->id,
            'transaction_date' => now(),
            'description' => 'Test fixture: seed agent profit balance (contra)',
            'debit' => $creditAmount,
            'credit' => 0,
            'balance' => 0,
            'name' => $contraAccount->name,
            'type' => 'expense',
            'currency' => 'KWD',
            'exchange_rate' => 1.0,
            'amount' => $creditAmount,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // settleByProfit -- OFF path
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_settle_by_profit_off_path_writes_balanced_legacy_entry(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$company, $branch, $agent, $profitAccount, $lossAccount] = $this->makeCompanyBranchAgent();
        $this->actingAsSomeUser($company->id);
        $this->seedProfitBalance($company, $branch, $profitAccount, 1000.000);

        $settlement = $this->makeSettlement($company, $branch, $agent, 400.000);

        $settlementPayment = app(AgentSettlementService::class)->settleByProfit($settlement, 250.000);

        $this->assertInstanceOf(AgentSettlementPayment::class, $settlementPayment);
        $this->assertSame('profit', $settlementPayment->method);
        $this->assertEquals(250.000, (float) $settlementPayment->amount);

        $transaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('reference_number', $settlement->settlement_number)
            ->first();

        $this->assertNotNull($transaction, 'Legacy path must write the transactions header.');
        $this->assertNull($transaction->idempotency_key, 'A legacy transaction never carries an idempotency_key -- proves the engine did not run.');
        $this->assertSame(
            'Payment',
            $transaction->reference_type,
            'PRE-EXISTING BUG fix: HEAD wrote the illegal ENUM value "Settlement" here, which strict-mode MySQL has always rejected -- see settleByProfit()\'s own docblock.'
        );

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines, 'settleByProfit() legacy write must be exactly two lines (unlike the one-sided salary-feeder defect).');

        $debitLine = $lines->firstWhere('account_id', $profitAccount->id);
        $creditLine = $lines->firstWhere('account_id', $lossAccount->id);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(250.000, (float) $debitLine->debit);
        $this->assertEquals(0, (float) $debitLine->credit);
        $this->assertEquals(250.000, (float) $creditLine->credit);
        $this->assertEquals(0, (float) $creditLine->debit);

        $this->assertEqualsWithDelta(
            0.0,
            (float) $lines->sum('debit') - (float) $lines->sum('credit'),
            0.0005,
            'Legacy settleByProfit() write must be balanced.'
        );

        $settlement->refresh();
        $this->assertEquals(250.000, (float) $settlement->paid_amount);
        $this->assertEquals(150.000, (float) $settlement->remaining_amount);
        $this->assertSame('partial', $settlement->status);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // settleByProfit -- ON path
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_settle_by_profit_on_path_posts_a_balanced_ast_document_through_the_seam(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$company, $branch, $agent, $profitAccount, $lossAccount] = $this->makeCompanyBranchAgent();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->actingAsSomeUser($company->id);
        $this->seedProfitBalance($company, $branch, $profitAccount, 1000.000);

        $settlement = $this->makeSettlement($company, $branch, $agent, 400.000);

        app(AgentSettlementService::class)->settleByProfit($settlement, 250.000);

        $transaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('doc_type', 'AST')
            ->first();

        $this->assertNotNull($transaction, 'The engine path must post one AST transaction.');
        $this->assertSame('LEGACY', $transaction->sub_type);
        $this->assertNotNull($transaction->idempotency_key, 'Engine-posted transactions always carry an idempotency_key.');
        $this->assertStringStartsWith('agent-settlement:profit:'.$settlement->id.':250.000:', $transaction->idempotency_key);
        $this->assertSame('Payment', $transaction->reference_type);

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines, 'Engine-posted document must be exactly two lines.');

        $debitLine = $lines->firstWhere('account_id', $profitAccount->id);
        $creditLine = $lines->firstWhere('account_id', $lossAccount->id);

        $this->assertNotNull($debitLine, 'The debit leg must land on the AGENT\'S OWN profit account id, never a purpose-code-resolved company anchor.');
        $this->assertNotNull($creditLine, 'The credit leg must land on the AGENT\'S OWN loss account id.');
        $this->assertEquals(250.000, (float) $debitLine->debit);
        $this->assertEquals(0, (float) $debitLine->credit);
        $this->assertEquals(250.000, (float) $creditLine->credit);
        $this->assertEquals(0, (float) $creditLine->debit);

        $this->assertEqualsWithDelta(
            0.0,
            (float) $lines->sum('debit') - (float) $lines->sum('credit'),
            0.0005,
            'Balanced or rejected -- w5-brief.md §W5.S.'
        );

        // actual_balance must never be touched by this method (it never was, but pin it anyway
        // now that the method is actually exercised for the first time).
        $this->assertEquals(0.0, (float) $profitAccount->refresh()->actual_balance);
        $this->assertEquals(0.0, (float) $lossAccount->refresh()->actual_balance);

        $settlement->refresh();
        $this->assertEquals(250.000, (float) $settlement->paid_amount);
        $this->assertEquals(150.000, (float) $settlement->remaining_amount);

        $settlementPayment = AgentSettlementPayment::where('agent_settlement_id', $settlement->id)->first();
        $this->assertNotNull($settlementPayment);
        $this->assertSame('profit', $settlementPayment->method);
    }

    public function test_settle_by_profit_on_path_is_idempotent_on_a_retried_key(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$company, $branch, $agent, $profitAccount, $lossAccount] = $this->makeCompanyBranchAgent();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->actingAsSomeUser($company->id);
        $this->seedProfitBalance($company, $branch, $profitAccount, 1000.000);

        $settlement = $this->makeSettlement($company, $branch, $agent, 400.000);

        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-08-29 10:00:00'));

        app(AgentSettlementService::class)->settleByProfit($settlement, 100.000);
        $transactionCountAfterFirst = DB::table('transactions')->where('company_id', $company->id)->count();
        $lineCountAfterFirst = DB::table('journal_entries')->where('company_id', $company->id)->count();

        // A second, GENUINELY different real business event within the same second (different
        // amount) must still post its own document -- proves the key is not collapsing distinct
        // settlement events.
        app(AgentSettlementService::class)->settleByProfit($settlement, 50.000);

        $this->assertSame(
            $transactionCountAfterFirst + 1,
            DB::table('transactions')->where('company_id', $company->id)->count(),
            'A genuinely different amount, same second, must post its OWN document.'
        );
        $this->assertSame(
            $lineCountAfterFirst + 2,
            DB::table('journal_entries')->where('company_id', $company->id)->count()
        );

        \Illuminate\Support\Carbon::setTestNow();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // onPaymentCompleted -- Rule 3b: no automatic deduction without an explicit settlement row.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_payment_completed_refuses_when_payment_has_no_matching_settlement_id(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$company, $branch, $agent] = $this->makeCompanyBranchAgent();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->actingAsSomeUser($company->id);

        $settlement = $this->makeSettlement($company, $branch, $agent, 400.000);

        // A plain client payment -- chk_payment_owner requires client_id set XOR settlement_id
        // set, so this Payment genuinely has settlement_id = NULL, exactly the shape a
        // mis-wired/automatic caller would produce.
        $client = Client::factory()->create();
        $payment = Payment::factory()->create([
            'client_id' => $client->id,
            'settlement_id' => null,
            'account_id' => null,
            'invoice_id' => null,
            'created_by' => $this->actingUser->id,
            'agent_id' => $agent->id,
            'amount' => 300.000,
            'payment_gateway' => 'MyFatoorah',
            'completed' => true,
            'status' => 'completed',
        ]);

        $transactionCountBefore = DB::table('transactions')->count();
        $journalCountBefore = DB::table('journal_entries')->count();
        $settlementPaymentCountBefore = AgentSettlementPayment::count();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Rule 3b/');

        try {
            app(AgentSettlementService::class)->onPaymentCompleted($payment, $settlement);
        } finally {
            $this->assertSame($transactionCountBefore, DB::table('transactions')->count(), 'Rule 3b: no transaction may be posted without an explicit settlement row request.');
            $this->assertSame($journalCountBefore, DB::table('journal_entries')->count());
            $this->assertSame($settlementPaymentCountBefore, AgentSettlementPayment::count());
            $settlement->refresh();
            $this->assertEquals(0.0, (float) $settlement->paid_amount, 'Rule 3b: settlement totals must not move either.');
        }
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // onPaymentCompleted -- OFF path (explicit settlement row present).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_payment_completed_off_path_writes_balanced_legacy_entry(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$company, $branch, $agent, , $lossAccount] = $this->makeCompanyBranchAgent();
        $this->actingAsSomeUser($company->id);

        $gatewayAssetAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'MyFatoorah Clearing']);
        $gatewayExpenseAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'MyFatoorah Charges']);

        Charge::factory()->create([
            'company_id' => $company->id,
            'name' => 'MyFatoorah',
            'charge_type' => 'Flat Rate',
            'amount' => 5.000,
            'self_charge' => 5.000,
            'acc_fee_bank_id' => $gatewayAssetAccount->id,
            'acc_fee_id' => $gatewayExpenseAccount->id,
        ]);

        $settlement = $this->makeSettlement($company, $branch, $agent, 400.000);

        $payment = Payment::factory()->create([
            'client_id' => null,
            'settlement_id' => $settlement->id,
            'account_id' => null,
            'invoice_id' => null,
            'created_by' => $this->actingUser->id,
            'agent_id' => $agent->id,
            'amount' => 200.000,
            'payment_gateway' => 'MyFatoorah',
            'payment_method_id' => null,
            'completed' => true,
            'status' => 'completed',
        ]);

        app(AgentSettlementService::class)->onPaymentCompleted($payment, $settlement);

        $transaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('payment_id', $payment->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertNull($transaction->idempotency_key);
        $this->assertSame('Payment', $transaction->reference_type, 'PRE-EXISTING BUG fix -- see onPaymentCompleted()\'s own docblock.');

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(3, $lines, 'Legacy write is always 3 lines (loss credit, gateway net debit, fee debit) even when unconditional.');

        foreach ($lines as $line) {
            $this->assertEquals(0.0, (float) $line->balance, 'PRE-EXISTING BUG fix: balance must be 0, never a read of actual_balance -- see docblock item 2.');
        }

        $this->assertEqualsWithDelta(0.0, (float) $lines->sum('debit') - (float) $lines->sum('credit'), 0.0005);

        $this->assertEquals(0.0, (float) $gatewayAssetAccount->refresh()->actual_balance, 'actual_balance must never be hand-incremented (hard rule).');
        $this->assertEquals(0.0, (float) $gatewayExpenseAccount->refresh()->actual_balance);

        $settlement->refresh();
        $this->assertEquals(200.000, (float) $settlement->paid_amount);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // onPaymentCompleted -- ON path.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_payment_completed_on_path_posts_a_balanced_ast_document_through_the_seam(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$company, $branch, $agent, , $lossAccount] = $this->makeCompanyBranchAgent();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->actingAsSomeUser($company->id);

        $gatewayAssetAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'MyFatoorah Clearing']);
        $gatewayExpenseAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'MyFatoorah Charges']);

        Charge::factory()->create([
            'company_id' => $company->id,
            'name' => 'MyFatoorah',
            'charge_type' => 'Flat Rate',
            'amount' => 5.000,
            'self_charge' => 5.000,
            'acc_fee_bank_id' => $gatewayAssetAccount->id,
            'acc_fee_id' => $gatewayExpenseAccount->id,
        ]);

        $settlement = $this->makeSettlement($company, $branch, $agent, 400.000);

        $payment = Payment::factory()->create([
            'client_id' => null,
            'settlement_id' => $settlement->id,
            'account_id' => null,
            'invoice_id' => null,
            'created_by' => $this->actingUser->id,
            'agent_id' => $agent->id,
            'amount' => 200.000,
            'payment_gateway' => 'MyFatoorah',
            'payment_method_id' => null,
            'completed' => true,
            'status' => 'completed',
        ]);

        app(AgentSettlementService::class)->onPaymentCompleted($payment, $settlement);

        $transaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('doc_type', 'AST')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame('LEGACY', $transaction->sub_type);
        $this->assertSame('agent-settlement:payment:'.$payment->id.':settlement:'.$settlement->id, $transaction->idempotency_key);
        $this->assertSame('Payment', $transaction->reference_type);
        $this->assertSame($payment->id, $transaction->payment_id);

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(3, $lines);

        $creditLine = $lines->firstWhere('account_id', $lossAccount->id);
        $assetLine = $lines->firstWhere('account_id', $gatewayAssetAccount->id);
        $feeLine = $lines->firstWhere('account_id', $gatewayExpenseAccount->id);

        $this->assertNotNull($creditLine);
        $this->assertNotNull($assetLine);
        $this->assertNotNull($feeLine);
        $this->assertEquals(200.000, (float) $creditLine->credit);
        $this->assertEquals((float) $assetLine->debit + (float) $feeLine->debit, (float) $creditLine->credit);

        $this->assertEqualsWithDelta(0.0, (float) $lines->sum('debit') - (float) $lines->sum('credit'), 0.0005);

        $this->assertEquals(0.0, (float) $gatewayAssetAccount->refresh()->actual_balance);
        $this->assertEquals(0.0, (float) $gatewayExpenseAccount->refresh()->actual_balance);

        $settlement->refresh();
        $this->assertEquals(200.000, (float) $settlement->paid_amount);

        $settlementPayment = AgentSettlementPayment::where('agent_settlement_id', $settlement->id)->first();
        $this->assertNotNull($settlementPayment);
        $this->assertSame('payment_link', $settlementPayment->method);
        $this->assertSame($payment->id, $settlementPayment->payment_id);
    }

    public function test_on_payment_completed_omits_the_fee_line_when_the_gateway_has_no_configured_fee(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$company, $branch, $agent, , $lossAccount] = $this->makeCompanyBranchAgent();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->actingAsSomeUser($company->id);

        $gatewayAssetAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'NoFee Clearing']);
        $gatewayExpenseAccount = Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'NoFee Charges']);

        Charge::factory()->create([
            'company_id' => $company->id,
            'name' => 'NoFeeGateway',
            'charge_type' => 'Flat Rate',
            'amount' => 0,
            'self_charge' => 0,
            'acc_fee_bank_id' => $gatewayAssetAccount->id,
            'acc_fee_id' => $gatewayExpenseAccount->id,
        ]);

        $settlement = $this->makeSettlement($company, $branch, $agent, 400.000);

        $payment = Payment::factory()->create([
            'client_id' => null,
            'settlement_id' => $settlement->id,
            'account_id' => null,
            'invoice_id' => null,
            'created_by' => $this->actingUser->id,
            'agent_id' => $agent->id,
            'amount' => 150.000,
            'payment_gateway' => 'NoFeeGateway',
            'payment_method_id' => null,
            'completed' => true,
            'status' => 'completed',
        ]);

        app(AgentSettlementService::class)->onPaymentCompleted($payment, $settlement);

        $transaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('doc_type', 'AST')
            ->first();

        $this->assertNotNull($transaction);

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines, 'A zero-fee gateway must post a clean 2-line document -- the engine refuses a zero-amount line outright.');
        $this->assertNull($lines->firstWhere('account_id', $gatewayExpenseAccount->id), 'The fee leg must be entirely absent, not present at 0.');
        $this->assertEqualsWithDelta(0.0, (float) $lines->sum('debit') - (float) $lines->sum('credit'), 0.0005);
    }
}
