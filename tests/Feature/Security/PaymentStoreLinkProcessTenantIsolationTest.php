<?php

namespace Tests\Feature\Security;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for PaymentController::paymentStoreLinkProcess() -- a second front door
 * into payment creation, reachable via POST payment/link/store whenever `payment_gateway` is
 * non-null (the `payment_gateway == null` branch already routes through the hardened
 * multiPaymentMethodProcess() instead -- see MultiPaymentMethodProcessTenantIsolationTest).
 * `company_id` was taken straight from request input with only 'exists:companies,id', and
 * Client::find()/Agent::find() had no company check at all, so any authenticated user could
 * inject a real Payment row into another company's books by supplying that company's
 * client_id/agent_id (and/or company_id) pair.
 *
 * The fix mirrors multiPaymentMethodProcess()'s identical shape: resolve client/agent WITH their
 * company relations, require them to belong to the SAME company, then require the caller to
 * match that company (or be an unscoped admin). company_id is now always DERIVED from the
 * verified agent -- request->company_id is no longer trusted at all.
 */
class PaymentStoreLinkProcessTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'payment_gateway' => 'Knet', // non-null -> legacy branch under test, not
            // multiPaymentMethodProcess() and not the source=import branch.
            'amount' => 50,
            // payments.language is NOT NULL with no DB default when explicitly written (same
            // pre-existing quirk MultiPaymentMethodProcessTenantIsolationTest's own storePayload()
            // already works around) -- paymentStoreLinkProcess() writes $request->language
            // verbatim, so it must be supplied for Payment::create() to succeed in the
            // same-company happy-path tests below.
            'language' => 'en',
        ], $overrides);
    }

    public function test_cannot_create_payment_for_a_client_and_agent_both_belonging_to_another_company(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantA['user'])
            ->post(route('payment.link.store'), $this->storePayload([
                'client_id' => $tenantB['client']->id,
                'agent_id' => $tenantB['agent']->id,
            ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cannot_create_payment_for_own_client_paired_with_another_companys_agent(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantA['user'])
            ->post(route('payment.link.store'), $this->storePayload([
                'client_id' => $tenantA['client']->id,
                'agent_id' => $tenantB['agent']->id,
            ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cannot_create_payment_for_own_agent_paired_with_another_companys_client(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantA['user'])
            ->post(route('payment.link.store'), $this->storePayload([
                'client_id' => $tenantB['client']->id,
                'agent_id' => $tenantA['agent']->id,
            ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * Even with a legitimately-owned client and agent, an injected company_id used to be trusted
     * outright -- proves that value is now ignored in favour of the agent-derived one.
     */
    public function test_injected_company_id_is_ignored_in_favour_of_the_verified_agents_company(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantA['user'])
            ->post(route('payment.link.store'), $this->storePayload([
                'client_id' => $tenantA['client']->id,
                'agent_id' => $tenantA['agent']->id,
                'company_id' => $tenantB['company']->id, // attack: inject another company's id
            ]));

        $response->assertRedirect(route('payment.link.index'));
        $response->assertSessionHas('success');

        $payment = Payment::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame($tenantA['company']->id, $payment->company_id);
    }

    public function test_same_company_payment_creation_still_works_unchanged(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant['user'])
            ->post(route('payment.link.store'), $this->storePayload([
                'client_id' => $tenant['client']->id,
                'agent_id' => $tenant['agent']->id,
            ]));

        $response->assertRedirect(route('payment.link.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'company_id' => $tenant['company']->id,
            'amount' => 50,
            'payment_gateway' => 'Knet',
            'status' => 'pending',
        ]);
    }
}
