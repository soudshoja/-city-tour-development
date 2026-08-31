<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the /credits IDOR (W41): CreditController::index() correctly scoped
 * its main $allCreditRecords query to Role::COMPANY's own company_id, but the three lists it
 * feeds the view for the topup-modal dropdowns -- Agent::all(), Client::whereIn('agent_id',
 * <every agent id system-wide>), Invoice::all() -- were completely unscoped. Any Role::COMPANY
 * user opening /credits therefore received every other company's agents, clients and invoices in
 * the page payload. The fix scopes all three via the agent -> branch -> company chain
 * (getCompanyId()), matching the convention already used by InvoiceController::index() and
 * AccountingController.php:740/766.
 */
class CreditIndexTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_company_user_does_not_see_another_companys_agents_clients_or_invoices(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceB = Invoice::factory()->create([
            'agent_id' => $tenantB['agent']->id,
            'client_id' => $tenantB['client']->id,
        ]);

        $response = $this->actingAs($tenantA['user'])->get(route('credits.index'));

        $response->assertOk();

        $agents = $response->viewData('agents');
        $clients = $response->viewData('clients');
        $invoices = $response->viewData('invoices');

        $this->assertFalse($agents->pluck('id')->contains($tenantB['agent']->id), 'Company A leaked company B\'s agent.');
        $this->assertFalse($clients->pluck('id')->contains($tenantB['client']->id), 'Company A leaked company B\'s client.');
        $this->assertFalse($invoices->pluck('id')->contains($invoiceB->id), 'Company A leaked company B\'s invoice.');
    }

    public function test_company_user_still_sees_their_own_agents_clients_and_invoices(): void
    {
        $tenant = $this->createTenant();

        $invoice = Invoice::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
        ]);

        $response = $this->actingAs($tenant['user'])->get(route('credits.index'));

        $response->assertOk();

        $agents = $response->viewData('agents');
        $clients = $response->viewData('clients');
        $invoices = $response->viewData('invoices');

        $this->assertTrue($agents->pluck('id')->contains($tenant['agent']->id), 'Legitimate own agent missing from scoped list.');
        $this->assertTrue($clients->pluck('id')->contains($tenant['client']->id), 'Legitimate own client missing from scoped list.');
        $this->assertTrue($invoices->pluck('id')->contains($invoice->id), 'Legitimate own invoice missing from scoped list.');
    }
}
