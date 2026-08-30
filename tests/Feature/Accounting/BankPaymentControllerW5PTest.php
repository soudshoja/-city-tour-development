<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\BankPayment;
use App\Models\BonusAgent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\VoucherOptions;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w5p-controller. W5.P (w5-brief.md §W5.P) — BankPaymentController through
 * {@see \App\Services\Accounting\PostingSeam}: store/approve/update/destroy/clear, overdraft
 * pre-check, cheque-issued-not-cleared instrument, bonus PV, PaymentByDate fast path, policy
 * enforcement, OFF-path parity.
 */
class BankPaymentControllerW5PTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: User} */
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

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $agent, $admin];
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

    /** Seeds the bank leaf (1201) with an opening credit so a direct-bank payment has something to
     * pay from -- a fresh CoaSeeder company otherwise starts every leaf at a true zero balance,
     * which would make EVERY payment trip the overdraft refusal regardless of what this test is
     * actually asserting. */
    private function fundBank(Company $company, Branch $branch, string $bankCode, float $amount): Account
    {
        $bank = $this->accountByCode($company->id, $bankCode);
        $incomeSuspense = $this->accountByCode($company->id, '4133'); // Service Fee Income -- any credit-normal leaf works as the funding counter-leg.

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
    // store() lifecycle
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_creates_pending_draft_with_no_threshold_and_does_not_post(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        $response = $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-1',
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Test payment',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $response->assertRedirect(route('bank-payments.index'));

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($bankPayment);
        $this->assertSame(BankPayment::STATUS_PENDING, $bankPayment->status);
        $this->assertNull($bankPayment->transaction_id);
    }

    public function test_store_auto_approves_and_posts_balanced_document_under_threshold(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '100', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-2',
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Auto-approved payment',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ])->assertRedirect(route('bank-payments.index'));

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status);
        $this->assertSame('ACCOUNT', $bankPayment->sub_type);
        $this->assertNotNull($bankPayment->transaction_id);

        $lines = JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get();
        $this->assertCount(2, $lines);

        $debit = $lines->firstWhere('account_id', $target->id);
        $credit = $lines->firstWhere('account_id', $bank->id);
        $this->assertNotNull($debit);
        $this->assertNotNull($credit);
        $this->assertEqualsWithDelta(50.0, (float) $debit->debit, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $credit->credit, 0.001);
    }

    public function test_store_classifies_liability_target_as_supplier_sub_type(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '2110'); // Creditors -- under Liabilities.

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Supplier payment',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame('SUPPLIER', $bankPayment->sub_type);
    }

    public function test_store_with_manual_bank_charge_posts_a_balanced_four_line_document(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');
        $bankChargesExpense = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Payment with bank charge',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50, 'bank_charge_amount' => 2],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $lines = JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get();
        $this->assertCount(4, $lines);

        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $this->assertEqualsWithDelta(52.0, $totalDebit, 0.001);
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Bonus PV
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_bonus_payment_creates_bonus_agent_side_record_and_posts_bonus_sub_type(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $bonusAccount = $this->accountByCode($company->id, '5160'); // Agent Salaries.

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Bonus payment',
            'items' => [
                ['type_selector' => 'bonus', 'account_id' => $bonusAccount->id, 'agent_id' => $agent->id, 'credit' => 30],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame('BONUS', $bankPayment->sub_type);
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status);

        $bonusAgent = BonusAgent::where('transaction_id', $bankPayment->transaction_id)->first();
        $this->assertNotNull($bonusAgent);
        $this->assertSame($agent->id, $bonusAgent->agent_id);
        $this->assertEqualsWithDelta(30.0, (float) $bonusAgent->amount, 0.001);
    }

    public function test_bonus_payment_without_agent_is_rejected(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $bonusAccount = $this->accountByCode($company->id, '5160');

        $before = BankPayment::count();

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Bonus payment, no agent',
            'items' => [
                ['type_selector' => 'bonus', 'account_id' => $bonusAccount->id, 'credit' => 30],
            ],
        ])->assertRedirect();

        $this->assertSame($before, BankPayment::count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Cheque issued not cleared -> clear()
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_cheque_issued_posts_to_cheques_issued_not_cleared_then_clear_moves_it_to_bank(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '2110');
        $chequesIssued = $this->accountByCode($company->id, '2215');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Cheque payment',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 60, 'cheque_no' => 'CHQ-900', 'cheque_date' => now()->addDays(5)->toDateString()],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $lines = JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get();
        $credit = $lines->firstWhere('account_id', $chequesIssued->id);
        $this->assertNotNull($credit, 'Cheque-issued PV should credit CHEQUES_ISSUED_NOT_CLEARED (2215), not the bank leaf directly.');
        $this->assertEqualsWithDelta(60.0, (float) $credit->credit, 0.001);
        $this->assertNull($lines->firstWhere('account_id', $bank->id), 'The real bank leaf must NOT move until the cheque is cleared.');

        $this->actingAs($admin)->post(route('bank-payments.clear', $bankPayment->id), [
            'bank_account_id' => $bank->id,
        ])->assertRedirect();

        $bankPayment->refresh();
        $this->assertNotNull($bankPayment->cheque_clearance_date);

        $clearanceTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'pv-clear:'.$bankPayment->id)->first();
        $this->assertNotNull($clearanceTransaction);

        $clearLines = JournalEntry::where('transaction_id', $clearanceTransaction->id)->get();
        $this->assertEqualsWithDelta(60.0, (float) $clearLines->firstWhere('account_id', $bank->id)?->credit, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $clearLines->firstWhere('account_id', $chequesIssued->id)?->debit, 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // PaymentByDate fast path
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_payment_by_date_sets_reconciled_two_on_its_own_line_via_engine_flag(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '2110');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'PaymentByDate',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Payment by date',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 40],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame('BY_DATE', $bankPayment->sub_type);

        $lines = JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get();
        foreach ($lines as $line) {
            $this->assertSame(2, (int) $line->reconciled, 'PaymentByDate fast-path lines must carry reconciled=2, set via LineDraft::$reconciled, not a raw post-insert column write.');
        }
    }

    public function test_payment_by_date_marks_selected_prior_journal_entries_reconciled(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '2110');

        // A pre-existing, unreconciled liability journal entry this fast path will mark reconciled.
        $priorTxn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => 40, 'description' => 'Prior liability',
            'reference_type' => 'Invoice', 'reference_number' => 'PRIOR-'.uniqid(),
            'name' => 'Prior liability', 'transaction_date' => now()->subDays(3),
            'doc_type' => 'INV', 'doc_year' => (int) now()->format('Y'), 'posting_status' => 'posted',
            'total_debit' => 40, 'total_credit' => 40, 'idempotency_key' => 'prior:'.uniqid(),
        ]);
        $priorLine = JournalEntry::create([
            'transaction_id' => $priorTxn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $target->id, 'transaction_date' => now()->subDays(3), 'description' => 'Prior liability',
            'debit' => 0, 'credit' => 40, 'name' => $target->name, 'type' => 'payable', 'currency' => 'KWD',
            'exchange_rate' => 1, 'amount' => 40, 'voucher_number' => 'PRIOR', 'reconciled' => 0,
        ]);
        // Balancing leg (the invariant checked in tearDown() requires every transaction to be
        // balanced) -- the account chosen doesn't matter to this test, only that the prior
        // transaction is a real double-entry pair.
        $priorExpense = $this->accountByCode($company->id, '5222');
        JournalEntry::create([
            'transaction_id' => $priorTxn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $priorExpense->id, 'transaction_date' => now()->subDays(3), 'description' => 'Prior liability',
            'debit' => 40, 'credit' => 0, 'name' => $priorExpense->name, 'type' => 'expense', 'currency' => 'KWD',
            'exchange_rate' => 1, 'amount' => 40, 'voucher_number' => 'PRIOR', 'reconciled' => 0,
        ]);

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'PaymentByDate',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Payment by date, reconciling prior entry',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 40, 'transaction_id' => (string) $priorLine->id],
            ],
        ])->assertRedirect();

        $priorLine->refresh();
        $this->assertSame(1, (int) $priorLine->reconciled);
        $this->assertNotNull($priorLine->reconciled_ref_id);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Overdraft pre-check
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_store_refuses_when_bank_balance_insufficient_and_overdraft_not_allowed(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 30); // Only 30 available.
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $before = BankPayment::where('status', BankPayment::STATUS_APPROVED)->count();

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Overdraft attempt',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 100],
            ],
        ])->assertRedirect();

        $this->assertSame($before, BankPayment::where('status', BankPayment::STATUS_APPROVED)->count(), 'No voucher should have posted (and none of its journal_entries) when the bank balance is insufficient.');

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_PENDING, $bankPayment->status, 'The row itself still exists (validation passed) but auto-approve must have failed and rolled back, leaving it pending.');
        $this->assertNull($bankPayment->transaction_id);
    }

    public function test_store_allows_overdraft_when_company_option_enabled(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 30);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);
        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::PV_ALLOW_OVERDRAFT_KEY,
            'value' => '1', 'type' => 'boolean',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Overdraft allowed',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 100],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status);
    }

    public function test_cheque_issued_payment_does_not_trip_overdraft_check_on_issuance(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 10); // Far less than the cheque amount.
        $target = $this->accountByCode($company->id, '2110');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Cheque payment exceeding bank balance',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 500, 'cheque_no' => 'CHQ-901', 'cheque_date' => now()->addDays(5)->toDateString()],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status, 'A cheque-instrument payment must not be blocked by the real bank balance -- the bank is not actually debited until clearance.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // update()/destroy() reverse+repost / reverse
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_on_pending_row_updates_fields_without_posting(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Original',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();

        $this->actingAs($admin)->put(route('bank-payments.update', $bankPayment->id), [
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'type_selector' => 'account',
            'pay_from_account' => $bank->id,
            'account_id' => $target->id,
            'amount' => 75,
            'remarks_create' => 'Updated',
        ])->assertRedirect();

        $bankPayment->refresh();
        $this->assertSame(BankPayment::STATUS_PENDING, $bankPayment->status);
        $this->assertEqualsWithDelta(75.0, (float) $bankPayment->amount, 0.001);
        $this->assertNull($bankPayment->transaction_id);
    }

    public function test_update_on_posted_row_reverses_and_reposts_a_balanced_replacement(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Original posted',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status);
        $oldTransactionId = $bankPayment->transaction_id;

        $this->actingAs($admin)->put(route('bank-payments.update', $bankPayment->id), [
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'type_selector' => 'account',
            'pay_from_account' => $bank->id,
            'account_id' => $target->id,
            'amount' => 90,
            'remarks_create' => 'Corrected amount',
        ])->assertRedirect();

        $bankPayment->refresh();
        $this->assertNotEquals($oldTransactionId, $bankPayment->transaction_id);

        $oldTransaction = Transaction::withoutGlobalScopes()->find($oldTransactionId);
        $this->assertSame('reversed', $oldTransaction->posting_status);

        $newLines = JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get();
        $totalDebit = (float) $newLines->sum('debit');
        $totalCredit = (float) $newLines->sum('credit');
        $this->assertEqualsWithDelta(90.0, $totalDebit, 0.001);
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001);
    }

    public function test_delete_on_pending_row_hard_deletes(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'To be deleted',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();

        $this->actingAs($admin)->delete(route('bank-payments.destroy', $bankPayment->id))->assertRedirect();

        $this->assertNull(BankPayment::find($bankPayment->id));
    }

    public function test_delete_on_posted_row_reverses(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'To be reversed',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $transactionId = $bankPayment->transaction_id;

        $this->actingAs($admin)->delete(route('bank-payments.destroy', $bankPayment->id))->assertRedirect();

        $bankPayment->refresh();
        $this->assertSame(BankPayment::STATUS_REVERSED, $bankPayment->status);

        $reversal = Transaction::withoutGlobalScopes()->where('reversal_of_transaction_id', $transactionId)->first();
        $this->assertNotNull($reversal);
    }

    public function test_delete_is_blocked_when_a_line_is_reconciled(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Reconciled line',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();

        JournalEntry::where('transaction_id', $bankPayment->transaction_id)->limit(1)->update(['reconciled' => 1]);

        $this->actingAs($admin)->delete(route('bank-payments.destroy', $bankPayment->id))->assertRedirect();

        $bankPayment->refresh();
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // BankPaymentPolicy enforcement
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_approve_is_403_for_unauthorized_role(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'For 403 test',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();

        $agentRoleUser = User::factory()->create(['role_id' => Role::AGENT]);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentRoleUser->id, 'type_id' => $agent->type_id]);

        $this->actingAs($agentRoleUser)
            ->post(route('bank-payments.approve', $bankPayment->id))
            ->assertForbidden();
    }

    public function test_store_is_404_when_accounting_module_disabled(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => 'module.accounting',
            'value' => 'false', 'type' => 'boolean',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Module disabled',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ])->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path: same real accounts, no name-LIKE lookups, still balanced
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_off_path_still_posts_a_balanced_document_to_the_same_accounts(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        // Engine deliberately left OFF (config default) -- but system_accounts must still exist for
        // AccountResolver (BANK_CHARGES_EXPENSE/CHEQUES_ISSUED_NOT_CLEARED resolution), per
        // ReceiptVoucherController's own established convention for its identical OFF-path test.
        (new SystemAccountsSeeder)->run();
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'OFF path payment',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status);

        $lines = JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get();
        $this->assertCount(2, $lines);
        $this->assertNotNull($lines->firstWhere('account_id', $target->id));
        $this->assertNotNull($lines->firstWhere('account_id', $bank->id));

        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001);
    }

    public function test_off_path_update_on_posted_row_marks_the_old_transaction_reversed(): void
    {
        [$company, $branch, $agent, $admin] = $this->makeFixture();
        // Engine deliberately left OFF.
        (new SystemAccountsSeeder)->run();
        $bank = $this->fundBank($company, $branch, '1201', 1000);
        $target = $this->accountByCode($company->id, '5222');

        Setting::create([
            'company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000', 'type' => 'string',
        ]);

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'OFF path, to be updated',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $oldTransactionId = $bankPayment->transaction_id;

        $this->actingAs($admin)->put(route('bank-payments.update', $bankPayment->id), [
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'type_selector' => 'account',
            'pay_from_account' => $bank->id,
            'account_id' => $target->id,
            'amount' => 65,
            'remarks_create' => 'OFF path, updated',
        ])->assertRedirect();

        $bankPayment->refresh();
        $this->assertNotEquals($oldTransactionId, $bankPayment->transaction_id);

        $oldTransaction = Transaction::withoutGlobalScopes()->find($oldTransactionId);
        $this->assertSame('reversed', $oldTransaction->posting_status, 'markTransactionReversed() must actually persist -- posting_status is not mass-assignable.');

        $newLines = JournalEntry::where('transaction_id', $bankPayment->transaction_id)->get();
        $this->assertEqualsWithDelta(65.0, (float) $newLines->sum('debit'), 0.001);
    }
}
