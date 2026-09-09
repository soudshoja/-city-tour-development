<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\ReceiptPostingRule;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 wave 2, item W2-2 — receipts under owner ruling R-CT3.
 *
 * Two halves, both of which failed before this wave:
 *
 *  (a) **Which statuses post, and which reverse, is configured** —
 *      `config('accounting.receipt.posting_statuses' | 'reversing_statuses' | 'draft_statuses')`,
 *      read by {@see ReceiptPostingRule}. The behavioural payoff is the bounce defect: until wave
 *      2, `ReceiptVoucherController::bounce()` reversed only the cheque-CLEARANCE journal and left
 *      the receipt document `rv:{id}` (Dr cheques-in-hand / Cr AR) standing with the invoice still
 *      marked `paid` — so a bounced cheque left a collected receivable for money that never
 *      arrived.
 *
 *  (b) **The instrument account comes from the configured payment-method account**
 *      (`charges.acc_bank_id`, keyed by the new `invoice_receipts.settlement_channel`), never from
 *      the `CASH_IN_HAND` constant the old `resolveInstrumentLeg()` fell through to.
 *
 * Plus the AR-party assertion: every receipt line carries its client party, including the
 * account-type credit leg that used to carry none.
 */
class W22ReceiptStatusAndInstrumentTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $company->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function makeReceipt(Company $company, Branch $branch, Client $client, array $overrides = []): InvoiceReceipt
    {
        return InvoiceReceipt::create(array_merge([
            'type' => 'account',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'account_id' => $this->accountByCode($company->id, '1351')->id,
            'client_id' => $client->id,
            'amount' => 100,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'status' => InvoiceReceipt::STATUS_PENDING,
        ], $overrides));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (a) The configured status vocabulary
    // ────────────────────────────────────────────────────────────────────────────────────────

    /** Case 1: the four verdicts, each from config — not from a hard-coded status list. */
    public function test_receipt_status_vocabulary_is_configured(): void
    {
        /** @var ReceiptPostingRule $rule */
        $rule = app(ReceiptPostingRule::class);

        $this->assertTrue($rule->decide('approved')->shouldPost);
        $this->assertSame('status_posts', $rule->decide('approved')->reason);

        $this->assertTrue($rule->decide('bounced')->shouldReverse);
        $this->assertFalse($rule->decide('bounced')->shouldPost);
        $this->assertSame('status_reverses', $rule->decide('bounced')->reason);

        $this->assertTrue($rule->decide('reversed')->shouldReverse);
        $this->assertTrue($rule->decide('rejected')->shouldReverse);

        $draft = $rule->decide('pending');
        $this->assertFalse($draft->shouldPost);
        $this->assertFalse($draft->shouldReverse);
        $this->assertSame('status_is_draft', $draft->reason);

        $unknown = $rule->decide('some-status-nobody-configured');
        $this->assertFalse($unknown->shouldPost);
        $this->assertFalse($unknown->shouldReverse);
        $this->assertSame('status_not_configured', $unknown->reason);

        // MUTATION PROOF for "configured, not hard-coded": move `approved` out of the posting
        // list and the verdict must change. A rule that keeps saying shouldPost here is reading a
        // constant, not the config.
        config(['accounting.receipt.posting_statuses' => []]);
        $this->assertFalse(app(ReceiptPostingRule::class)->decide('approved')->shouldPost);
    }

    /**
     * Case 2 — THE DEFECT. A cheque receipt against an invoice is approved (invoice -> paid), the
     * cheque clears, and then it bounces. After wave 2 the receipt document itself is reversed and
     * the invoice goes back to unpaid.
     *
     * Before wave 2 this test fails on BOTH assertions: `bounce()` reversed only `rv-clear:{id}`,
     * so the `rv:{id}` transaction stayed `posted` and the invoice stayed `paid`.
     */
    public function test_bounced_cheque_reverses_the_receipt_document_and_reopens_the_invoice(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'unpaid',
            'invoice_date' => now(),
        ]);

        $receipt = $this->makeReceipt($company, $branch, $client, [
            'type' => 'invoice',
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'amount' => 100,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]],
            'cheque_no' => 'CHQ-1',
            'cheque_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $invoice->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $receipt->status);
        $this->assertSame('paid', $invoice->status);
        $receiptTransactionId = (int) $receipt->transaction_id;

        // Clear the cheque into a real bank leaf, then bounce it.
        $bank = $this->accountByCode($company->id, '1201');

        $this->actingAs($admin)->post(route('receipt-voucher.clear', $receipt->id), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('receipt-voucher.bounce', $receipt->id), [
            'bounce_fee_amount' => 0,
        ])->assertRedirect();

        $receipt->refresh();
        $invoice->refresh();

        $this->assertSame(InvoiceReceipt::STATUS_BOUNCED, $receipt->status);

        $receiptTransaction = Transaction::withoutGlobalScopes()->findOrFail($receiptTransactionId);
        $this->assertSame(
            'reversed',
            $receiptTransaction->posting_status,
            'A bounced cheque must reverse the RECEIPT document, not only the clearance JV — the money never arrived.'
        );

        $this->assertSame(
            'unpaid',
            $invoice->status,
            'A bounced cheque must reopen the invoice it was allocated against.'
        );

        // And the reversal is a dated REV document, never a delete: the original lines survive.
        $this->assertGreaterThan(0, JournalEntry::where('transaction_id', $receiptTransactionId)->count());
    }

    /** Case 3: a receipt already at a reversing status can never be (re-)posted by approve(). */
    public function test_a_receipt_at_a_reversing_status_is_refused_by_post_voucher(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $receipt = $this->makeReceipt($company, $branch, $client, [
            'status' => InvoiceReceipt::STATUS_BOUNCED,
        ]);

        $controller = app(\App\Http\Controllers\ReceiptVoucherController::class);
        $method = new \ReflectionMethod($controller, 'postVoucher');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/REVERSING status/');

        $method->invoke($controller, $receipt->fresh());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) The instrument account comes from configured master data
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 4 — the R-CT3 half. A receipt whose `settlement_channel` names a configured payment
     * method debits THAT method's own account. Before wave 2 the same receipt landed in
     * CASH_IN_HAND, because the resolver's last branch was the constant.
     */
    public function test_instrument_leg_uses_the_configured_payment_method_account(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $bank = $this->accountByCode($company->id, '1201');
        $cash = $this->accountByCode($company->id, '1120');

        Charge::withoutGlobalScopes()->create([
            'name' => 'KNET',
            'type' => 'knet',
            'amount' => 0,
            'company_id' => $company->id,
            'acc_bank_id' => $bank->id,
            'is_active' => true,
        ]);

        $receipt = $this->makeReceipt($company, $branch, $client, [
            'settlement_channel' => 'KNET',
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $lines = JournalEntry::where('transaction_id', $receipt->transaction_id)->get();

        $debit = $lines->firstWhere('debit', '>', 0);
        $this->assertNotNull($debit);
        $this->assertSame(
            $bank->id,
            (int) $debit->account_id,
            'The instrument leg must debit the payment method\'s CONFIGURED account, not the cash constant.'
        );
        $this->assertNotSame($cash->id, (int) $debit->account_id);
    }

    /** Case 5: no channel and no bank account — the configured fallback purpose, still not a
     *  hard-coded account id. */
    public function test_a_receipt_with_no_channel_falls_back_to_the_configured_purpose(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $cash = $this->accountByCode($company->id, '1120');

        $receipt = $this->makeReceipt($company, $branch, $client);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $debit = JournalEntry::where('transaction_id', $receipt->transaction_id)->where('debit', '>', 0)->first();

        $this->assertNotNull($debit);
        $this->assertSame($cash->id, (int) $debit->account_id);
    }

    /**
     * Case 6: a channel whose payment method has NO account configured must not silently invent
     * one — it falls back, and (the point) a channel that names nothing this company owns cannot
     * reach another tenant's account.
     */
    public function test_a_channel_with_no_configured_account_falls_back_rather_than_guessing(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $cash = $this->accountByCode($company->id, '1120');

        Charge::withoutGlobalScopes()->create([
            'name' => 'Unconfigured Wallet',
            'type' => 'wallet',
            'amount' => 0,
            'company_id' => $company->id,
            'acc_bank_id' => null,
            'is_active' => true,
        ]);

        $receipt = $this->makeReceipt($company, $branch, $client, [
            'settlement_channel' => 'Unconfigured Wallet',
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $debit = JournalEntry::where('transaction_id', $receipt->transaction_id)->where('debit', '>', 0)->first();

        $this->assertNotNull($debit);
        $this->assertSame($cash->id, (int) $debit->account_id);
    }

    /**
     * Case 7: an explicit `bank_account_id` on the voucher still wins over the channel — an
     * operator's own choice beats a default.
     */
    public function test_an_explicit_bank_account_beats_the_configured_channel(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $knetBank = $this->accountByCode($company->id, '1201');
        $chosenBank = $this->accountByCode($company->id, '1204');

        Charge::withoutGlobalScopes()->create([
            'name' => 'KNET',
            'type' => 'knet',
            'amount' => 0,
            'company_id' => $company->id,
            'acc_bank_id' => $knetBank->id,
            'is_active' => true,
        ]);

        $receipt = $this->makeReceipt($company, $branch, $client, [
            'settlement_channel' => 'KNET',
            'bank_account_id' => $chosenBank->id,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $debit = JournalEntry::where('transaction_id', $receipt->transaction_id)->where('debit', '>', 0)->first();

        $this->assertSame($chosenBank->id, (int) $debit->account_id);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (c) AR party on every line
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Case 8: an account-type receipt's CREDIT leg carries the client party. Before wave 2 that
     * leg passed no `partyAccountRef` at all, so an account receipt could not be attributed to the
     * client who paid it — the one receipt line still missing from CT-A3-WAVE1 §4.4's
     * control-vs-party reconciliation.
     */
    public function test_every_receipt_line_carries_the_client_party(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $receipt = $this->makeReceipt($company, $branch, $client);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $lines = JournalEntry::where('transaction_id', $receipt->transaction_id)->get();

        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $this->assertSame(
                (int) $client->id,
                (int) $line->type_reference_id,
                'Every receipt line must carry the paying client as its party.'
            );
        }
    }
}
