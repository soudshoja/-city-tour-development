<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the invoice-wide IDOR/leak fix: invoice.show /
 * .pdf / .split / .proforma / .proforma-pdf (and their Arabic twins) used to
 * be withoutMiddleware(['auth']) on a guessable {companyId}/{invoiceNumber}
 * pair, with no policy check in the controller, and the view data included
 * internal supplier_price/markup_price/profit/commission figures. Anyone
 * who could guess/enumerate the pair could read any tenant's invoice,
 * margins included.
 *
 * The fix (see InvoiceController::authorizeStaffInvoiceAccess(),
 * ::isPublicInvoiceRequest(), ::scrubInvoiceDetailsForPublicView(), and
 * Invoice::publicUrl()):
 *  - The plain routes ('invoice.show', 'invoice.pdf', ...) now require a
 *    logged-in, same-company (or admin) user.
 *  - A signed-URL-only '.public' twin of each ('invoice.show.public',
 *    'invoice.pdf.public', ...) preserves the client-facing "share this
 *    invoice" use case, reachable only via Invoice::publicUrl().
 *  - Those '.public' variants also strip supplier_price/markup_price/
 *    profit/commission from what they hand to the view.
 */
class InvoicePublicRoutesTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * Builds an invoice (with one paid InvoicePartial, so show()/pdf() don't
     * 404 on "no partials") carrying distinctive, easy-to-assert-on
     * internal pricing figures on its one InvoiceDetail.
     */
    private function createInvoiceWithSensitiveDetail(array $tenant): Invoice
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
        ]);

        $task = Task::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
        ]);

        InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_price' => 999.99,
            'supplier_price' => 123.456,
            'markup_price' => 234.567,
            'profit' => 345.678,
            'commission' => 45.678,
        ]);

        InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $tenant['client']->id,
            'amount' => 999.99,
            'service_charge' => 0,
            'status' => 'paid',
            'expiry_date' => now()->addDays(30),
            'type' => 'full',
            'payment_gateway' => 'tap',
        ]);

        return $invoice->fresh(['agent.branch.company']);
    }

    // ---- invoice.show ------------------------------------------------

    public function test_show_guest_on_plain_url_is_redirected_to_login(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $response = $this->get(route('invoice.show', [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_show_other_company_user_is_forbidden(): void
    {
        $tenantA = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenantA);

        $tenantB = $this->createTenant(['view invoice']);

        $response = $this->actingAs($tenantB['user'])->get(route('invoice.show', [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertForbidden();
    }

    public function test_show_same_company_user_sees_it(): void
    {
        $tenant = $this->createTenant(['view invoice']);
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $response = $this->actingAs($tenant['user'])->get(route('invoice.show', [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertOk();
    }

    public function test_show_signed_url_works_with_no_auth_and_hides_internal_figures(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = $invoice->publicUrl('show');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertDontSee('123.456');
        $response->assertDontSee('234.567');
        $response->assertDontSee('345.678');
        $response->assertDontSee('45.678');
    }

    public function test_show_signed_url_tampered_signature_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = $invoice->publicUrl('show');
        $tampered = preg_replace('/.$/', $url[strlen($url) - 1] === 'a' ? 'b' : 'a', $url);

        $response = $this->get($tampered);

        $response->assertForbidden();
    }

    public function test_show_signed_url_expired_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = URL::temporarySignedRoute('invoice.show.public', now()->subMinute(), [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]);

        $response = $this->get($url);

        $response->assertForbidden();
    }

    // ---- invoice.pdf ---------------------------------------------------

    public function test_pdf_guest_on_plain_url_is_redirected_to_login(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $response = $this->get(route('invoice.pdf', [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_pdf_other_company_user_is_forbidden(): void
    {
        $tenantA = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenantA);

        $tenantB = $this->createTenant(['view invoice']);

        $response = $this->actingAs($tenantB['user'])->get(route('invoice.pdf', [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertForbidden();
    }

    public function test_pdf_same_company_user_sees_it(): void
    {
        $tenant = $this->createTenant(['view invoice']);
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $response = $this->actingAs($tenant['user'])->get(route('invoice.pdf', [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_signed_url_works_with_no_auth_and_hides_internal_figures(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = $invoice->publicUrl('pdf');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDontSee('123.456');
        $response->assertDontSee('234.567');
        $response->assertDontSee('345.678');
        $response->assertDontSee('45.678');
    }

    public function test_pdf_signed_url_tampered_signature_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = $invoice->publicUrl('pdf');
        $tampered = preg_replace('/.$/', $url[strlen($url) - 1] === 'a' ? 'b' : 'a', $url);

        $response = $this->get($tampered);

        $response->assertForbidden();
    }

    public function test_pdf_signed_url_expired_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = URL::temporarySignedRoute('invoice.pdf.public', now()->subMinute(), [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]);

        $response = $this->get($url);

        $response->assertForbidden();
    }

    // ---- invoice.proforma ----------------------------------------------
    //
    // fix2 blocker 1 regression coverage: proforma.blade.php always wrapped
    // its content in <x-app-layout>, whose render() unconditionally calls
    // getCompanyId(Auth::user()) and reads $user->role_id with no null
    // guard. A guest hitting the signed '.public' proforma URL (Auth::user()
    // === null) blew up with "Attempt to read property \"role_id\" on
    // null" -> ViewException -> HTTP 500, so the "share this proforma"
    // use case never actually worked. The fix renders a self-contained
    // document (mirroring show/split/show-refund) instead of
    // <x-app-layout> when $isPublicInvoiceRequest is true.

    public function test_proforma_guest_on_plain_url_is_redirected_to_login(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $response = $this->get(route('invoice.proforma', [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_proforma_other_company_user_is_forbidden(): void
    {
        $tenantA = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenantA);

        $tenantB = $this->createTenant(['view invoice']);

        $response = $this->actingAs($tenantB['user'])->get(route('invoice.proforma', [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertForbidden();
    }

    public function test_proforma_same_company_user_sees_it(): void
    {
        $tenant = $this->createTenant(['view invoice']);
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $response = $this->actingAs($tenant['user'])->get(route('invoice.proforma', [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        $response->assertOk();
    }

    public function test_proforma_signed_url_renders_for_guest_instead_of_500(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = $invoice->publicUrl('proforma');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('PROFORMA INVOICE');
    }

    public function test_proforma_signed_url_hides_internal_figures(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = $invoice->publicUrl('proforma');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertDontSee('123.456');
        $response->assertDontSee('234.567');
        $response->assertDontSee('345.678');
        $response->assertDontSee('45.678');
    }

    public function test_proforma_signed_url_tampered_signature_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = $invoice->publicUrl('proforma');
        $tampered = preg_replace('/.$/', $url[strlen($url) - 1] === 'a' ? 'b' : 'a', $url);

        $response = $this->get($tampered);

        $response->assertForbidden();
    }

    public function test_proforma_signed_url_expired_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoiceWithSensitiveDetail($tenant);

        $url = URL::temporarySignedRoute('invoice.proforma.public', now()->subMinute(), [
            'companyId' => $tenant['company']->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]);

        $response = $this->get($url);

        $response->assertForbidden();
    }
}
