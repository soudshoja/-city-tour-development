<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankPayment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBankDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T14 "Supplier bank details per currency" (accounting-builds PLAN.md §5 T14; L18). The supplier
 * payment voucher's auto-selection: `BankPaymentController::resolveSupplierBankAjax()` (the live
 * lookup create/edit's Alpine.js calls) picks the supplier's DEFAULT bank detail for the
 * PAYMENT CURRENCY -- resolved as the `pay_from_account`'s own `currency` column (L18: "our own
 * bank Account rows carry a currency column") -- shows a warning (never a block, never a silent
 * fallback to another currency's row) when none exists for that currency, and `edit()` resolves
 * the same thing server-side for the voucher as saved.
 */
class BankPaymentSupplierBankSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private Supplier $supplier;

    private Account $supplierAccount;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        $this->admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $this->supplier = Supplier::factory()->create(['name' => 'T14 Voucher Supplier']);

        $this->supplierAccount = Account::factory()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
        ]);

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function bankAccount(string $currency): Account
    {
        return Account::factory()->create(['company_id' => $this->company->id, 'currency' => $currency]);
    }

    private function makeBankDetail(string $currency, array $overrides = []): SupplierBankDetail
    {
        return SupplierBankDetail::create(array_merge([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'currency' => $currency,
            'bank_name' => 'Deutsche Bank',
            'beneficiary_name' => 'T14 Voucher Supplier',
            'iban' => 'DE89370400440532013000',
            'swift_bic' => 'DEUTDEFF',
            'bank_country' => 'DE',
            'is_default' => true,
            'is_active' => true,
        ], $overrides));
    }

    // ---------------------------------------------------------------------------------------
    // Selection follows the payment currency
    // ---------------------------------------------------------------------------------------

    public function test_ajax_resolution_selects_the_default_for_the_pay_from_accounts_currency(): void
    {
        $this->makeBankDetail('EUR');
        $eurBank = $this->bankAccount('EUR');

        $response = $this->actingAs($this->admin)->getJson(route('bank-payments.resolve-supplier-bank', [
            'account_id' => $this->supplierAccount->id,
            'pay_from_account_id' => $eurBank->id,
        ]));

        $response->assertOk();
        $response->assertJson([
            'is_supplier_target' => true,
            'currency' => 'EUR',
            'found' => true,
        ]);
        $this->assertSame('DEUTDEFF', $response->json('bank_detail.swift_bic'));
    }

    public function test_it_never_substitutes_another_currencys_details(): void
    {
        $this->makeBankDetail('EUR');
        $usdBank = $this->bankAccount('USD');

        $response = $this->actingAs($this->admin)->getJson(route('bank-payments.resolve-supplier-bank', [
            'account_id' => $this->supplierAccount->id,
            'pay_from_account_id' => $usdBank->id,
        ]));

        $response->assertOk();
        $response->assertJson([
            'is_supplier_target' => true,
            'currency' => 'USD',
            'found' => false,
        ]);
        $this->assertNull($response->json('bank_detail'));
    }

    public function test_missing_currency_shows_a_warning_flag_never_a_block(): void
    {
        // No bank details at all for this supplier.
        $bank = $this->bankAccount('GBP');

        $response = $this->actingAs($this->admin)->getJson(route('bank-payments.resolve-supplier-bank', [
            'account_id' => $this->supplierAccount->id,
            'pay_from_account_id' => $bank->id,
        ]));

        $response->assertOk();
        $response->assertJson(['is_supplier_target' => true, 'found' => false]);
        // The endpoint itself never refuses/blocks -- it is advisory (200 OK either way).
    }

    public function test_a_non_supplier_target_account_is_reported_as_such(): void
    {
        $plainAccount = Account::factory()->create(['company_id' => $this->company->id, 'supplier_id' => null]);
        $bank = $this->bankAccount('EUR');

        $response = $this->actingAs($this->admin)->getJson(route('bank-payments.resolve-supplier-bank', [
            'account_id' => $plainAccount->id,
            'pay_from_account_id' => $bank->id,
        ]));

        $response->assertOk();
        $response->assertJson(['is_supplier_target' => false]);
    }

    public function test_a_soft_deleted_default_is_never_selected(): void
    {
        $detail = $this->makeBankDetail('EUR');
        $detail->delete();
        $eurBank = $this->bankAccount('EUR');

        $response = $this->actingAs($this->admin)->getJson(route('bank-payments.resolve-supplier-bank', [
            'account_id' => $this->supplierAccount->id,
            'pay_from_account_id' => $eurBank->id,
        ]));

        $response->assertJson(['is_supplier_target' => true, 'found' => false]);
    }

    public function test_null_account_currency_falls_back_to_the_company_base_currency(): void
    {
        $this->makeBankDetail('KWD');
        $bankWithNoCurrency = Account::factory()->create(['company_id' => $this->company->id, 'currency' => null]);

        $response = $this->actingAs($this->admin)->getJson(route('bank-payments.resolve-supplier-bank', [
            'account_id' => $this->supplierAccount->id,
            'pay_from_account_id' => $bankWithNoCurrency->id,
        ]));

        $response->assertJson(['is_supplier_target' => true, 'currency' => 'KWD', 'found' => true]);
    }

    // ---------------------------------------------------------------------------------------
    // edit() -- server-side resolution for the voucher/remittance view of a SAVED voucher
    // ---------------------------------------------------------------------------------------

    public function test_edit_page_renders_the_resolved_default_bank_detail_for_the_voucher_as_saved(): void
    {
        $this->makeBankDetail('EUR', ['bank_name' => 'Deutsche Bank', 'beneficiary_name' => 'T14 Voucher Supplier']);
        $eurBank = $this->bankAccount('EUR');
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->admin->id]);

        $bankPayment = BankPayment::create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'sub_type' => 'SUPPLIER',
            'pay_from_account_id' => $eurBank->id,
            'target_account_id' => $this->supplierAccount->id,
            'amount' => 100,
            'status' => BankPayment::STATUS_PENDING,
            'voucher_number' => 'PV-TEST-1',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('bank-payments.edit', $bankPayment->id));

        $response->assertOk();
        $response->assertSee('Deutsche Bank');
        $response->assertSee('Remittance details', false);
    }

    public function test_edit_page_shows_the_missing_currency_warning_when_no_default_exists(): void
    {
        $gbpBank = $this->bankAccount('GBP');
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->admin->id]);

        $bankPayment = BankPayment::create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->toDateString(),
            'sub_type' => 'SUPPLIER',
            'pay_from_account_id' => $gbpBank->id,
            'target_account_id' => $this->supplierAccount->id,
            'amount' => 100,
            'status' => BankPayment::STATUS_PENDING,
            'voucher_number' => 'PV-TEST-2',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('bank-payments.edit', $bankPayment->id));

        $response->assertOk();
        $response->assertSee('No bank details on file', false);
    }
}
