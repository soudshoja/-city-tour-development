<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the credits.filter IDOR: CreditController::filter()
 * (GET /credits/filter) validated client_id with 'exists:clients,id' only --
 * proof the id is *a* client, not that it belongs to the caller -- then
 * returned that client's full credit ledger with no ClientPolicy check and
 * no tenant scope. It served the exact same rows as the (already-hardened)
 * 'clients.credits' route, just via the clients.index statement panel's
 * AJAX call (resources/views/clients/index.blade.php's ledgerUrlTemplate),
 * so a company-B user could pull company-A's client credit history by id
 * even though the page itself correctly 403s on the equivalent
 * server-rendered route. See ClientCreditStatementAccessTest for the
 * sibling fix this mirrors.
 */
class CreditFilterTenantIsolationTest extends TestCase
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

        $response = $this->actingAs($tenantB['user'])
            ->getJson(route('credits.filter', [
                'client_id' => $tenantA['client']->id,
                'from' => '2000-01-01',
                'to' => '2999-12-31',
            ]));

        $response->assertForbidden();
    }

    public function test_same_company_authorised_user_gets_ok(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant['user'])
            ->getJson(route('credits.filter', [
                'client_id' => $tenant['client']->id,
                'from' => '2000-01-01',
                'to' => '2999-12-31',
            ]));

        $response->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $tenant = $this->createTenant();

        $response = $this->get(route('credits.filter', [
            'client_id' => $tenant['client']->id,
            'from' => '2000-01-01',
            'to' => '2999-12-31',
        ]));

        $response->assertRedirect(route('login'));
    }
}
