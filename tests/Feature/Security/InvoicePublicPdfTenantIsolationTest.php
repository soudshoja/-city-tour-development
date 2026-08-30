<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-2 (Layer 1): the invoice-PDF routes
 * (/invoice/{companyId}/{invoiceNumber}/pdf and .../proforma-pdf) must scope
 * by BOTH invoice_number AND the companyId from the URL. Before the fix,
 * companyId was accepted into the method signature and never used, so
 * pairing any real invoice_number with any OTHER company's id in the URL
 * still returned that invoice's PDF.
 *
 * Superseded/broadened by the invoice-wide IDOR fix (see
 * InvoicePublicRoutesTest.php): these two routes are no longer
 * withoutMiddleware(['auth']) -- a guest now gets redirected to login on the
 * plain URL, and the "no auth required" case moves to the signed
 * 'invoice.pdf.public' / 'invoice.proforma.pdf.public' routes. The
 * companyId-scoping assertions this file exists for are kept, exercised
 * against both the authenticated route (as the owning tenant, since only
 * they can reach it now) and the signed public route.
 */
class InvoicePublicPdfTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_guest_on_plain_pdf_url_is_redirected_to_login(): void
    {
        $tenantA = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $response = $this->get(route('invoice.pdf', [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_generate_pdf_404s_when_company_id_does_not_own_the_invoice(): void
    {
        $tenantA = $this->createTenant(['view invoice']);
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        // Tenant A's own user is authorized to view SOME invoice via
        // InvoicePolicy, but the companyId in the URL belongs to tenant B --
        // the whereHas('agent.branch.company', ...) scope on the lookup must
        // still reject the mismatched pair before authorization ever gets a
        // chance to run. generatePdf()'s "not found" branch redirects an
        // authenticated user back to invoices.index rather than aborting
        // 404 (see the guest case above for the 404 path), so the
        // assertion here is "definitely not tenant B's PDF", not a status
        // code -- the mismatched pair must never resolve to a 200.
        $response = $this->actingAs($tenantA['user'])->get(route('invoice.pdf', [
            'companyId' => $tenantB['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertRedirect(route('invoices.index'));
    }

    public function test_authenticated_generate_pdf_works_when_company_id_matches(): void
    {
        $tenantA = $this->createTenant(['view invoice']);

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $response = $this->actingAs($tenantA['user'])->get(route('invoice.pdf', [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_signed_pdf_url_works_with_no_auth_when_company_id_matches(): void
    {
        $tenantA = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $url = URL::temporarySignedRoute('invoice.pdf.public', now()->addMinutes(10), [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_signed_pdf_url_404s_when_company_id_does_not_own_the_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        // Even a validly-signed URL for this exact (wrong) companyId +
        // invoiceNumber pair must still 404 -- the signature only proves
        // the link wasn't tampered with, not that the pairing is correct.
        $url = URL::temporarySignedRoute('invoice.pdf.public', now()->addMinutes(10), [
            'companyId' => $tenantB['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]);

        $response = $this->get($url);

        $response->assertNotFound();
    }

    public function test_guest_on_plain_proforma_pdf_url_is_redirected_to_login(): void
    {
        $tenantA = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $response = $this->get(route('invoice.proforma.pdf', [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_proforma_generate_pdf_404s_when_company_id_does_not_own_the_invoice(): void
    {
        $tenantA = $this->createTenant(['view invoice']);
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        // Same "not found" branch redirects an authenticated user instead
        // of aborting 404 -- see the analogous invoice.pdf test above.
        $response = $this->actingAs($tenantA['user'])->get(route('invoice.proforma.pdf', [
            'companyId' => $tenantB['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertRedirect(route('invoices.index'));
    }

    public function test_authenticated_proforma_generate_pdf_works_when_company_id_matches(): void
    {
        $tenantA = $this->createTenant(['view invoice']);

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $response = $this->actingAs($tenantA['user'])->get(route('invoice.proforma.pdf', [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_signed_proforma_pdf_url_works_with_no_auth_when_company_id_matches(): void
    {
        $tenantA = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $url = URL::temporarySignedRoute('invoice.proforma.pdf.public', now()->addMinutes(10), [
            'companyId' => $tenantA['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_signed_proforma_pdf_url_404s_when_company_id_does_not_own_the_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $url = URL::temporarySignedRoute('invoice.proforma.pdf.public', now()->addMinutes(10), [
            'companyId' => $tenantB['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]);

        $response = $this->get($url);

        $response->assertNotFound();
    }
}
