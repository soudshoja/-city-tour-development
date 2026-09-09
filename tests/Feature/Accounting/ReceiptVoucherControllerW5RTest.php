<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\ReceiptVoucherController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\VoucherOptions;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w5r-controller. W5.R (w5-brief.md §W5.R) — ReceiptVoucherController through
 * {@see \App\Services\Accounting\PostingSeam}: store/approve/update/delete/clear/bounce, overpay
 * policy, cheque instrument, threshold auto-approve, policy enforcement.
 */
class ReceiptVoucherControllerW5RTest extends AccountingTestCase
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
        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        // NOTE: AgentType::$fillable is `['name']` only ('id' is NOT mass-assignable) -- passing
        // ['id' => 2] to firstOrCreate()'s create() branch silently drops it and lets MySQL
        // auto-increment assign whatever id is next, which is NOT reliably 2 once earlier tests in
        // this same process have advanced the counter (InnoDB AUTO_INCREMENT survives a rolled-back
        // transaction). Always use the returned model's own ->id, never assume the literal.
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function makeUnpaidInvoice(Client $client, Agent $agent, float $amount = 100.000): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => $amount,
            'status' => 'unpaid',
            'invoice_date' => now(),
        ]);
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // store() lifecycle
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_creates_pending_draft_with_no_threshold_and_does_not_post(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110'); // Payable control -- any leaf works for 'account' type

        $response = $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'Test account receipt',
        ]);

        $response->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($invoiceReceipt);
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $invoiceReceipt->status);
        $this->assertNull($invoiceReceipt->transaction_id);
    }

    public function test_store_auto_approves_and_posts_balanced_document_under_threshold(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '100',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');
        $cashInHand = $this->accountByCode($company->id, '1120');

        $response = $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'Auto-approved receipt',
        ]);

        $response->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);
        $this->assertNotNull($invoiceReceipt->transaction_id);

        $lines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $this->assertCount(2, $lines);

        $debit = $lines->firstWhere('account_id', $cashInHand->id);
        $credit = $lines->firstWhere('account_id', $account->id);
        $this->assertNotNull($debit);
        $this->assertNotNull($credit);
        $this->assertEqualsWithDelta(50.0, (float) $debit->debit, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $credit->credit, 0.001);
    }

    public function test_store_over_threshold_leaves_voucher_pending_for_manual_approve(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '10',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 500,
            'remarks_create' => 'Above threshold',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $invoiceReceipt->status);
        $this->assertNull($invoiceReceipt->transaction_id);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Invoice allocation + overpay policy
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_approve_applies_allocation_and_posts_remainder_to_client_advance_under_credit_policy(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $invoice = $this->makeUnpaidInvoice($client, $agent, 100.000);

        $cashInHand = $this->accountByCode($company->id, '1120');
        $clientAdvance = $this->accountByCode($company->id, '2632');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'invoice',
            'client_id' => $client->id,
            'amount' => 120,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]],
            'remarks_create' => 'Overpaid invoice receipt',
        ])->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $invoiceReceipt->status);

        $this->actingAs($admin)->post(route('receipt-voucher.approve', $invoiceReceipt->id))
            ->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);

        $lines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $this->assertCount(3, $lines);

        $debit = $lines->firstWhere('account_id', $cashInHand->id);
        $advanceCredit = $lines->firstWhere('account_id', $clientAdvance->id);
        $this->assertNotNull($debit);
        $this->assertEqualsWithDelta(120.0, (float) $debit->debit, 0.001);
        $this->assertNotNull($advanceCredit);
        $this->assertEqualsWithDelta(20.0, (float) $advanceCredit->credit, 0.001);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }

    public function test_store_refuses_overpay_when_company_policy_is_block(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::RV_OVERPAY_POLICY_KEY,
            'value' => 'block',
            'type' => 'string',
        ]);

        $invoice = $this->makeUnpaidInvoice($client, $agent, 100.000);
        $before = InvoiceReceipt::count();

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'invoice',
            'client_id' => $client->id,
            'amount' => 120,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]],
            'remarks_create' => 'Blocked overpay',
        ])->assertRedirect();

        $this->assertSame($before, InvoiceReceipt::count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // update()/delete() reverse+repost / reverse
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_on_pending_row_updates_fields_without_posting(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'Original',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        $this->actingAs($admin)->put(route('receipt-voucher.update', $invoiceReceipt->id), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 75,
            'remarks_create' => 'Updated',
        ])->assertRedirect();

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $invoiceReceipt->status);
        $this->assertEqualsWithDelta(75.0, (float) $invoiceReceipt->amount, 0.001);
        $this->assertNull($invoiceReceipt->transaction_id);
    }

    public function test_update_on_posted_row_reverses_and_reposts_a_balanced_replacement(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'Original posted',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);
        $oldTransactionId = $invoiceReceipt->transaction_id;

        $this->actingAs($admin)->put(route('receipt-voucher.update', $invoiceReceipt->id), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 90,
            'remarks_create' => 'Corrected amount',
        ])->assertRedirect();

        $invoiceReceipt->refresh();
        $this->assertNotEquals($oldTransactionId, $invoiceReceipt->transaction_id);

        $oldTransaction = Transaction::withoutGlobalScopes()->find($oldTransactionId);
        $this->assertSame('reversed', $oldTransaction->posting_status);

        $newLines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $totalDebit = (float) $newLines->sum('debit');
        $totalCredit = (float) $newLines->sum('credit');
        $this->assertEqualsWithDelta(90.0, $totalDebit, 0.001);
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001);
    }

    public function test_delete_on_pending_row_hard_deletes(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'To be deleted',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        $this->actingAs($admin)->delete(route('receipt-voucher.destroy', $invoiceReceipt->id))->assertRedirect();

        $this->assertNull(InvoiceReceipt::find($invoiceReceipt->id));
    }

    public function test_delete_on_posted_row_reverses(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'To be reversed',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $transactionId = $invoiceReceipt->transaction_id;

        $this->actingAs($admin)->delete(route('receipt-voucher.destroy', $invoiceReceipt->id))->assertRedirect();

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_REVERSED, $invoiceReceipt->status);

        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $transactionId)->first();
        $this->assertNotNull($reversal);
    }

    public function test_delete_is_blocked_when_a_line_is_reconciled(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'Reconciled line',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->limit(1)->update(['reconciled' => 1]);

        $this->actingAs($admin)->delete(route('receipt-voucher.destroy', $invoiceReceipt->id))->assertRedirect();

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Cheque instrument: PDC float, clear, bounce
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_future_dated_cheque_posts_to_cheques_in_hand_then_clear_moves_it_to_bank(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');
        $chequesInHand = $this->accountByCode($company->id, '1215');
        $bank = $this->accountByCode($company->id, '1201');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 60,
            'cheque_no' => 'CHQ-001',
            'cheque_date' => now()->addDays(10)->toDateString(),
            'remarks_create' => 'PDC receipt',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $lines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $debit = $lines->firstWhere('account_id', $chequesInHand->id);
        $this->assertNotNull($debit, 'Future-dated cheque should debit CHEQUES_IN_HAND (1215).');
        $this->assertEqualsWithDelta(60.0, (float) $debit->debit, 0.001);

        $this->actingAs($admin)->post(route('receipt-voucher.clear', $invoiceReceipt->id), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect();

        $invoiceReceipt->refresh();
        $this->assertNotNull($invoiceReceipt->cheque_clearance_date);

        $clearanceTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'rv-clear:'.$invoiceReceipt->id)->first();
        $this->assertNotNull($clearanceTransaction);

        $clearLines = JournalEntry::where('transaction_id', $clearanceTransaction->id)->get();
        $this->assertEqualsWithDelta(60.0, (float) $clearLines->firstWhere('account_id', $bank->id)?->debit, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $clearLines->firstWhere('account_id', $chequesInHand->id)?->credit, 0.001);
    }

    public function test_bounce_reverses_clearance_and_posts_bounce_fee_dbn(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');
        $bank = $this->accountByCode($company->id, '1201');
        $bankCharges = $this->accountByCode($company->id, '5222');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'client_id' => $client->id,
            'account_id' => $account->id,
            'amount' => 60,
            'cheque_no' => 'CHQ-002',
            'cheque_date' => now()->addDays(5)->toDateString(),
            'remarks_create' => 'PDC to be bounced',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        $this->actingAs($admin)->post(route('receipt-voucher.clear', $invoiceReceipt->id), [
            'bank_account_id' => $bank->id,
        ]);

        $this->actingAs($admin)->post(route('receipt-voucher.bounce', $invoiceReceipt->id), [
            'bounce_fee_amount' => 5,
        ])->assertRedirect();

        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_BOUNCED, $invoiceReceipt->status);
        $this->assertNull($invoiceReceipt->cheque_clearance_date);

        $clearanceTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'rv-clear:'.$invoiceReceipt->id)->first();
        $this->assertSame('reversed', $clearanceTransaction->posting_status);

        $dbnTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'rv-bounce-fee:'.$invoiceReceipt->id)->first();
        $this->assertNotNull($dbnTransaction);

        $dbnLines = JournalEntry::where('transaction_id', $dbnTransaction->id)->get();
        $this->assertEqualsWithDelta(5.0, (float) $dbnLines->firstWhere('account_id', $bankCharges->id)?->credit, 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ReceiptVoucherPolicy enforcement
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_approve_is_403_for_unauthorized_role(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'For 403 test',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        // Needs a real, company-resolvable Agent (not a bare role) -- otherwise
        // EnsureModuleEnabled's own deliberate 404-before-403 (see that middleware's docblock)
        // fires first, since getCompanyId() can't resolve a company for an Agent-role user with
        // no Agent row at all, and this test is asserting the POLICY denies the ability, not the
        // module gate denying the whole route.
        $agentRoleUser = User::factory()->create(['role_id' => Role::AGENT]);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentRoleUser->id, 'type_id' => $agent->type_id]);

        $this->actingAs($agentRoleUser)
            ->post(route('receipt-voucher.approve', $invoiceReceipt->id))
            ->assertForbidden();
    }

    /** EnsureModuleEnabled deliberately 404s (never 403) so a disabled module stays invisible
     * rather than merely locked -- see that middleware's own docblock. */
    public function test_store_is_404_when_accounting_module_disabled(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        // makeFixture() grants the module via GrantsAccountingModule; this test needs it OFF, so
        // it must update that same row (not insert a second one) and drop the per-request memo.
        // NB: Setting::setValueAttribute()'s boolean branch does `$value ? 'true' : 'false'` --
        // on an UPDATE of an already-typed row the string 'false' is truthy in PHP and would
        // silently re-enable the module, so this must pass the real boolean false.
        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'module.accounting'],
            ['value' => false, 'type' => 'boolean'],
        );
        \App\Models\Company::forgetModuleCache();

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'Module disabled',
        ])->assertNotFound();
    }

    public function test_off_path_update_on_posted_row_marks_the_old_transaction_reversed(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        // Engine deliberately left OFF.
        (new SystemAccountsSeeder)->run();

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'OFF path, to be updated',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $oldTransactionId = $invoiceReceipt->transaction_id;

        $this->actingAs($admin)->put(route('receipt-voucher.update', $invoiceReceipt->id), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 65,
            'remarks_create' => 'OFF path, updated',
        ])->assertRedirect();

        $invoiceReceipt->refresh();
        $this->assertNotEquals($oldTransactionId, $invoiceReceipt->transaction_id);

        $oldTransaction = Transaction::withoutGlobalScopes()->find($oldTransactionId);
        $this->assertSame('reversed', $oldTransaction->posting_status, 'markTransactionReversed() must actually persist -- posting_status is not mass-assignable.');

        $newLines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $this->assertEqualsWithDelta(65.0, (float) $newLines->sum('debit'), 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path: same real accounts, no name-LIKE lookups, still balanced
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_still_posts_a_balanced_document_to_the_same_accounts(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        // Engine deliberately left OFF (config default) -- but system_accounts must still exist
        // for AccountResolver, per this controller's own "Why that is a documented deviation" note.
        (new SystemAccountsSeeder)->run();

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
        ]);

        $account = $this->accountByCode($company->id, '2110');
        $cashInHand = $this->accountByCode($company->id, '1120');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'OFF path receipt',
        ])->assertRedirect();

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);

        $lines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $this->assertCount(2, $lines);
        $this->assertNotNull($lines->firstWhere('account_id', $cashInHand->id));
        $this->assertNotNull($lines->firstWhere('account_id', $account->id));

        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // import()/invoiceJournalEntry() -- post-verify CRITICAL 1-3 fix. Previously: (1) a live
    // POST /receipt-voucher/import route bypassed PostingSeam entirely via raw Transaction::create()
    // + JournalEntry::create() with no balance check, for every company regardless of the engine
    // flag; (2) it resolved accounts via Account::where('name', 'Accounts Receivable')/('Clients')/
    // ->where('name','like',...) -- the exact anti-pattern this sub-wave kills; (3) import() had NO
    // Gate::authorize() call at all. All three verified fixed below.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /** CRITICAL 3 fix: import() now gates on the SAME 'create' ability store() uses -- an
     * agent-role user store() already rejects must be rejected here too. */
    public function test_import_is_403_for_unauthorized_role(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $agentRoleUser = User::factory()->create(['role_id' => Role::AGENT]);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentRoleUser->id, 'type_id' => $agent->type_id]);

        $this->actingAs($agentRoleUser)
            ->post(route('receipt-voucher.import'), [
                'receipt_reference' => 'RV-DOES-NOT-EXIST',
                'agent_name' => 'irrelevant',
                'client_name' => 'irrelevant',
                'invoice_number' => 'irrelevant',
            ])
            ->assertForbidden();
    }

    /** @return array{0: Invoice, 1: InvoiceDetail, 2: Task, 3: Transaction} */
    private function makeUninvoicedImportFixture(Company $company, Branch $branch, Agent $agent, Client $client, float $sell = 200.000, float $cost = 100.000, ?float $partialAmount = null): array
    {
        $supplier = Supplier::factory()->create();

        $invoice = $this->makeUnpaidInvoice($client, $agent, $sell);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'type' => 'flight',
            'total' => $cost,
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_price' => $sell,
        ]);

        // import()'s own outer code (unchanged by this fix) always creates this row, with
        // amount == invoice->amount, BEFORE calling invoiceJournalEntry() -- reproduced here since
        // this fixture calls invoiceJournalEntry() directly (see that test's own docblock for why).
        \App\Models\InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'service_charge' => 0,
            'amount' => $partialAmount ?? $sell,
            'status' => 'paid',
            'type' => 'cash',
            'payment_gateway' => 'Cash',
        ]);

        // Transaction::factory() is NOT used here -- its own definition() sets a
        // 'transaction_id' key with no matching database column at all (a pre-existing,
        // unrelated bug in database/factories/TransactionFactory.php, verified: any
        // Transaction::factory()->create() call fails with "Unknown column 'transaction_id'"
        // regardless of this fix). forceCreate() bypasses that broken factory definition
        // entirely and builds exactly the pre-existing "already-recorded receipt" row
        // import()/invoiceJournalEntry() expects to receive, matching writeLegacyTransaction()'s
        // own established use of forceCreate() for the non-fillable columns.
        $transaction = Transaction::forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $sell,
            'description' => 'Imported historical receipt',
            'reference_number' => 'RV-IMPORT-'.$invoice->id,
            'reference_type' => 'Receipt',
            'invoice_id' => $invoice->id,
            'name' => $client->name,
            'transaction_date' => now(),
            'posting_status' => 'posted',
            'idempotency_key' => null,
        ]);

        return [$invoice, $invoiceDetail, $task, $transaction];
    }

    /**
     * CRITICAL 1-2 fix, direct unit-level coverage of {@see ReceiptVoucherController::invoiceJournalEntry()}
     * (the method the previous verify's adversarial probe caught bypassing the seam via a live
     * route). Not driven through the full HTTP import() request: that outer method has its own,
     * separate, PRE-EXISTING bug unrelated to this fix (`Client::where('name', $transaction->client_id)`
     * -- compares a client NAME column against an integer id and essentially never resolves a real
     * client) which would make an HTTP-level fixture depend on undefined MySQL string/int coercion
     * behaviour rather than testing this fix. invoiceJournalEntry() itself does its own, correct
     * `Client::find($invoice->client_id)` lookup and is exercised directly here exactly as
     * `import()`'s own unchanged call site invokes it.
     */
    public function test_invoice_journal_entry_routes_uninvoiced_task_sale_through_posting_seam_with_no_name_lookup(): void
    {
        // Forces makeFixture()'s own `AgentType::firstOrCreate(['id' => 2], ...)` to find THIS
        // row rather than fall through to an auto-incremented id ('id' is not mass-assignable on
        // AgentType -- see makeFixture()'s own comment) -- an explicit-PK raw insert is immune to
        // the drifting AUTO_INCREMENT high-water mark within this test's own fresh transaction,
        // guaranteeing $agent->type_id below is deterministically 2, landing in import()'s own
        // `in_array($agent->type_id, [2, 3])` commission branch (commission = 0.15*(200-100) = 15).
        \Illuminate\Support\Facades\DB::table('agent_type')->insert([
            'id' => 2, 'name' => 'type-2', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $this->assertSame(2, $agent->type_id, 'Fixture precondition: commission math below assumes type_id=2.');

        // agents.commission defaults to 0.00 (NOT NULL, migration
        // 2025_05_27_174700_add_column_in_agents_table.php), so `$agent->commission ?? 0.15`
        // never actually falls back for a freshly-factoried agent -- set a real rate explicitly
        // so the commission math below (0.15 * (200-100) = 15) is deterministic.
        $agent->update(['commission' => 0.15]);

        [$invoice, $invoiceDetail, $task, $transaction] = $this->makeUninvoicedImportFixture($company, $branch, $agent, $client);

        $receivable = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        $serviceRevenue = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'flight');
        // CT-A3 E4 (CT-F38), 2026-09-09 — was SALARY_EXPENSE / SALARY_PAYABLE (5160 / 2201, a
        // PAYROLL pair). A commission on a sale posts to COMMISSION_EXPENSE / COMMISSION_PAYABLE
        // (5130 / 2210), where the legacy ledger already put it.
        $commissionExpense = app(AccountResolver::class)->resolve('COMMISSION_EXPENSE', $company->id);
        $commissionPayable = app(AccountResolver::class)->resolve('COMMISSION_PAYABLE', $company->id);
        $salaryExpense = app(AccountResolver::class)->resolve('SALARY_EXPENSE', $company->id);
        $salaryPayable = app(AccountResolver::class)->resolve('SALARY_PAYABLE', $company->id);

        $result = app(ReceiptVoucherController::class)->invoiceJournalEntry($transaction, $invoice);

        $this->assertSame('success', $result['status'] ?? null, json_encode($result));

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':sale')
            ->first();
        $this->assertNotNull($saleTransaction, 'invoiceJournalEntry() must post through PostingSeam under a real idempotency key, not a raw Transaction::create() with a NULL key.');
        $this->assertNotNull($saleTransaction->idempotency_key);

        $saleLines = JournalEntry::where('transaction_id', $saleTransaction->id)->get();
        $this->assertCount(2, $saleLines);
        $debit = $saleLines->firstWhere('account_id', $receivable->id);
        $credit = $saleLines->firstWhere('account_id', $serviceRevenue->id);
        $this->assertNotNull($debit, 'AR leg must land on the RECEIVABLE_CONTROL purpose-code account, not a name-resolved "Clients" leaf.');
        $this->assertNotNull($credit, 'Income leg must land on the SERVICE_REVENUE/flight purpose-code account, not a name-LIKE "%Flight Booking%" leaf.');
        $this->assertEqualsWithDelta(200.0, (float) $debit->debit, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $credit->credit, 0.001);
        $this->assertEqualsWithDelta((float) $saleLines->sum('debit'), (float) $saleLines->sum('credit'), 0.001, 'Sale document must be balanced.');

        $commissionTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':agent-commission')
            ->first();
        $this->assertNotNull($commissionTransaction, 'Agent (type_id=2) commission pair must also post through the seam.');

        $commissionLines = JournalEntry::where('transaction_id', $commissionTransaction->id)->get();
        $this->assertEqualsWithDelta(15.0, (float) ($commissionLines->firstWhere('account_id', $commissionExpense->id)?->debit ?? 0), 0.001);
        $this->assertEqualsWithDelta(15.0, (float) ($commissionLines->firstWhere('account_id', $commissionPayable->id)?->credit ?? 0), 0.001);
        $this->assertNull($commissionLines->firstWhere('account_id', $salaryExpense->id), 'CT-F38: 5160 Agent Salaries must receive nothing from a commission.');
        $this->assertNull($commissionLines->firstWhere('account_id', $salaryPayable->id), 'CT-F38: 2201 Salaries & Wages Payable must receive nothing from a commission.');
        $this->assertEqualsWithDelta((float) $commissionLines->sum('debit'), (float) $commissionLines->sum('credit'), 0.001);
    }

    /** Same fix, OFF path -- writeLegacyTransaction() still resolves every line by purpose code
     * (never Account::where('name', ...)), matching this controller's own established "OFF-path
     * parity means real accounts, not literal HEAD bytes" convention. */
    public function test_invoice_journal_entry_off_path_still_posts_to_the_same_purpose_code_accounts(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        // Engine deliberately left OFF, but system_accounts must still exist for AccountResolver.
        (new SystemAccountsSeeder)->run();

        [$invoice, $invoiceDetail, $task, $transaction] = $this->makeUninvoicedImportFixture($company, $branch, $agent, $client);

        $receivable = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
        $serviceRevenue = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'flight');

        $result = app(ReceiptVoucherController::class)->invoiceJournalEntry($transaction, $invoice);
        $this->assertSame('success', $result['status'] ?? null, json_encode($result));

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':sale')
            ->first();
        $this->assertNotNull($saleTransaction);

        $saleLines = JournalEntry::where('transaction_id', $saleTransaction->id)->get();
        $this->assertNotNull($saleLines->firstWhere('account_id', $receivable->id));
        $this->assertNotNull($saleLines->firstWhere('account_id', $serviceRevenue->id));
        $this->assertEqualsWithDelta((float) $saleLines->sum('debit'), (float) $saleLines->sum('credit'), 0.001);
    }

    /** Balanced-or-rejected guard: a mismatched receivable/income pair (the two amounts HEAD never
     * verified were equal) must refuse to post rather than write an unbalanced document. */
    public function test_invoice_journal_entry_refuses_when_receivable_and_income_amounts_mismatch(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        // partialAmount (150) deliberately DIFFERS from the invoice's own amount (200) --
        // invoiceJournalEntry()'s own ->first() picks up this row as "the" invoicePartial.
        [$invoice, $invoiceDetail, $task, $transaction] = $this->makeUninvoicedImportFixture($company, $branch, $agent, $client, 200.000, 100.000, 150.000);

        $result = app(ReceiptVoucherController::class)->invoiceJournalEntry($transaction, $invoice);

        $this->assertSame('error', $result['status'] ?? null);

        $posted = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':sale')
            ->first();
        $this->assertNull($posted, 'A mismatched (unbalanced) pair must never reach PostingSeam::post() at all.');
    }
}
