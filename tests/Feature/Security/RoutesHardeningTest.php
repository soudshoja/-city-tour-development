<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Covers three unrelated route-hardening fixes, per owner decision
 * (2026-09-04):
 *
 *  - GET export-clients (TaskController::exportCsv) now requires 'auth' and
 *    scopes to the caller's own company (unscoped only for Role::ADMIN).
 *  - GET/POST _dusk/* (Laravel\Dusk\DuskServiceProvider's own routes, incl.
 *    the "log in as any user by id" tool) is kept, but gated to an
 *    authenticated platform super admin via RestrictDuskRoutesToSuperAdmin
 *    -- everyone else gets a 404.
 *  - POST /api/invoice (MobileController::store) moved behind auth:sanctum.
 *    Every other invoice/payment-link read route is deliberately untouched.
 *
 * NOTE on export-clients: TaskController::exportCsv() ends with an
 * unconditional exit() (pre-existing, unrelated to this fix) that would
 * terminate the whole PHPUnit process if the controller body actually ran
 * in this test run. Only the guest-is-blocked (middleware) case is
 * exercised here for that route; the "200 when authenticated" / scoping
 * behaviour was verified by reading the controller, not by executing it --
 * see the task report for this limitation.
 */
class RoutesHardeningTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    // ------------------------------------------------------------------
    // export-clients
    // ------------------------------------------------------------------

    public function test_export_clients_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('clients.exportCsv'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // _dusk/login/{userId}/{guard?}
    // ------------------------------------------------------------------

    public function test_dusk_login_guest_gets_404(): void
    {
        $target = User::factory()->create();

        $response = $this->get('/_dusk/login/'.$target->id);

        $response->assertStatus(404);
        $this->assertGuest();
    }

    public function test_dusk_login_normal_company_user_gets_404(): void
    {
        $tenant = $this->createTenant();
        $target = User::factory()->create();

        $response = $this->actingAs($tenant['user'])->get('/_dusk/login/'.$target->id);

        $response->assertStatus(404);
    }

    public function test_dusk_login_super_admin_can_impersonate(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->get('/_dusk/login/'.$target->id);

        // Laravel\Dusk\Http\Controllers\UserController::login() returns a
        // bare 204 on success (no body/redirect) -- see vendor source.
        $response->assertStatus(204);
        $this->assertAuthenticatedAs($target);
    }

    // ------------------------------------------------------------------
    // POST /api/invoice
    // ------------------------------------------------------------------

    public function test_post_api_invoice_without_auth_is_401(): void
    {
        $response = $this->postJson('/api/invoice', []);

        $response->assertStatus(401);
    }

    public function test_post_api_invoice_with_auth_passes_the_gate(): void
    {
        $tenant = $this->createTenant();

        // Deliberately invalid/empty payload: proves the sanctum gate lets an
        // authenticated caller reach the controller (422 validation error,
        // not 401) without exercising MobileController::store()'s
        // ledger-writing body -- that code writes Account/Invoice/
        // JournalEntry rows directly and is out of this fix's scope.
        $response = $this->actingAs($tenant['user'])->postJson('/api/invoice', []);

        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // GET /api/invoice/by/{Id} -- deliberately left public, must still work.
    // ------------------------------------------------------------------

    public function test_get_invoice_by_id_still_works_without_auth(): void
    {
        $tenant = $this->createTenant();
        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
        ]);

        $response = $this->getJson('/api/invoice/by/'.$invoice->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $invoice->id]);
    }
}
