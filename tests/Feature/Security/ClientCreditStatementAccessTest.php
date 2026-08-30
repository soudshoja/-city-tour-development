<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for closing the client credit-ledger IDOR:
 * routes/web.php's 'clients.credits' route used to be registered
 * withoutMiddleware(['auth']), and ClientController::showCredit() did
 * nothing but Client::findOrFail($id) -- anyone who guessed a client id
 * could read that client's entire credit ledger, no login required.
 *
 * The fix splits the page into two routes:
 *  - 'clients.credits'        -- requires auth + ClientPolicy::view().
 *  - 'clients.credits.shared' -- unauthenticated, but only reachable via a
 *                                 Laravel temporary signed URL
 *                                 (Client::creditStatementUrl()).
 */
class ClientCreditStatementAccessTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_guest_on_plain_url_is_redirected_to_login(): void
    {
        $tenant = $this->createTenant();

        $response = $this->get(route('clients.credits', $tenant['client']->id));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_on_plain_url_gets_401_for_json(): void
    {
        $tenant = $this->createTenant();

        $response = $this->getJson(route('clients.credits', $tenant['client']->id));

        $response->assertUnauthorized();
    }

    public function test_user_from_another_company_is_forbidden(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantB['user'])
            ->get(route('clients.credits', $tenantA['client']->id));

        $response->assertForbidden();
    }

    public function test_same_company_authorised_user_sees_client_name(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant['user'])
            ->get(route('clients.credits', $tenant['client']->id));

        $response->assertOk();
        $response->assertSee($tenant['client']->full_name);
    }

    public function test_signed_url_works_with_no_auth(): void
    {
        $tenant = $this->createTenant();

        $url = $tenant['client']->creditStatementUrl();

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee($tenant['client']->full_name);
    }

    public function test_tampered_signature_is_forbidden(): void
    {
        $tenant = $this->createTenant();

        $url = $tenant['client']->creditStatementUrl();
        // Flip the trailing signature so it no longer matches the URL.
        $tampered = preg_replace('/.$/', $url[strlen($url) - 1] === 'a' ? 'b' : 'a', $url);

        $response = $this->get($tampered);

        $response->assertForbidden();
    }

    public function test_expired_signed_url_is_forbidden(): void
    {
        $tenant = $this->createTenant();

        $expiredUrl = URL::temporarySignedRoute(
            'clients.credits.shared',
            now()->subMinute(),
            ['id' => $tenant['client']->id]
        );

        $response = $this->get($expiredUrl);

        $response->assertForbidden();
    }
}
