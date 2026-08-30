<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\BankPayment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\VoucherOptions;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w5u-ui. W5.U (w5-brief.md §W5.U) — RV/PV create/edit screens rewired to the new
 * ReceiptVoucherController/BankPaymentController contract, the two new voucher settings wired end
 * to end through SettingController, and the cheque-image upload this sub-wave adds.
 *
 * Verify criteria (w5-brief.md §W5.U, numbered to match):
 *   1. Settings form persists voucher_approval_threshold/pv_allow_overdraft and the next post
 *      honours it.
 *   2. Posting an RV from the screen with a split allocation yields the allocation lines + remainder
 *      disposition; a PV with pv_allow_overdraft=false against an insufficient balance is refused
 *      with the check inside the same transaction as the post.
 *   3. Clear/bounce shapes are unchanged; reconciled/locked vouchers cannot be edited or reversed
 *      through the screen (the controller action itself refuses, not just a hidden button).
 *   4. Unauthorized users hit 403 on approve/reverse.
 */
class ReceiptVoucherBankPaymentW5UTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
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

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
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

    /** Seeds the bank leaf with an opening credit -- mirrors BankPaymentControllerW5PTest's own
     * identical helper (a fresh CoaSeeder company otherwise starts every leaf at a true zero
     * balance). */
    private function fundBank(Company $company, Branch $branch, string $bankCode, float $amount): Account
    {
        $bank = $this->accountByCode($company->id, $bankCode);
        $incomeSuspense = $this->accountByCode($company->id, '4133');

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Opening funding',
            'reference_type' => 'Invoice', 'reference_number' => 'FUND-'.substr(uniqid(), -8),
            'name' => 'Opening funding', 'transaction_date' => now(),
            'doc_type' => 'JV', 'doc_year' => (int) now()->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => 'fund:'.$bank->id.':'.uniqid(),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bank->id, 'transaction_date' => now(), 'description' => 'Opening funding',
            'debit' => $amount, 'credit' => 0, 'name' => $bank->name, 'type' => 'bank', 'currency' => 'KWD',
            'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'FUND',
        ]);
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $incomeSuspense->id, 'transaction_date' => now(), 'description' => 'Opening funding',
            'debit' => 0, 'credit' => $amount, 'name' => $incomeSuspense->name, 'type' => 'income', 'currency' => 'KWD',
            'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'FUND',
        ]);

        return $bank;
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Criterion 1 -- settings form persists the two new options, and the next post honours them
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_accounting_settings_tab_loads_the_two_new_voucher_options(): void
    {
        [$company, , , , $admin] = $this->makeFixture();

        $response = $this->actingAs($admin)->getJson(route('settings.accounting-settings'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['settings' => ['voucher_approval_threshold', 'pv_allow_overdraft']]);

        $this->assertNull($response->json('settings.voucher_approval_threshold'));
        $this->assertFalse($response->json('settings.pv_allow_overdraft'));
    }

    /** Full loop: SettingController::storeAccountingSettings() persists under VoucherOptions' own
     * key constants, and the very next RV post (through the real store() route, not a hand-seeded
     * Setting row) honours the threshold this form just saved. */
    public function test_saving_voucher_approval_threshold_via_settings_form_makes_the_next_rv_auto_approve(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $payload = $this->baseAccountingSettingsPayload();
        $payload['voucher_approval_threshold'] = 100;
        $payload['pv_allow_overdraft'] = false;

        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('100', Setting::where('company_id', $company->id)->where('key', VoucherOptions::APPROVAL_THRESHOLD_KEY)->value('value'));
        $this->assertEqualsWithDelta(100.0, VoucherOptions::approvalThreshold($company->id), 0.001);

        $account = $this->accountByCode($company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => 'Auto-approved via saved threshold',
        ])->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status, 'A voucher at/under the just-saved threshold must auto-approve.');
    }

    /** Clearing the field back to blank restores "always require manual approval" -- proves the
     * form round-trips null correctly, not just a truthy number. */
    public function test_clearing_voucher_approval_threshold_restores_manual_approval_requirement(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '500', 'type' => 'string']);

        $payload = $this->baseAccountingSettingsPayload();
        $payload['voucher_approval_threshold'] = null;
        $payload['pv_allow_overdraft'] = false;

        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), $payload)->assertOk();

        $this->assertNull(VoucherOptions::approvalThreshold($company->id));

        $account = $this->accountByCode($company->id, '2110');
        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'type' => 'account', 'account_id' => $account->id, 'amount' => 10, 'remarks_create' => 'Manual now',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $invoiceReceipt->status);
    }

    /** Saving pv_allow_overdraft=true via the settings form (not a hand-seeded row) lets a PV take
     * the bank leaf negative; saving it back to false refuses the same payment. */
    public function test_saving_pv_allow_overdraft_via_settings_form_controls_the_next_pv_post(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->accountByCode($company->id, '1201'); // zero balance -- no fundBank() call
        $target = $this->accountByCode($company->id, '5222');

        $onPayload = $this->baseAccountingSettingsPayload();
        $onPayload['pv_allow_overdraft'] = true;
        // Threshold must be set too -- otherwise the PV auto-approve gate in
        // BankPaymentController::store() never fires (threshold null means "always require a
        // manual approve() step" regardless of the overdraft setting), and this test would only
        // be proving the row stays pending, not the overdraft check itself.
        $onPayload['voucher_approval_threshold'] = 1000;
        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), $onPayload)->assertOk();
        $this->assertTrue(VoucherOptions::pvAllowOverdraft($company->id));

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-OD-1', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'Allowed overdraft', 'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 40]],
        ])->assertRedirect(route('bank-payments.index'));

        $posted = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_APPROVED, $posted->status, 'pv_allow_overdraft=true (saved via the settings form) must let this payment post despite a zero balance.');

        $offPayload = $this->baseAccountingSettingsPayload();
        $offPayload['pv_allow_overdraft'] = false;
        $offPayload['voucher_approval_threshold'] = 1000;
        $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), $offPayload)->assertOk();
        $this->assertFalse(VoucherOptions::pvAllowOverdraft($company->id));

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-OD-2', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'Refused overdraft', 'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 40]],
        ]);

        $refused = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_PENDING, $refused->status, 'pv_allow_overdraft=false (saved via the settings form) must refuse auto-approval of an overdrawing payment.');
    }

    private function baseAccountingSettingsPayload(): array
    {
        return [
            'invoice_overpay_cancel_policy' => 'credit',
            'unclaimed_writeback_months' => 12,
            'commissionable_fee_types' => [],
            'refund_send_on_post' => true,
            'agent_unearn_notice' => true,
            'fee_schedule' => [],
            'posting_basis' => [],
            'bearer' => [],
        ];
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Criterion 2 -- posting an RV from the screen with a split allocation; PV overdraft refused
    // inside the same transaction as the post
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_create_screen_renders_allocation_editor_and_instrument_fields(): void
    {
        [$company, , , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $response = $this->actingAs($admin)->get(route('receipt-voucher.create'));

        $response->assertOk();
        $response->assertSee('Apply to invoices');
        $response->assertSee('allocations[', false);
        $response->assertSee('cheque_no', false);
        $response->assertSee('cheque_image', false);
        $response->assertSee('bank_account_id', false);
    }

    public function test_posting_rv_from_the_screen_with_a_split_allocation_yields_the_allocation_lines_and_remainder(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);

        $invoiceOne = $this->makeUnpaidInvoice($client, $agent, 60.000);
        $invoiceTwo = $this->makeUnpaidInvoice($client, $agent, 40.000);
        $cashInHand = $this->accountByCode($company->id, '1120');
        $clientAdvance = $this->accountByCode($company->id, '2632');

        // Exactly the shape the create.blade.php allocation-lines editor submits: two
        // `allocations[i][invoice_id]`/`allocations[i][amount]` pairs plus a positive remainder.
        $response = $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'invoice',
            'client_id' => $client->id,
            'amount' => 110,
            'allocations' => [
                ['invoice_id' => $invoiceOne->id, 'amount' => 60],
                ['invoice_id' => $invoiceTwo->id, 'amount' => 40],
            ],
            'remarks_create' => 'Split allocation from the create screen',
        ]);

        $response->assertRedirect(route('receipt-voucher.index'));

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status);
        $this->assertEqualsWithDelta(10.0, (float) $invoiceReceipt->remainder_amount, 0.001);
        $this->assertSame('credit', $invoiceReceipt->remainder_policy);

        $lines = JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->get();
        $this->assertCount(4, $lines, 'debit instrument + 2 invoice allocation credits + remainder credit');

        $this->assertEqualsWithDelta(110.0, (float) $lines->firstWhere('account_id', $cashInHand->id)?->debit, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $lines->firstWhere('account_id', $clientAdvance->id)?->credit, 0.001);

        $invoiceOne->refresh();
        $invoiceTwo->refresh();
        $this->assertSame('paid', $invoiceOne->status);
        $this->assertSame('paid', $invoiceTwo->status);
    }

    public function test_pv_create_screen_renders_batch_line_editor_and_overdraft_note(): void
    {
        [$company, , , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $response = $this->actingAs($admin)->get(route('bank-payments.create'));

        $response->assertOk();
        $response->assertSee('Payment lines');
        $response->assertSee('pay_from_account', false);
        $response->assertSee('cheque_image', false);
        $response->assertSee('bank_charge_amount', false);
    }

    public function test_pv_from_the_screen_with_overdraft_disallowed_is_refused_against_insufficient_balance(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);
        // pv_allow_overdraft defaults false; bank leaf starts at a true zero balance.
        $bank = $this->accountByCode($company->id, '1201');
        $target = $this->accountByCode($company->id, '5222');

        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);

        $before = JournalEntry::where('account_id', $bank->id)->count();

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-INSUFFICIENT', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'Should be refused', 'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 999]],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_PENDING, $bankPayment->status, 'Auto-approval must be refused, not silently skipped -- the row stays pending.');
        $this->assertNull($bankPayment->transaction_id);
        // No journal line was ever written for the refused attempt -- proves the overdraft check
        // ran INSIDE the same transaction as the post attempt (no partial write leaked).
        $this->assertSame($before, JournalEntry::where('account_id', $bank->id)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Cheque image upload (W5.U addition)
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_rv_store_persists_an_uploaded_cheque_image_and_edit_screen_links_to_it(): void
    {
        // Security fix regression guard: the cheque image now lands on the PRIVATE `local` disk,
        // never `public` -- see ChequeImageStore's own docblock.
        Storage::fake('local');
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');
        $file = UploadedFile::fake()->image('cheque.jpg', 200, 100)->size(100);

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'cheque_no' => 'CHQ-IMG-1',
            'cheque_image' => $file,
            'remarks_create' => 'With cheque image',
        ])->assertRedirect();

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($invoiceReceipt->cheque_image_path);
        Storage::disk('local')->assertExists($invoiceReceipt->cheque_image_path);
        $this->assertStringStartsWith('cheques/'.$company->id.'/', $invoiceReceipt->cheque_image_path);
        $this->assertStringEndsWith('.jpg', $invoiceReceipt->cheque_image_path, 'Stored extension must come from the sniffed MIME type, not the client filename.');

        $this->actingAs($admin)->get(route('receipt-voucher.edit', $invoiceReceipt->id))
            ->assertOk()
            ->assertSee('Cheque image on file')
            ->assertSee(route('receipt-voucher.cheque-image', $invoiceReceipt->id), false);
    }

    public function test_pv_update_without_a_new_file_keeps_the_existing_cheque_image_path(): void
    {
        // Security fix regression guard: see the identical note on the RV test above.
        Storage::fake('local');
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');
        $file = UploadedFile::fake()->create('cheque.pdf', 50, 'application/pdf');

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-IMG', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'With cheque image',
            'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 40, 'cheque_no' => 'CHQ-PV-1', 'cheque_image' => $file]],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($bankPayment->cheque_image_path);
        $originalPath = $bankPayment->cheque_image_path;

        $this->actingAs($admin)->put(route('bank-payments.update', $bankPayment->id), [
            'docdate' => now()->toDateString(), 'bankpaymenttype' => 'Payment', 'type_selector' => 'account',
            'pay_from_account' => $bank->id, 'account_id' => $target->id, 'amount' => 45,
            'remarks_create' => 'Amount corrected, no new file',
        ])->assertRedirect();

        $bankPayment->refresh();
        $this->assertSame($originalPath, $bankPayment->cheque_image_path, 'An update with no new file must not erase the previously uploaded cheque image.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Criterion 3 -- clear/bounce shapes reachable from the screen; reconciled/locked vouchers
    // cannot be edited or reversed through the screen (controller-level refusal, not just a
    // hidden button)
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_edit_screen_shows_clear_action_for_an_outstanding_cheque_and_bounce_action_once_cleared(): void
    {
        [$company, $branch, , $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);

        $account = $this->accountByCode($company->id, '2110');
        $bank = $this->accountByCode($company->id, '1201');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'type' => 'account', 'client_id' => $client->id, 'account_id' => $account->id, 'amount' => 60,
            'cheque_no' => 'CHQ-EDIT-1', 'cheque_date' => now()->addDays(5)->toDateString(),
            'remarks_create' => 'PDC for edit-screen test',
        ]);
        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        $editBeforeClear = $this->actingAs($admin)->get(route('receipt-voucher.edit', $invoiceReceipt->id));
        $editBeforeClear->assertOk()->assertSee('Clear cheque')->assertDontSee('Bounce cheque');

        $this->actingAs($admin)->post(route('receipt-voucher.clear', $invoiceReceipt->id), ['bank_account_id' => $bank->id]);

        $editAfterClear = $this->actingAs($admin)->get(route('receipt-voucher.edit', $invoiceReceipt->id));
        $editAfterClear->assertOk()->assertSee('Bounce cheque')->assertDontSee('Clear cheque');
    }

    public function test_edit_screen_greys_out_edit_and_reverse_controls_when_a_line_is_reconciled(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);
        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);

        $account = $this->accountByCode($company->id, '2110');
        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'type' => 'account', 'account_id' => $account->id, 'amount' => 50, 'remarks_create' => 'To be reconciled',
        ]);
        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        JournalEntry::where('transaction_id', $invoiceReceipt->transaction_id)->limit(1)->update(['reconciled' => 1]);

        $response = $this->actingAs($admin)->get(route('receipt-voucher.edit', $invoiceReceipt->id));
        $response->assertOk();
        $response->assertSee('Reconciled');
        $response->assertDontSee('Save (reverse and repost)');
        $response->assertDontSee('name="Reverse"', false);

        // Controller-level refusal, not merely a hidden button: a direct POST still refuses.
        $this->actingAs($admin)->delete(route('receipt-voucher.destroy', $invoiceReceipt->id))->assertRedirect();
        $invoiceReceipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $invoiceReceipt->status, 'A reconciled voucher must not actually reverse even via a direct request.');

        $this->actingAs($admin)->put(route('receipt-voucher.update', $invoiceReceipt->id), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'type' => 'account', 'account_id' => $account->id, 'amount' => 999, 'remarks_create' => 'Should not apply',
        ])->assertRedirect();
        $invoiceReceipt->refresh();
        $this->assertEqualsWithDelta(50.0, (float) $invoiceReceipt->amount, 0.001, 'A reconciled voucher must not actually update even via a direct request.');
    }

    public function test_pv_edit_screen_greys_out_controls_when_locked(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);
        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);

        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-LOCK', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'To be locked', 'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 40]],
        ]);
        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();

        $transaction = Transaction::withoutGlobalScopes()->find($bankPayment->transaction_id);
        $transaction->is_locked = true;
        $transaction->locked_by = $admin->id;
        $transaction->locked_at = now();
        $transaction->save();

        $response = $this->actingAs($admin)->get(route('bank-payments.edit', $bankPayment->id));
        $response->assertOk();
        $response->assertSee('Locked');
        $response->assertDontSee('Save (reverse and repost)');

        // The controller-level refusal here is a hard 403 (App\Http\Traits\Lockable::canModify()
        // -> Gate::authorize('manageLocks', ...), which throws on denial rather than returning
        // false -- the SAME documented convention InvoiceControllerW40Test/
        // InvoiceControllerW3eTest already rely on for Invoice::canModify()) -- an even stronger
        // proof of "the controller itself refuses" than a friendly redirect would be.
        $this->actingAs($admin)->delete(route('bank-payments.destroy', $bankPayment->id))->assertForbidden();
        $bankPayment->refresh();
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status, 'A locked voucher must not actually reverse even via a direct request.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Pending (never-posted) drafts render on the edit screen too -- no transaction/journal lines
    // exist yet, so this exercises every optional()/null-guard the edit views carry for that state.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_rv_edit_screen_renders_a_still_pending_draft_with_an_approve_action(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);
        // No threshold Setting row -> approvalThreshold() is null -> store() leaves the row pending.

        $account = $this->accountByCode($company->id, '2110');
        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'type' => 'account', 'account_id' => $account->id, 'amount' => 50, 'remarks_create' => 'Still pending',
        ]);
        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $invoiceReceipt->status);

        $response = $this->actingAs($admin)->get(route('receipt-voucher.edit', $invoiceReceipt->id));
        $response->assertOk();
        $response->assertSee('Pending');
        $response->assertSee('Approve &amp; post', false);
        $response->assertSee('Save changes');
        $response->assertDontSee('Clear cheque');
    }

    public function test_pv_edit_screen_renders_a_still_pending_draft_with_an_approve_action(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');
        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-PENDING', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'Still pending', 'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 40]],
        ]);
        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_PENDING, $bankPayment->status);

        $response = $this->actingAs($admin)->get(route('bank-payments.edit', $bankPayment->id));
        $response->assertOk();
        $response->assertSee('Pending');
        $response->assertSee('Approve &amp; post', false);
        $response->assertSee('Save changes');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Criterion 4 -- unauthorized users hit 403 on approve/reverse
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_rv_destroy_is_403_for_unauthorized_role(): void
    {
        [$company, $branch, $agent, , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');
        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'type' => 'account', 'account_id' => $account->id, 'amount' => 50, 'remarks_create' => 'For 403 destroy test',
        ]);
        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        $agentRoleUser = User::factory()->create(['role_id' => Role::AGENT]);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentRoleUser->id, 'type_id' => $agent->type_id]);

        $this->actingAs($agentRoleUser)->delete(route('receipt-voucher.destroy', $invoiceReceipt->id))->assertForbidden();
    }

    public function test_pv_approve_and_destroy_are_403_for_unauthorized_role(): void
    {
        [$company, $branch, $agent, , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-403', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'For 403 test', 'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 900]],
        ]);
        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_PENDING, $bankPayment->status);

        $agentRoleUser = User::factory()->create(['role_id' => Role::AGENT]);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentRoleUser->id, 'type_id' => $agent->type_id]);

        $this->actingAs($agentRoleUser)->post(route('bank-payments.approve', $bankPayment->id))->assertForbidden();
        $this->actingAs($agentRoleUser)->delete(route('bank-payments.destroy', $bankPayment->id))->assertForbidden();
    }
}
