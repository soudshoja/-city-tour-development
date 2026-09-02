<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\PaymentIdempotencyKey;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w7x. W7.X (.planning/accounting-waves/w7/w7-final-gate.md §1a, BLOCKER 2) --
 * PaymentReleaseToCompanyBankAccProcess ('app:payment-release-to-company-bankacc-process')
 * through the seam. Previously a scheduled daily raw ledger writer with zero PostingSeam
 * awareness. See that command's own class docblock for the full cutover shape.
 */
class PaymentReleaseToCompanyBankAccProcessW7XTest extends AccountingTestCase
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

    /**
     * Builds one "already-completed-but-unreleased" Payment plus the 'charges'-type JournalEntry
     * row (`type='charges'`, matched on `voucher_number`) the command's own totalAmount loop
     * reads -- the row `ClientController::addCredit()`'s ENTRY2 would have posted earlier in the
     * pipeline. amount=100, fee=5 -> netAmount=95 released to the bank.
     */
    private function makeReleasablePayment(Agent $agent, Client $client, int $companyId, string $date): Payment
    {
        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $agent->user_id,
            'amount' => 100.0,
            'gateway_fee' => 5.0,
            'payment_gateway' => 'Tap',
            'payment_method_id' => null,
            'status' => 'completed',
            'completed' => 0,
            'payment_date' => $date,
        ]);

        // A real Transaction header, not null, so this synthetic fee row doesn't trip
        // AccountingInvariants' "no orphaned journal_entries" check on tearDown -- this row
        // simulates a fee entry ClientController::addCredit()'s ENTRY2 would already have posted
        // earlier in the pipeline, which always carries a real transaction_id.
        $feeTransaction = Transaction::create([
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'transaction_type' => 'credit',
            'amount' => 5.0,
            'description' => 'Client Advance via '.$payment->voucher_number,
            'payment_id' => $payment->id,
            'transaction_date' => $date,
        ]);

        JournalEntry::create([
            'transaction_id' => $feeTransaction->id,
            'company_id' => $companyId,
            'voucher_number' => $payment->voucher_number,
            'type' => 'charges',
            'account_id' => Account::where('company_id', $companyId)->where('name', 'TAP Charges')->firstOrFail()->id,
            'debit' => 5.0,
            'credit' => 0,
            'balance' => 0,
            'name' => 'TAP Charges',
            'description' => 'Client Pays Gateway Fee: TAP Charges',
            'transaction_date' => $date,
        ]);

        // Offsetting leg so this synthetic fixture transaction is itself a balanced document --
        // AccountingInvariants (this base test case's own tearDown hook) asserts every
        // transaction for a tracked company balances (per transaction_id -- see
        // AccountingInvariants::assertLedgerBalanced()), not just the command's own output. This
        // fixture models a COMPANY-BEARS payment (the command's own fee-recovery lookup, W7.Y
        // fix gate item 3, must find NOTHING for it) -- deliberately NOT carrying this payment's
        // own voucher_number (unlike the 'charges' row above), so it can never be mistaken by
        // that lookup (`where('voucher_number', $payment->voucher_number)->where('type',
        // 'income')`) for a real client-borne GATEWAY_FEE_RECOVERY credit. A real company-bears
        // addCredit() call never writes an income-type row for this voucher at all (recordIncome
        // is false) -- this leg exists purely to keep the SYNTHETIC fixture transaction balanced,
        // not to model any real posting.
        JournalEntry::create([
            'transaction_id' => $feeTransaction->id,
            'company_id' => $companyId,
            'voucher_number' => null,
            'type' => 'income',
            'account_id' => Account::where('company_id', $companyId)->where('name', 'Gateway Fee Recovery')->firstOrFail()->id,
            'debit' => 0,
            'credit' => 5.0,
            'balance' => 0,
            'name' => 'Gateway Fee Recovery',
            'description' => 'Fixture-only balancing leg (not a real fee-recovery event)',
            'transaction_date' => $date,
        ]);

        return $payment;
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    /**
     * Client-bears companion to makeReleasablePayment() above. Where that fixture models a
     * COMPANY-bears payment with a synthetic two-line balancing shape, this one writes the REAL
     * legacy ENTRY1..4 rows {@see \App\Http\Controllers\ClientController::legacyAddCreditLedgerWrite()}
     * posts for a CLIENT-borne payment (paid_by='Client', recordIncome=true), mirrored here
     * field-for-field rather than invoked directly so this stays a pure Console-command test.
     * amount=100 (A), fee=5 (f):
     *   ENTRY1: Dr "Payment Gateway" (asset, chargeRecord->acc_fee_bank_id)   debit  A=100
     *   ENTRY2: Dr "TAP Charges"     (expense, chargeRecord->acc_fee_id)     debit  f=5
     *   ENTRY3: Cr "Gateway Fee Recovery" (income)                           credit f=5
     *   ENTRY4: Cr "Client > Payment Gateway" (liability)                    credit A=100
     * Balanced: 100+5 = 105 debit vs 5+100 = 105 credit -- a real, self-contained legacy
     * document, not a fixture-only balancing leg.
     */
    private function makeClientBearsReleasablePayment(Agent $agent, Client $client, int $companyId, string $date): Payment
    {
        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $agent->user_id,
            'amount' => 100.0,
            'gateway_fee' => 5.0,
            'payment_gateway' => 'Tap',
            'payment_method_id' => null,
            'status' => 'completed',
            'completed' => 0,
            'payment_date' => $date,
        ]);

        $transaction = Transaction::create([
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'transaction_type' => 'credit',
            'amount' => 100.0,
            'description' => 'Client Advance via '.$payment->voucher_number,
            'payment_id' => $payment->id,
            'transaction_date' => $date,
        ]);

        $paymentGatewayAsset = Account::where('company_id', $companyId)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();
        $tapCharges = Account::where('company_id', $companyId)->where('name', 'TAP Charges')->firstOrFail();
        $incomeAccount = Account::where('company_id', $companyId)->where('name', 'Gateway Fee Recovery')->firstOrFail();
        $liabilitiesAccount = Account::where('company_id', $companyId)->where('name', 'like', 'Liabilities%')->firstOrFail();
        $clientAdvance = Account::where('company_id', $companyId)->where('name', 'Client')->where('root_id', $liabilitiesAccount->id)->firstOrFail();
        $clientAdvancePaymentGateway = Account::where('company_id', $companyId)->where('name', 'Payment Gateway')->where('parent_id', $clientAdvance->id)->firstOrFail();

        // ENTRY 1: DEBIT Asset (Payment Gateway Bank)
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $agent->branch_id,
            'account_id' => $paymentGatewayAsset->id,
            'transaction_date' => $date,
            'description' => 'Client Pays by '.$client->full_name.' via (Assets): '.$paymentGatewayAsset->name,
            'debit' => 100.0,
            'credit' => 0,
            'balance' => 0,
            'name' => $paymentGatewayAsset->name,
            'type' => 'bank',
            'voucher_number' => $payment->voucher_number,
            'type_reference_id' => $paymentGatewayAsset->id,
        ]);

        // ENTRY 2: DEBIT Expense (Gateway Fee) -- the 'charges'-type row the release command's
        // own totalAmount loop reads (`type = 'charges'`, matched on voucher_number).
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $agent->branch_id,
            'account_id' => $tapCharges->id,
            'voucher_number' => $payment->voucher_number,
            'transaction_date' => $date,
            'description' => 'Client Pays Gateway Fee: '.$tapCharges->name,
            'debit' => 5.0,
            'credit' => 0,
            'balance' => 0,
            'name' => $tapCharges->name,
            'type' => 'charges',
            'type_reference_id' => $tapCharges->id,
        ]);

        // ENTRY 3: CREDIT Income (Fee Recovery) -- the 'income'-type row the release command's
        // fee-recovery add-back lookup reads (`type = 'income'`, matched on voucher_number).
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $agent->branch_id,
            'account_id' => $incomeAccount->id,
            'voucher_number' => $payment->voucher_number,
            'transaction_date' => $date,
            'description' => 'Gateway Fee Recovery from Client: '.$client->full_name,
            'debit' => 0,
            'credit' => 5.0,
            'balance' => 0,
            'name' => $incomeAccount->name,
            'type' => 'income',
            'type_reference_id' => $incomeAccount->id,
        ]);

        // ENTRY 4: CREDIT Liability (Client Advance)
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'account_id' => $clientAdvancePaymentGateway->id,
            'transaction_date' => $date,
            'description' => 'Advance Payment in voucher number: '.$payment->voucher_number,
            'debit' => 0,
            'credit' => 100.0,
            'balance' => 0,
            'name' => $client->full_name,
            'type' => 'advance',
            'voucher_number' => $payment->voucher_number,
            'type_reference_id' => $client->id,
        ]);

        return $payment;
    }

    /**
     * R5 (P2-EXIT-REPORT.md §7 residual register): a real INVOICE-payment fixture, shaped after
     * {@see \App\Http\Controllers\InvoiceController::createGatewayFeeRecoveryEntries()}'s own
     * legacy closure (its `type='income'`, `voucher_number=$payment->voucher_number`, `credit =
     * $grossUpAmount = round($accountingFee + $markupProfit + $roundingProfit, 3)` shape) -- as
     * opposed to {@see self::makeClientBearsReleasablePayment()} above, which mirrors the TOPUP
     * path (`ClientController::addCredit()`'s ENTRY3). The release command's own docblock claims
     * its fee-recovery add-back "GENERALIZES to client-borne INVOICE payments too, not just
     * topups" via the identical `type='income'`/`voucher_number` lookup shape; this fixture is
     * the first one in this suite that actually posts an Invoice-attached payment through that
     * exact shape rather than a topup-shaped one.
     *
     * Deliberately makes `grossUpAmount` (7.5 = accountingFee 5 + markupProfit 2 + roundingProfit
     * 0.5) DIFFERENT from the raw gateway fee (5) read off the 'charges' row -- so a test built on
     * this fixture can only pass if the release command's add-back reads back the REAL posted
     * income-leg credit (the grossUp identity), not merely re-adds the raw fee it already
     * subtracted. amount=100 (A), charges-fee=5 (f), grossUp=7.5 (g):
     * netAmount = A - f = 95; totalAmount = netAmount + g = 102.5.
     */
    private function makeInvoicePaymentClientBearsReleasablePayment(Agent $agent, Client $client, int $companyId, string $date): Payment
    {
        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'currency' => 'KWD',
            'sub_amount' => 100.0,
            'amount' => 100.0,
            'status' => 'paid',
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $agent->user_id,
            'amount' => 100.0,
            'gateway_fee' => 5.0,
            'payment_gateway' => 'Tap',
            'payment_method_id' => null,
            'status' => 'completed',
            'completed' => 0,
            'payment_date' => $date,
        ]);

        $transaction = Transaction::create([
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'transaction_type' => 'credit',
            'amount' => 100.0,
            'description' => 'Invoice payment via '.$payment->voucher_number,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'transaction_date' => $date,
        ]);

        $tapCharges = Account::where('company_id', $companyId)->where('name', 'TAP Charges')->firstOrFail();
        $incomeAccount = Account::where('company_id', $companyId)->where('name', 'Gateway Fee Recovery')->firstOrFail();
        $receivableAccount = Account::where('company_id', $companyId)->where('name', 'Clients')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();

        // 'charges'-type row the release command's own totalAmount loop reads to compute
        // netAmount = payment->amount - journalEntry->debit = 100 - 5 = 95.
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $agent->branch_id,
            'account_id' => $tapCharges->id,
            'voucher_number' => $payment->voucher_number,
            'invoice_id' => $invoice->id,
            'transaction_date' => $date,
            'description' => 'Client Pays Gateway Fee: '.$tapCharges->name,
            'debit' => 5.0,
            'credit' => 0,
            'balance' => 0,
            'name' => $tapCharges->name,
            'type' => 'charges',
        ]);

        // 'income'-type row -- EXACT shape createGatewayFeeRecoveryEntries()'s legacy closure
        // posts: credit = grossUpAmount (7.5), NOT the raw gateway fee (5). This is the row the
        // release command's own $feeRecoveryEntry lookup (type='income', matched on
        // voucher_number, orderBy('id')->first()) must read back.
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $agent->branch_id,
            'account_id' => $incomeAccount->id,
            'voucher_number' => $payment->voucher_number,
            'invoice_id' => $invoice->id,
            'transaction_date' => $date,
            'description' => 'Gateway fee recovered from client on invoice '.$invoice->invoice_number,
            'debit' => 0,
            'credit' => 7.5,
            'balance' => 0,
            'name' => $incomeAccount->name,
            'type' => 'income',
        ]);

        // Offsetting DEBIT leg (Clients/receivable) -- EXACT shape createGatewayFeeRecoveryEntries()'s
        // legacy closure posts alongside the income credit above (its own "DEBIT: Clients
        // (receivable)" leg), same grossUpAmount. Together these two legs are self-balanced.
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $agent->branch_id,
            'account_id' => $receivableAccount->id,
            'voucher_number' => $payment->voucher_number,
            'invoice_id' => $invoice->id,
            'transaction_date' => $date,
            'description' => 'Gateway fee recovered from client on invoice '.$invoice->invoice_number,
            'debit' => 7.5,
            'credit' => 0,
            'balance' => 0,
            'name' => $receivableAccount->name,
            'type' => 'receivable',
        ]);

        // Fixture-only balancing leg for the 'charges' debit above -- deliberately a SEPARATE
        // 'income' row with NO voucher_number (mirrors makeReleasablePayment()'s own "Offsetting
        // leg" convention above), so it can never be mistaken by the guarded
        // where('voucher_number', $payment->voucher_number) lookup for the real grossUp credit.
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $agent->branch_id,
            'account_id' => $incomeAccount->id,
            'voucher_number' => null,
            'transaction_date' => $date,
            'description' => 'Fixture-only balancing leg (not a real fee-recovery event)',
            'debit' => 0,
            'credit' => 5.0,
            'balance' => 0,
            'name' => $incomeAccount->name,
            'type' => 'income',
        ]);

        return $payment;
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

        $date = Carbon::now()->format('Y-m-d');
        $payment = $this->makeReleasablePayment($agent, $client, $company->id, $date);

        Artisan::call('app:payment-release-to-company-bankacc-process');

        $this->assertSame(1, (int) $payment->fresh()->completed);

        // Legacy shape, verbatim: 1 group Transaction + 2 JournalEntry rows, neither carrying an
        // idempotency_key.
        $groupTransactions = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('entity_type', 'company')->get();
        $this->assertSame(1, $groupTransactions->count());
        $this->assertNull($groupTransactions->first()->idempotency_key);

        $bankAccount = Account::where('company_id', $company->id)->where('name', 'Kuwait International Bank')->firstOrFail();
        $paymentGatewayAsset = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();

        $bankLine = JournalEntry::where('account_id', $bankAccount->id)->where('type', 'receivable')->first();
        $clearingLine = JournalEntry::where('account_id', $paymentGatewayAsset->id)->where('type', 'receivable')->first();

        $this->assertNotNull($bankLine);
        $this->assertNotNull($clearingLine);
        $this->assertEqualsWithDelta(95.0, (float) $bankLine->debit, 0.0005);
        $this->assertEqualsWithDelta(95.0, (float) $clearingLine->credit, 0.0005);
    }

    /**
     * DELIBERATE LEGACY CHANGE (W7.Y, gate item 3): OFF-path release settlement for a
     * CLIENT-borne payment. Legacy addCredit() posts ENTRY1..4 for A=100, f=5 as: Dr bank A=100,
     * Dr fee f=5, Cr recovery f=5, Cr advance A=100 -- legacy's own ENTRY1 debits the FULL A into
     * the gateway-fee bank/asset leg (unlike company-bears, which debits A-f there), because a
     * client-borne payment's gross bank movement already includes the fee it is passing through.
     * The release command's fee-recovery add-back (its own $feeRecoveryEntry lookup, matched on
     * voucher_number/type='income') now reads that same ENTRY3 credit (f=5) back and adds it onto
     * the uniform `amount - fee` figure it starts from: (A - f) + f = A = 100, NOT A - f = 95.
     * This is a deliberate behaviour change from pre-W7.Y (which credited only 95 here, stranding
     * a residual 5 against legacy's own 100 debit) -- see PaymentReleaseToCompanyBankAccProcess's
     * own fee-recovery-lookup comment for the full "CRITICAL CONSISTENCY REQUIREMENT" reasoning,
     * and this test's commit message for the explicit legacy-consistency declaration.
     */
    public function test_off_path_client_bears_release_settles_full_amount_via_fee_recovery_addback(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);
        config(['accounting.engine.enabled' => false]);

        $date = Carbon::now()->format('Y-m-d');
        $payment = $this->makeClientBearsReleasablePayment($agent, $client, $company->id, $date);

        Artisan::call('app:payment-release-to-company-bankacc-process');

        $this->assertSame(1, (int) $payment->fresh()->completed);

        $groupTransactions = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('entity_type', 'company')->get();
        $this->assertSame(1, $groupTransactions->count());
        $this->assertNull($groupTransactions->first()->idempotency_key);

        $bankAccount = Account::where('company_id', $company->id)->where('name', 'Kuwait International Bank')->firstOrFail();
        $paymentGatewayAsset = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();

        $bankLine = JournalEntry::where('account_id', $bankAccount->id)->where('type', 'receivable')->first();
        $clearingLine = JournalEntry::where('account_id', $paymentGatewayAsset->id)->where('type', 'receivable')->first();

        $this->assertNotNull($bankLine);
        $this->assertNotNull($clearingLine);
        // DELIBERATE: totalAmount = (A - f) + f = A = 100, via the fee-recovery add-back -- not
        // A - f = 95. See this test's own docblock.
        $this->assertEqualsWithDelta(100.0, (float) $bankLine->debit, 0.0005);
        $this->assertEqualsWithDelta(100.0, (float) $clearingLine->credit, 0.0005);
    }

    /**
     * R5 (P2-EXIT-REPORT.md §7 residual register): pins the release command's income-JE
     * credit add-back arithmetic for a real INVOICE payment (as opposed to the topup-shaped
     * fixture the test above uses) -- the exact gap `p2_5/p25e-verify.md` and the exit report's
     * own R5 entry flag as untested. Uses {@see self::makeInvoicePaymentClientBearsReleasablePayment()},
     * whose grossUpAmount (7.5) is deliberately NOT equal to the raw gateway fee (5) it posts on
     * the separate 'charges' row -- so this test can only pass if the release command reads back
     * the REAL posted grossUp income credit (the "what the client was actually charged at the
     * gateway" identity `PaymentReleaseToCompanyBankAccProcess::handle()`'s own docblock derives),
     * not a re-derivation of the bearer formula from the raw fee alone.
     *
     * netAmount = amount(100) - charges-fee(5) = 95; totalAmount = netAmount + grossUp(7.5) = 102.5.
     */
    public function test_off_path_invoice_payment_release_adds_back_the_real_grossup_income_credit(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);
        config(['accounting.engine.enabled' => false]);

        $date = Carbon::now()->format('Y-m-d');
        $payment = $this->makeInvoicePaymentClientBearsReleasablePayment($agent, $client, $company->id, $date);

        Artisan::call('app:payment-release-to-company-bankacc-process');

        $this->assertSame(1, (int) $payment->fresh()->completed);

        $groupTransactions = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('entity_type', 'company')->get();
        $this->assertSame(1, $groupTransactions->count());

        $bankAccount = Account::where('company_id', $company->id)->where('name', 'Kuwait International Bank')->firstOrFail();
        $paymentGatewayAsset = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('root', fn ($q) => $q->where('name', 'Assets'))->firstOrFail();

        $bankLine = JournalEntry::where('account_id', $bankAccount->id)->where('type', 'receivable')->first();
        $clearingLine = JournalEntry::where('account_id', $paymentGatewayAsset->id)->where('type', 'receivable')->first();

        $this->assertNotNull($bankLine);
        $this->assertNotNull($clearingLine);
        // DELIBERATE (the grossUp identity, not the raw fee): totalAmount = (A - f) + g
        //   = (100 - 5) + 7.5 = 102.5, NOT (A - f) + f = 100 and NOT A - f = 95.
        $this->assertEqualsWithDelta(102.5, (float) $bankLine->debit, 0.0005, 'The release settlement must add back the real posted grossUp income credit (7.5), not the raw fee (5) it already subtracted.');
        $this->assertEqualsWithDelta(102.5, (float) $clearingLine->credit, 0.0005);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path -- Dr configured bank leaf / Cr GATEWAY_CLEARING_{gateway}.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_on_path_posts_one_balanced_jv_and_marks_payments_released(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $date = Carbon::now()->format('Y-m-d');
        $payment = $this->makeReleasablePayment($agent, $client, $company->id, $date);

        Artisan::call('app:payment-release-to-company-bankacc-process');

        $this->assertSame(1, (int) $payment->fresh()->completed, 'Payment must be marked released regardless of path.');

        $key = PaymentIdempotencyKey::forPaymentReleaseGroup($company->id, 'Tap', $date, [$payment->id]);
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first();
        $this->assertNotNull($posted, 'ON path must post a real engine document under the per-batch key.');
        $this->assertSame('JV', $posted->doc_type);

        $bankAccount = Account::where('company_id', $company->id)->where('name', 'Kuwait International Bank')->firstOrFail();
        $clearing = app(AccountResolver::class)->resolve('GATEWAY_CLEARING_TAP', $company->id);

        $bankDebit = (float) DB::table('journal_entries')->where('account_id', $bankAccount->id)->sum('debit');
        $bankCredit = (float) DB::table('journal_entries')->where('account_id', $bankAccount->id)->sum('credit');
        $clearingDebit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('debit');
        $clearingCredit = (float) DB::table('journal_entries')->where('account_id', $clearing->id)->sum('credit');

        $this->assertEqualsWithDelta(95.0, $bankDebit - $bankCredit, 0.0005, 'The real bank leaf must net a 95 DEBIT (money settling in).');
        $this->assertEqualsWithDelta(95.0, $clearingCredit - $clearingDebit, 0.0005, 'GATEWAY_CLEARING_TAP must net a 95 CREDIT (draining as it settles).');

        $this->assertSame(2, JournalEntry::where('transaction_id', $posted->id)->count(), 'Exactly one balanced two-line JV.');
    }

    public function test_on_path_reprocessing_the_identical_batch_posts_exactly_one_document(): void
    {
        [$company, $branch] = $this->makeCompany();
        [$agent, $client] = $this->makeAgentAndClient($branch);
        $this->makeTapCharge($company->id);

        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $date = Carbon::now()->format('Y-m-d');
        $payment = $this->makeReleasablePayment($agent, $client, $company->id, $date);

        Artisan::call('app:payment-release-to-company-bankacc-process');
        $this->assertSame(1, (int) $payment->fresh()->completed);

        // Simulate a re-fire over the IDENTICAL batch (e.g. a crash between the post and the
        // completed=1 write, or a scheduler double-fire) by resetting completed back to 0 for
        // the SAME payment id -- the group's (company, gateway, date, payment ids) identity, and
        // therefore its idempotency key, is unchanged.
        $payment->completed = 0;
        $payment->save();

        Artisan::call('app:payment-release-to-company-bankacc-process');
        $this->assertSame(1, (int) $payment->fresh()->completed);

        $key = PaymentIdempotencyKey::forPaymentReleaseGroup($company->id, 'Tap', $date, [$payment->id]);
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->count(),
            'The stable per-batch idempotency key must dedupe the identical re-run to exactly one JV document.'
        );

        $bankAccount = Account::where('company_id', $company->id)->where('name', 'Kuwait International Bank')->firstOrFail();
        $bankNet = (float) DB::table('journal_entries')->where('account_id', $bankAccount->id)->sum('debit')
            - (float) DB::table('journal_entries')->where('account_id', $bankAccount->id)->sum('credit');
        $this->assertEqualsWithDelta(95.0, $bankNet, 0.0005, 'Net must reflect exactly ONE 95 settlement, not two.');
    }
}
