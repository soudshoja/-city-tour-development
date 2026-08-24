<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-2 (Layer 1): the public, unauthenticated
 * invoice-PDF routes (/invoice/{companyId}/{invoiceNumber}/pdf and
 * .../proforma-pdf) must scope by BOTH invoice_number AND the companyId
 * from the URL. Before the fix, companyId was accepted into the method
 * signature and never used, so pairing any real invoice_number with any
 * OTHER company's id in the URL still returned that invoice's PDF.
 */
class InvoicePublicPdfTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_generate_pdf_404s_when_company_id_does_not_own_the_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        // Guest/unauthenticated request (this route has no auth middleware),
        // pairing tenant A's real invoice_number with tenant B's companyId.
        $response = $this->get(route('invoice.pdf', [
            'companyId' => $tenantB['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertNotFound();
    }

    public function test_generate_pdf_works_when_company_id_matches(): void
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

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_proforma_generate_pdf_404s_when_company_id_does_not_own_the_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);

        $response = $this->get(route('invoice.proforma.pdf', [
            'companyId' => $tenantB['company']->id,
            'invoiceNumber' => $invoiceA->invoice_number,
        ]));

        $response->assertNotFound();
    }

    public function test_proforma_generate_pdf_works_when_company_id_matches(): void
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

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
