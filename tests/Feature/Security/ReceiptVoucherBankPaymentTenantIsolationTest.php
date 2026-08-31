<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Company;
use App\Models\InvoiceReceipt;
use App\Models\Setting;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the request-trusted `company_id` in ReceiptVoucherController::
 * validateVoucherRequest() and BankPaymentController::validateVoucherRequest(): both only
 * validated that the posted `company_id` EXISTS ('exists:companies,id'), never that it belongs
 * to the authenticated user. Any logged-in user of company A could POST `company_id = B` and
 * create/update receipt vouchers and bank payments -- money documents that post journal entries
 * -- against company B. BankPaymentController then resolved every account with
 * Account::withoutGlobalScopes() keyed on that same trusted $companyId, dropping tenant scoping
 * entirely.
 *
 * The fix: right after validation, both methods now overwrite the request-supplied value with
 * `getCompanyId(Auth::user())` -- the same helper (and the same admin/session-selected-company
 * convention) `assertSameCompanyOrUnscopedAdmin()` already relies on elsewhere in both
 * controllers -- so nothing downstream ever reads the attacker-supplied value again.
 */
class ReceiptVoucherBankPaymentTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * Grants `module.accounting` to $company. Both routes under test carry the 'module:accounting'
     * gate (see EnsureModuleEnabled), and accounting fails CLOSED for a company with no
     * `module.*` rows (config('modules.default_disabled') -- see Company::hasModule()).
     * CreatesTenantFixtures::createTenant() builds exactly that kind of company, so the ACTING
     * tenant in every test below needs this grant to clear the middleware and reach the
     * tenant-isolation logic actually under test -- same helper/rationale as
     * AccountingAjaxTenantIsolationTest::grantAccountingModule().
     */
    private function grantAccountingModule(Company $company): void
    {
        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => Modules::settingKey(Modules::ACCOUNTING)],
            ['type' => 'boolean', 'value' => true]
        );

        Company::forgetModuleCache();
    }

    /**
     * A leaf account nested under an 'Assets' -> 'Bank Accounts' chain -- BankPaymentController's
     * validateVoucherRequest() also runs AccountResolver::assertUnderBankGroup() on
     * `pay_from_account`, which walks the parent chain looking for an ancestor literally named
     * config('accounting.engine.bank_group_name', 'Bank Accounts'). A bare makeAccount() (no
     * parent at all) fails that check regardless of tenant, so the "still works for same company"
     * control test needs this instead; the "rejects another company's accounts" test does not --
     * it fails the company-ownership lookup before assertUnderBankGroup() ever runs.
     */
    private function makeBankAccount(int $companyId, string $name, float $balance = 1000.00): Account
    {
        $assets = Account::create([
            'name' => 'Assets', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
        ]);
        $bankGroup = Account::create([
            'name' => 'Bank Accounts', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
            'parent_id' => $assets->id, 'root_id' => $assets->id,
        ]);

        return Account::create([
            'name' => $name,
            'level' => 3,
            'actual_balance' => $balance,
            'opening_balance' => $balance,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $companyId,
            'parent_id' => $bankGroup->id,
            'root_id' => $assets->id,
        ]);
    }

    private function makeAccount(int $companyId, string $name, float $balance = 1000.00): Account
    {
        return Account::create([
            'name' => $name,
            'level' => 4,
            'actual_balance' => $balance,
            'opening_balance' => $balance,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $companyId,
        ]);
    }

    // ---- ReceiptVoucherController::store() ----------------------------------------------

    public function test_receipt_voucher_store_locks_company_id_to_the_authenticated_users_company(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->grantAccountingModule($tenantA['company']);

        // A's own, legitimately-owned account -- the attack here is purely the injected
        // company_id field, isolating this test from the separate (pre-existing, out of scope)
        // question of whether account_id itself is tenant-checked for RV's 'account' type.
        $account = $this->makeAccount($tenantA['company']->id, 'Test Revenue Account');

        $response = $this->actingAs($tenantA['user'])->post(route('receipt-voucher.store'), [
            'company_id' => $tenantB['company']->id, // attack: inject another company's id
            'branch_id' => $tenantA['branch']->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 25,
        ]);

        $response->assertRedirect(route('receipt-voucher.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('invoice_receipts', 1);

        $receipt = InvoiceReceipt::first();
        $this->assertNotNull($receipt);
        $this->assertSame($tenantA['company']->id, $receipt->company_id);
        $this->assertSame(0, InvoiceReceipt::where('company_id', $tenantB['company']->id)->count());
    }

    public function test_receipt_voucher_store_still_works_for_same_company(): void
    {
        $tenant = $this->createTenant();
        $this->grantAccountingModule($tenant['company']);
        $account = $this->makeAccount($tenant['company']->id, 'Test Revenue Account');

        $response = $this->actingAs($tenant['user'])->post(route('receipt-voucher.store'), [
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 25,
        ]);

        $response->assertRedirect(route('receipt-voucher.index'));
        $response->assertSessionHas('success');

        $receipt = InvoiceReceipt::first();
        $this->assertNotNull($receipt);
        $this->assertSame($tenant['company']->id, $receipt->company_id);
    }

    // ---- BankPaymentController::store() --------------------------------------------------

    /**
     * The strongest possible proof for BankPaymentController: before the fix, this EXACT
     * payload (company_id = B, and B's own real accounts) would have created a BankPayment and
     * posted journal entries against company B using B's real money accounts -- a full
     * cross-tenant breach, since Account::withoutGlobalScopes() trusted the injected
     * $companyId completely. After the fix $companyId is forced to A regardless of the
     * injected value, so B's accounts fail the "belongs to this company" lookup and the whole
     * request is rejected before anything is written.
     */
    public function test_bank_payment_store_rejects_another_companys_accounts_even_with_injected_company_id(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->grantAccountingModule($tenantA['company']);

        $bank = $this->makeAccount($tenantB['company']->id, 'Company B Bank', 1000.00);
        $target = $this->makeAccount($tenantB['company']->id, 'Company B Supplier', 500.00);

        $response = $this->actingAs($tenantA['user'])->post(route('bank-payments.store'), [
            'company_id' => $tenantB['company']->id, // attack: inject another company's id
            'branch_id' => $tenantA['branch']->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'items' => [
                ['account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $response->assertSessionHasErrors('pay_from_account');
        $this->assertDatabaseCount('bank_payments', 0);

        // Company B's real balances must be untouched -- before the fix this payload would have
        // debited/credited them directly.
        $this->assertSame(1000.00, (float) $bank->fresh()->actual_balance);
        $this->assertSame(500.00, (float) $target->fresh()->actual_balance);
    }

    public function test_bank_payment_store_still_works_for_same_company_accounts(): void
    {
        $tenant = $this->createTenant();
        $this->grantAccountingModule($tenant['company']);
        $bank = $this->makeBankAccount($tenant['company']->id, 'Test Bank', 1000.00);
        $target = $this->makeAccount($tenant['company']->id, 'Test Supplier', 500.00);

        $response = $this->actingAs($tenant['user'])->post(route('bank-payments.store'), [
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'docdate' => now()->toDateString(),
            'bankpaymenttype' => 'Payment',
            'pay_from_account' => $bank->id,
            'items' => [
                ['account_id' => $target->id, 'credit' => 50],
            ],
        ]);

        $response->assertSessionMissing('error');
        $this->assertDatabaseCount('bank_payments', 1);
        $this->assertDatabaseHas('bank_payments', [
            'company_id' => $tenant['company']->id,
        ]);
    }
}
