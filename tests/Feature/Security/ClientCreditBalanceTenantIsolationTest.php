<?php

namespace Tests\Feature\Security;

use App\Models\Credit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the clients.credit-balance IDOR:
 * ClientController::getCreditBalance() called
 * Credit::getTotalCreditsByClient($id) directly on the raw route
 * parameter -- no Client::findOrFail(), no Gate::authorize('view', ...),
 * no company/branch scope of any kind -- so any authenticated user of
 * any tenant could walk sequential client ids and read every client's
 * credit balance, even though the three sibling AJAX readers on the same
 * Client resource in the same controller (ajaxTasks(), ajaxInvoices(),
 * ajaxPayments()) already open with Client::findOrFail() +
 * Gate::authorize('view', $client). The fix adds the same two lines here
 * and resolves the credit total from the authorized $client model
 * instead of the unchecked $id. See ClientCreditStatementAccessTest for
 * the sibling 'clients.credits' fix this mirrors.
 */
class ClientCreditBalanceTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_user_from_another_company_is_forbidden(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        Credit::create([
            'company_id' => $tenantA['company']->id,
            'branch_id' => $tenantA['branch']->id,
            'client_id' => $tenantA['client']->id,
            'type' => Credit::TOPUP,
            'amount' => 777.77,
        ]);

        $response = $this->actingAs($tenantB['user'])
            ->getJson(route('clients.credit-balance', $tenantA['client']->id));

        $response->assertForbidden();
    }

    public function test_same_company_authorised_user_gets_ok_with_correct_balance(): void
    {
        $tenant = $this->createTenant();

        Credit::create([
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'client_id' => $tenant['client']->id,
            'type' => Credit::TOPUP,
            'amount' => 777.77,
        ]);

        $response = $this->actingAs($tenant['user'])
            ->getJson(route('clients.credit-balance', $tenant['client']->id));

        $response->assertOk();
        $response->assertJson(['credit' => '777.77']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $tenant = $this->createTenant();

        $response = $this->get(route('clients.credit-balance', $tenant['client']->id));

        $response->assertRedirect(route('login'));
    }
}
