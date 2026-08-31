<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\Setting;
use App\Models\Transaction;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the receipt-voucher IDOR fix: 'receipt-voucher.show' used to be a bare
 * firstOrFail() on a guessable {companyId}/{voucherNumber} pair (a single-digit company id and a
 * sequential reference number), reachable with NO auth, NO signature check, and NO policy check
 * at all -- any anonymous caller who could guess/enumerate the pair could read any tenant's
 * receipt voucher (real KWD amount, invoice amount, client name, status).
 *
 * The fix (see ReceiptVoucherController::show()/isPublicVoucherRequest() and
 * InvoiceReceipt::publicUrl()) mirrors InvoiceController's own invoice.show/.public and
 * RefundController's refunds.show/.public splits exactly:
 *  - The plain route ('receipt-voucher.show') now requires a logged-in, same-company (or admin)
 *    user -- ReceiptVoucherPolicy::view() PLUS assertSameCompanyOrUnscopedAdmin().
 *  - A signed-URL-only '.public' twin ('receipt-voucher.show.public') preserves the client-facing
 *    "share this receipt" use case, reachable only via InvoiceReceipt::publicUrl().
 */
class ReceiptVoucherPublicRoutesTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * Grants `module.accounting` to $company. The authenticated 'receipt-voucher.show' route
     * carries the 'module:accounting' gate (inherited from the group; see EnsureModuleEnabled),
     * and accounting fails CLOSED for a company with no `module.*` rows
     * (config('modules.default_disabled') -- see Company::hasModule()).
     * CreatesTenantFixtures::createTenant() builds exactly that kind of company, so the ACTING
     * (requesting) tenant needs this grant to clear the middleware and reach the auth/tenant
     * checks actually under test -- same helper/rationale as
     * AccountingAjaxTenantIsolationTest::grantAccountingModule(). Not needed for the
     * '.show.public' signed variant, which explicitly keeps skipping that middleware.
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
     * Builds a POSTED receipt voucher (InvoiceReceipt + its Transaction) with a distinctive
     * reference number and amount -- show()'s own lookup is keyed on transaction.reference_number,
     * not invoice_receipts.voucher_number (see InvoiceReceipt::publicUrl()'s own docblock for why).
     * Mirrors the minimal field set ReceiptVoucherController::writeLegacyTransaction() actually
     * writes, rather than the stale Transaction factory (whose definition() references columns
     * that no longer exist on this table).
     */
    private function createPostedReceiptVoucher(array $tenant, string $referenceNumber = 'RV-000123', float $amount = 42.5): InvoiceReceipt
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
        ]);

        $transaction = Transaction::forceCreate([
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'entity_id' => $tenant['company']->id,
            'entity_type' => 'company',
            'transaction_type' => 'RV',
            'amount' => $amount,
            'description' => 'Receipt Voucher - Test',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Payment',
            'reference_number' => $referenceNumber,
            'name' => $tenant['client']->full_name ?? 'Test Client',
            'transaction_date' => now(),
            'doc_type' => 'RV',
            'sub_type' => 'ACCOUNT',
            'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted',
            'total_debit' => $amount,
            'total_credit' => $amount,
            'idempotency_key' => 'test-rv-'.$referenceNumber,
            'created_by' => $tenant['user']->id,
            'posted_by' => $tenant['user']->id,
            'posted_at' => now(),
        ]);

        return InvoiceReceipt::create([
            'type' => 'account',
            'invoice_id' => $invoice->id,
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'status' => InvoiceReceipt::STATUS_APPROVED,
            'amount' => $amount,
            'transaction_id' => $transaction->id,
            'is_used' => true,
        ]);
    }

    // ---- receipt-voucher.show (authenticated) ---------------------------------------------

    public function test_show_guest_on_plain_url_is_redirected_to_login(): void
    {
        $tenant = $this->createTenant();
        $this->createPostedReceiptVoucher($tenant);

        $response = $this->get(route('receipt-voucher.show', [
            'companyId' => $tenant['company']->id,
            'voucherNumber' => 'RV-000123',
        ]));

        $response->assertRedirect(route('login'));
    }

    /**
     * The core IDOR-enumeration case: an anonymous caller who guessed/enumerated a valid
     * {companyId}/{voucherNumber} pair (no signature, no session at all) must never see the
     * voucher's contents -- same assertion as the guest-redirect case above, phrased for the
     * specific attack this fix closes.
     */
    public function test_anonymous_enumeration_of_a_guessable_url_is_rejected_not_leaked(): void
    {
        $tenant = $this->createTenant();
        $this->createPostedReceiptVoucher($tenant, 'RV-000999', 777.5);

        $response = $this->get('/receipt-voucher/'.$tenant['company']->id.'/RV-000999');

        $response->assertRedirect(route('login'));
        $response->assertDontSee('777.5');
    }

    public function test_show_other_company_user_is_forbidden(): void
    {
        $tenantA = $this->createTenant();
        $this->createPostedReceiptVoucher($tenantA);

        $tenantB = $this->createTenant();
        $this->grantAccountingModule($tenantB['company']);

        $response = $this->actingAs($tenantB['user'])->get(route('receipt-voucher.show', [
            'companyId' => $tenantA['company']->id,
            'voucherNumber' => 'RV-000123',
        ]));

        // Transaction carries its own company-scoped global scope (app/Models/Transaction.php's
        // booted(): `where('company_id', $user->company->id)` for Role::COMPANY), which already
        // blocks this whereHas('transaction', ...) query from matching tenant A's row before
        // show() ever reaches Gate::authorize()/assertSameCompanyOrUnscopedAdmin() -- so this
        // specific (Role::COMPANY-vs-Role::COMPANY) case surfaces as 404 (query miss), not 403
        // (policy denial). Both are secure denials; see
        // test_show_is_forbidden_for_an_admin_scoped_to_a_different_company below for the case
        // that actually exercises this fix's own tenant check (that global scope is a no-op for
        // Role::ADMIN, same as ReceiptVoucherPolicy::view()'s own role-only fallthrough).
        $response->assertNotFound();
    }

    /**
     * Role::ADMIN is the one role Transaction's own global scope does NOT filter by company
     * (`where('company_id', '!=', null)` -- see app/Models/Transaction.php's booted()), and
     * ReceiptVoucherPolicy::view() falls through to viewAny() for admin too (role-only, not
     * tenant-scoped) -- so an admin whose session is scoped to company B requesting company A's
     * voucher reaches show()'s query and Gate::authorize() untouched, and is stopped ONLY by
     * this fix's own assertSameCompanyOrUnscopedAdmin() call. Mirrors
     * ChequeImageUploadSecurityTest's identical admin/session pattern for the same helper.
     */
    public function test_show_is_forbidden_for_an_admin_scoped_to_a_different_company(): void
    {
        $tenantA = $this->createTenant();
        $this->createPostedReceiptVoucher($tenantA);

        $tenantB = $this->createTenant();
        $this->grantAccountingModule($tenantB['company']);

        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::ADMIN]);
        session(['company_id' => $tenantB['company']->id]);

        $response = $this->actingAs($admin)->get(route('receipt-voucher.show', [
            'companyId' => $tenantA['company']->id,
            'voucherNumber' => 'RV-000123',
        ]));

        $response->assertForbidden();
    }

    public function test_show_same_company_user_sees_it(): void
    {
        $tenant = $this->createTenant();
        $this->grantAccountingModule($tenant['company']);
        $this->createPostedReceiptVoucher($tenant);

        $response = $this->actingAs($tenant['user'])->get(route('receipt-voucher.show', [
            'companyId' => $tenant['company']->id,
            'voucherNumber' => 'RV-000123',
        ]));

        $response->assertOk();
        $response->assertSee('RV-000123');
    }

    // ---- receipt-voucher.show.public (signed) ----------------------------------------------

    public function test_show_signed_url_works_with_no_auth(): void
    {
        $tenant = $this->createTenant();
        $receipt = $this->createPostedReceiptVoucher($tenant, 'RV-000456', 99.75);

        $url = $receipt->fresh('transaction')->publicUrl();

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('RV-000456');
    }

    public function test_show_signed_url_tampered_signature_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $receipt = $this->createPostedReceiptVoucher($tenant, 'RV-000789', 15);

        $url = $receipt->fresh('transaction')->publicUrl();
        $tampered = preg_replace('/.$/', $url[strlen($url) - 1] === 'a' ? 'b' : 'a', $url);

        $response = $this->get($tampered);

        $response->assertForbidden();
    }

    public function test_show_signed_url_expired_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $this->createPostedReceiptVoucher($tenant, 'RV-000321', 15);

        $url = URL::temporarySignedRoute('receipt-voucher.show.public', now()->subMinute(), [
            'companyId' => $tenant['company']->id,
            'voucherNumber' => 'RV-000321',
        ]);

        $response = $this->get($url);

        $response->assertForbidden();
    }

    public function test_show_public_route_works_even_when_accounting_module_is_disabled(): void
    {
        // The '.public' route deliberately keeps skipping 'module:accounting' -- an anonymous
        // client opening their own shared link has no session/company to check the module
        // against, and a link that legitimately went out must not start 404ing the moment a
        // company's accounting module is toggled off (see routes/web.php's own comment above the
        // 'receipt-voucher' group).
        $tenant = $this->createTenant();
        $receipt = $this->createPostedReceiptVoucher($tenant, 'RV-000654', 8);

        (new \App\Support\Entitlements\ApplyCompanyModulePreset())->apply(
            $tenant['company'],
            [\App\Support\Modules::ACCOUNTING => false]
        );

        $url = $receipt->fresh('transaction')->publicUrl();

        $response = $this->get($url);

        $response->assertOk();
    }
}
