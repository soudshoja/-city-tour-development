<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-8: lockInvoice/unlockInvoice/getLossBearer/
 * updateLossBearer are all bound by raw route-model-bound {invoice} id
 * with no comparison to the caller's own company. Any authenticated user
 * whose role passes the (role/permission-only) Gate could lock, unlock, or
 * rewrite the agent/company loss-split of another company's invoice.
 */
class InvoiceLockTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    private const MANAGE_LOCKS_PERMISSIONS = ['manage locks'];

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function invoiceFor(array $tenant): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
        ]);
    }

    public function test_lock_invoice_rejects_another_companys_invoice(): void
    {
        $tenantA = $this->createTenant(self::MANAGE_LOCKS_PERMISSIONS);
        $tenantB = $this->createTenant();

        $invoiceB = $this->invoiceFor($tenantB);

        $response = $this->actingAs($tenantA['user'])->post(route('invoice.lock', ['invoice' => $invoiceB->id]));

        $response->assertForbidden();
        $this->assertFalse($invoiceB->fresh()->isLocked());
    }

    public function test_lock_invoice_succeeds_for_own_companys_invoice(): void
    {
        $tenantA = $this->createTenant(self::MANAGE_LOCKS_PERMISSIONS);
        $invoiceA = $this->invoiceFor($tenantA);

        $response = $this->actingAs($tenantA['user'])->post(route('invoice.lock', ['invoice' => $invoiceA->id]));

        $response->assertRedirect();
        $this->assertTrue($invoiceA->fresh()->isLocked());
    }

    public function test_unlock_invoice_rejects_another_companys_invoice(): void
    {
        $tenantA = $this->createTenant(self::MANAGE_LOCKS_PERMISSIONS);
        $tenantB = $this->createTenant();

        $invoiceB = $this->invoiceFor($tenantB);
        $invoiceB->lock();

        $response = $this->actingAs($tenantA['user'])->post(route('invoice.unlock', ['invoice' => $invoiceB->id]));

        $response->assertForbidden();
        $this->assertTrue($invoiceB->fresh()->isLocked());
    }

    public function test_get_loss_bearer_rejects_another_companys_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceB = $this->invoiceFor($tenantB);

        // NOT exercised through the named route: GET /invoice/{companyId}/
        // {invoiceNumber} ('show', registered earlier, no auth middleware,
        // no digit constraint on {companyId}) has the same 2-segment shape
        // as GET /invoice/{invoice}/loss-bearer and is matched first by
        // Laravel's router, silently shadowing this route for every
        // request (a pre-existing routes/web.php ordering bug, outside
        // this hotfix's assigned files -- see report). Calling the
        // controller method directly still proves the tenant-isolation fix
        // itself is correct.
        $this->actingAs($tenantA['user']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Controllers\InvoiceController::class)->getLossBearer($invoiceB);
    }

    public function test_update_loss_bearer_rejects_another_companys_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceB = $this->invoiceFor($tenantB);
        $originalAgentLoss = $invoiceB->agent_loss;

        $response = $this->actingAs($tenantA['user'])->putJson(route('invoice.loss-bearer.update', ['invoice' => $invoiceB->id]), [
            'loss_bearer' => 'agent',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
        $this->assertSame($originalAgentLoss, $invoiceB->fresh()->agent_loss);
    }

    public function test_update_loss_bearer_succeeds_for_own_companys_invoice(): void
    {
        $tenantA = $this->createTenant();
        $invoiceA = $this->invoiceFor($tenantA);

        $response = $this->actingAs($tenantA['user'])->putJson(route('invoice.loss-bearer.update', ['invoice' => $invoiceA->id]), [
            'loss_bearer' => 'agent',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertSame(100.0, (float) $invoiceA->fresh()->agent_loss);
    }
}
