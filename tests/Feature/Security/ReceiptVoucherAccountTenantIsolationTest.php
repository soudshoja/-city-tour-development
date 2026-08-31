<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Company;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Setting;
use App\Services\Accounting\VoucherOptions;
use App\Support\Modules;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for ReceiptVoucherController::buildVoucherDraft()'s 'account' type case
 * (item #5 of the payment/receipt-voucher tenant-isolation pass): it resolved `account_id` via
 * `Account::withoutGlobalScopes()->findOrFail($r->account_id)` with NO company-ownership check
 * at all. validateVoucherRequest() only validates `account_id` with 'exists:accounts,id' -- proof
 * the row is real, never that it belongs to the voucher's own company -- so an authenticated user
 * of company A could create an 'account'-type Receipt Voucher (company_id already locked to A by
 * validateVoucherRequest()'s own pre-existing fix) that names company B's account_id, and once
 * that voucher posts, the resulting journal-entry pair would credit B's real account directly --
 * company A's money landing in company B's chart of accounts.
 *
 * The fix mirrors BankPaymentController::validateVoucherRequest()'s own account-ownership
 * check: buildVoucherDraft()'s 'account' case now scopes the Account lookup to
 * `$r->company_id` (this voucher's own, already-locked company) and throws a ValidationException
 * -- never silently crossing tenants -- when the account does not belong to it. Only WHICH
 * account the 'account' type may resolve to changed; the debit/credit shape, the instrument leg
 * resolution, and every other voucher type are untouched.
 */
class ReceiptVoucherAccountTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * Grants `module.accounting` to $company -- see
     * ReceiptVoucherBankPaymentTenantIsolationTest::grantAccountingModule() for the full
     * rationale (accounting fails CLOSED by default; CreatesTenantFixtures builds a company with
     * no module.* rows at all).
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
     * Seeds a full chart of accounts (CoaSeeder) plus the purpose-code -> account_id mapping
     * (SystemAccountsSeeder) buildVoucherDraft()'s instrument-leg resolution
     * (resolveInstrumentLeg() -> AccountResolver::resolve('CASH_IN_HAND', ...)) needs regardless
     * of which voucher type is under test or whether the P1 posting engine itself is switched on
     * -- buildVoucherDraft() always runs (and therefore always needs CASH_IN_HAND mapped) before
     * PostingSeam decides whether to post through the new engine or the legacy writer.
     */
    private function seedCoa(int $companyId): void
    {
        CoaSeeder::run($companyId);
    }

    /** Sets a low-enough approval threshold that store()'s auto-approve path runs synchronously
     * (postVoucher() -> buildVoucherDraft() -- the code under test) instead of leaving the
     * voucher pending for a separate approve() call. */
    private function setApprovalThreshold(int $companyId, float $threshold): void
    {
        Setting::create([
            'company_id' => $companyId,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => (string) $threshold,
            'type' => 'string',
        ]);
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    public function test_account_type_receipt_voucher_cannot_be_pointed_at_another_companys_account(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->grantAccountingModule($tenantA['company']);
        $this->seedCoa($tenantA['company']->id);
        $this->seedCoa($tenantB['company']->id);
        (new SystemAccountsSeeder)->run();
        $this->setApprovalThreshold($tenantA['company']->id, 100);

        // Company B's own real account -- never touched by company A's own COA.
        $foreignAccount = $this->accountByCode($tenantB['company']->id, '2110');
        $foreignBalanceBefore = (float) $foreignAccount->actual_balance;

        $response = $this->actingAs($tenantA['user'])->post(route('receipt-voucher.store'), [
            'company_id' => $tenantA['company']->id, // caller's own company -- proves this is
            // purely the account_id gap, isolated from the separate company_id-injection hole
            // ReceiptVoucherBankPaymentTenantIsolationTest already covers.
            'branch_id' => $tenantA['branch']->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $foreignAccount->id, // attack: another tenant's account
            'amount' => 50,
        ]);

        // buildVoucherDraft() throws ValidationException (uncaught by store()'s narrower
        // `catch (PostingException $e)`), which Laravel's own exception handler renders as a
        // redirect back with the error attached to `account_id` -- exactly like any other
        // request-validation failure this controller produces.
        $response->assertSessionHasErrors('account_id');

        $receipt = InvoiceReceipt::where('company_id', $tenantA['company']->id)->latest('id')->first();
        $this->assertNotNull($receipt, 'The draft row itself is created before auto-approve runs.');
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $receipt->status);
        $this->assertNull($receipt->transaction_id);

        // The real assertion: no journal entry was ever written against company B's account, and
        // its balance never moved.
        $this->assertSame(0, JournalEntry::where('account_id', $foreignAccount->id)->count());
        $this->assertEqualsWithDelta($foreignBalanceBefore, (float) $foreignAccount->fresh()->actual_balance, 0.0001);
    }

    public function test_account_type_receipt_voucher_still_posts_for_the_callers_own_account(): void
    {
        $tenant = $this->createTenant();

        $this->grantAccountingModule($tenant['company']);
        $this->seedCoa($tenant['company']->id);
        (new SystemAccountsSeeder)->run();
        $this->setApprovalThreshold($tenant['company']->id, 100);

        $account = $this->accountByCode($tenant['company']->id, '2110');

        $response = $this->actingAs($tenant['user'])->post(route('receipt-voucher.store'), [
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
        ]);

        $response->assertRedirect(route('receipt-voucher.index'));
        $response->assertSessionHas('success');

        $receipt = InvoiceReceipt::where('company_id', $tenant['company']->id)->latest('id')->first();
        $this->assertNotNull($receipt);
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $receipt->status);
        $this->assertNotNull($receipt->transaction_id);

        $lines = JournalEntry::where('transaction_id', $receipt->transaction_id)->get();
        $credit = $lines->firstWhere('account_id', $account->id);
        $this->assertNotNull($credit, 'The named account is still the credited leg for a legitimate same-company voucher.');
        $this->assertEqualsWithDelta(50.0, (float) $credit->credit, 0.001);
    }
}
