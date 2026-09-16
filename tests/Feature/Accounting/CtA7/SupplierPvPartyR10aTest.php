<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\BankPayment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\VoucherOptions;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7-2 — finding **R3-10a** (VERIFY-CT-A56-R3 §3.3), verbatim:
 *
 * > "`BankPaymentController::buildVoucherDraft()` sets `partyAccountRef: $bp->sub_type === 'BONUS'
 * >  ? $bp->agent_id : null`. A **supplier** payment voucher therefore posts its payable debit with
 * >  `type_reference_id = NULL`. Filter the creditors or unpaid-AP screen by that supplier and the
 * >  **payments disappear while the invoices remain** — the balance shown is gross of everything
 * >  ever paid."
 *
 * `bank_payments` carries no supplier column: a PV names an ACCOUNT (`target_account_id`), never a
 * party. The party is therefore derived from the target leaf, through exactly the two columns that
 * leaf is minted with — `accounts.supplier_id` (the legacy per-supplier leaves, and the column
 * `BankPaymentController::resolveSupplierBankDetail()` already trusts on this very screen to decide
 * "is this a supplier target?") and `accounts.supplier_company_id` -> `supplier_companies.
 * supplier_id` (what `SupplierActivationService::activate()` actually populates). Both paths are
 * exercised below, on the engine path and on the engine-OFF seam path, because
 * `writeLegacyTransaction()` writes `type_reference_id` from the SAME `LineDraft::$partyAccountRef`
 * — one derivation, two writers, and this file proves the OFF path did not get left behind.
 *
 * The source-level ratchet that stops the next payable feeder repeating this lives in
 * {@see \Tests\Feature\Accounting\ArchitectureTest::test_no_ap_side_engine_line_is_written_with_a_null_party()}.
 * This file is the runtime half: it proves the value that actually reaches the column is the
 * supplier's id, which no source scan can assert.
 */
class SupplierPvPartyR10aTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    /** @return array{0: Company, 1: Branch, 2: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '10000',
            'type' => 'string',
        ]);

        return [$company, $branch, $admin];
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
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

    /** Copied verbatim in intent from BankPaymentControllerW5PTest: a fresh CoaSeeder company
     * starts every leaf at a true zero, so an unfunded bank trips the overdraft refusal before any
     * assertion in this file could run. */
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

    private function payTo(User $admin, Company $company, Branch $branch, Account $bank, Account $target, float $amount): BankPayment
    {
        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Supplier payment',
            'items' => [
                ['type_selector' => 'account', 'account_id' => $target->id, 'credit' => $amount],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();

        $this->assertNotNull($bankPayment);
        $this->assertSame('SUPPLIER', $bankPayment->sub_type, 'a liability target must classify as a SUPPLIER voucher for this test to mean anything');
        $this->assertSame(BankPayment::STATUS_APPROVED, $bankPayment->status);
        $this->assertNotNull($bankPayment->transaction_id, 'the voucher must have posted');

        return $bankPayment;
    }

    private function payableLineOf(BankPayment $bankPayment, Account $target): JournalEntry
    {
        $line = JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $bankPayment->transaction_id)
            ->where('account_id', $target->id)
            ->first();

        $this->assertNotNull($line, 'the voucher must carry a debit leg on its target account');

        return $line;
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Path 1 — `accounts.supplier_id`, the legacy per-supplier leaf shape.
     */
    public function test_a_supplier_payment_voucher_stamps_the_supplier_from_accounts_supplier_id(): void
    {
        [$company, $branch, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);

        $supplier = Supplier::factory()->create();
        $target = $this->accountByCode($company->id, '2120'); // Suppliers (Flights)
        $target->supplier_id = $supplier->id;
        $target->save();

        $bankPayment = $this->payTo($admin, $company, $branch, $bank, $target, 60.0);
        $line = $this->payableLineOf($bankPayment, $target);

        $this->assertSame(
            (int) $supplier->id,
            (int) $line->type_reference_id,
            'R3-10a: a supplier PV must stamp the supplier on its payable leg — a NULL here is what '
            .'made payments vanish from the supplier-filtered creditors and unpaid-AP screens.'
        );
        $this->assertEqualsWithDelta(60.0, (float) $line->debit, 0.0005);
    }

    /**
     * Path 2 — `accounts.supplier_company_id`, which is what `SupplierActivationService::activate()`
     * actually populates when it mints a supplier's payable leaf on this chart. Missing this path
     * would have left every ENGINE-era supplier leaf untagged while the legacy ones worked.
     */
    public function test_a_supplier_payment_voucher_stamps_the_supplier_through_the_supplier_company_pivot(): void
    {
        [$company, $branch, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);

        $supplier = Supplier::factory()->create();
        SupplierCompany::create([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        // Re-read by natural key, NOT `->id` off the create() return: SupplierCompany extends
        // Pivot, which declares `public $incrementing = false`, so a freshly-created row's `id` is
        // NULL on the returned instance even though the table has a real auto-increment key. That
        // is not a test artefact — it is the production defect CT-A7-2 also fixes in
        // SupplierActivationService, which stored exactly that NULL as `accounts.supplier_company_id`
        // on every FIRST activation of a supplier. See that method's own comment.
        $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $this->assertNotNull($supplierCompany->id);

        $target = $this->accountByCode($company->id, '2130'); // Suppliers (Hotels)
        $target->supplier_id = null;
        $target->supplier_company_id = $supplierCompany->id;
        $target->save();

        $bankPayment = $this->payTo($admin, $company, $branch, $bank, $target, 45.0);
        $line = $this->payableLineOf($bankPayment, $target);

        $this->assertSame((int) $supplier->id, (int) $line->type_reference_id);
    }

    /**
     * The ENGINE-OFF seam writer takes the same derivation: `writeLegacyTransaction()` writes
     * `type_reference_id` straight from `LineDraft::$partyAccountRef`, so OFF/ON parity is a
     * property of the fix rather than a second code path to keep in sync — asserted, not assumed.
     */
    public function test_the_engine_off_seam_writer_stamps_the_same_supplier(): void
    {
        [$company, $branch, $admin] = $this->makeFixture();
        // Engine deliberately NOT enabled: this is the legacy writer's own path.
        (new SystemAccountsSeeder)->run();
        $bank = $this->fundBank($company, $branch, '1201', 1000);

        $supplier = Supplier::factory()->create();
        $target = $this->accountByCode($company->id, '2120');
        $target->supplier_id = $supplier->id;
        $target->save();

        $bankPayment = $this->payTo($admin, $company, $branch, $bank, $target, 25.0);
        $line = $this->payableLineOf($bankPayment, $target);

        $this->assertSame(
            (int) $supplier->id,
            (int) $line->type_reference_id,
            'the engine-OFF seam writer must stamp the party too — companies 2 and 3 on the dev site '
            .'are engine-OFF, and their supplier filter is the same screen.'
        );
    }

    /**
     * The chart side of the same fix, and the reason CT-A7-2 is not purely a controller change:
     * `SupplierActivationService::activate()` — the one thing that mints a supplier's payable leaf
     * on this chart — never stamped `accounts.supplier_id` at all, and the `supplier_company_id` it
     * did stamp was NULL on every first activation (Pivot's `$incrementing = false`, see that
     * method's comment). A leaf with neither column has no link back to its party, so the PV party
     * derivation above would have had nothing to read on exactly the accounts it exists for.
     */
    public function test_supplier_activation_links_the_minted_payable_leaf_back_to_its_supplier(): void
    {
        $supplier = Supplier::factory()->create(['has_flight' => true, 'has_hotel' => false]);

        $unique = uniqid();
        $company = app(\App\Services\CompanyProvisioner::class)->provision(
            \App\Support\CompanyRegistrationData::fromArray([
                'company_name' => 'CtA7 PV Party Co '.$unique,
                'company_code' => 'CTA7-'.$unique,
                'country_id' => \App\Models\Country::factory()->create()->id,
                'company_email' => "owner-{$unique}@example.test",
                'owner_name' => 'Test Owner',
                'owner_email' => "owner-{$unique}@example.test",
                'owner_password' => 'password12345',
                'currency' => 'KWD',
                'supplier_ids' => [$supplier->id],
            ])
        );

        $this->trackCompanyForInvariants($company->id);

        $payableLeaf = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Suppliers (Flights)')
            ->firstOrFail()
            ->children()
            ->where('name', $supplier->name)
            ->firstOrFail();

        $this->assertSame(
            (int) $supplier->id,
            (int) $payableLeaf->supplier_id,
            'the minted payable leaf must name its supplier — without it a payment voucher against '
            .'this leaf has no party to stamp (R3-10a).'
        );
        $this->assertNotNull(
            $payableLeaf->supplier_company_id,
            'and the supplier_companies pivot id must be a real key, not the NULL a Pivot create() returns.'
        );
    }

    /**
     * The one shape that must NOT change: a BONUS voucher still carries the AGENT id, exactly as
     * before CT-A7-2. The fix widened a null into a supplier, it did not repurpose the column.
     */
    public function test_a_bonus_voucher_still_carries_the_agent_id(): void
    {
        [$company, $branch, $admin] = $this->makeFixture();
        $this->enableEngine($company);
        $bank = $this->fundBank($company, $branch, '1201', 1000);

        $agent = Agent::withoutGlobalScopes()->where('branch_id', $branch->id)->firstOrFail();
        $target = $this->accountByCode($company->id, '5130'); // Commissions Expense (Agents)

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'remarks_create' => 'Bonus payment',
            'items' => [
                ['type_selector' => 'bonus', 'account_id' => $target->id, 'agent_id' => $agent->id, 'credit' => 30],
            ],
        ])->assertRedirect();

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();

        $this->assertSame('BONUS', $bankPayment->sub_type);
        $this->assertSame(
            (int) $agent->id,
            (int) $this->payableLineOf($bankPayment, Account::withoutGlobalScopes()->findOrFail($bankPayment->target_account_id))->type_reference_id,
            'BONUS keeps the agent id — CT-A7-2 must not have repurposed the party column.'
        );
    }
}
