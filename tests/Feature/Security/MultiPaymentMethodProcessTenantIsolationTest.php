<?php

namespace Tests\Feature\Security;

use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the payment.link.store / multiPaymentMethodProcess IDOR (W41): same
 * shape as the credits.topup hole fixed in W40 (see CreditTopupTenantIsolationTest).
 * PaymentController::multiPaymentMethodProcess() validated `client_id` / `agent_id` with
 * 'exists:clients,id' / 'exists:agents,id' only -- proof the ids are *real* rows, not that they
 * belong to the caller's own company -- and the company_id written onto the resulting Payment row
 * was taken from $agent->branch->company->id, i.e. from the ATTACKER-SUPPLIED agent. Any
 * authenticated user of any company could post a client_id/agent_id pair belonging to a different
 * company via POST payment/link/store (payment_gateway omitted, payment_methods present -- see
 * PaymentController::paymentStoreLink()'s multi-method branch) and inject a real Payment row into
 * that company's books. The fix mirrors creditTopup(): derive the acting company from the
 * authenticated user (getCompanyId()) and require the supplied client and agent to both resolve
 * to that same company (or, for an admin with no company selected, to simply match each other),
 * aborting 403 otherwise. Only WHO may call it and WHICH company it may act for changed here --
 * no amount, account, or posting logic was touched.
 */
class MultiPaymentMethodProcessTenantIsolationTest extends TestCase
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
            'payment_methods' => [999999], // no real PaymentMethod needs to exist -- the tenant
            // check aborts before PaymentMethod::whereIn() is ever reached in the rejection cases.
            'currency' => 'KWD',
            'send_payment_receipt' => false,
            'amount' => 50,
            // payments.language is NOT NULL with no DB default; multiPaymentMethodProcess()
            // writes $request->language verbatim, so it must be supplied for Payment::create()
            // to succeed in the same-company happy-path test below.
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

    public function test_same_company_multi_payment_method_creation_still_works_unchanged(): void
    {
        $tenant = $this->createTenant();

        $paymentMethod = PaymentMethod::factory()->create([
            'company_id' => $tenant['company']->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($tenant['user'])
            ->post(route('payment.link.store'), $this->storePayload([
                'client_id' => $tenant['client']->id,
                'agent_id' => $tenant['agent']->id,
                'payment_methods' => [$paymentMethod->id],
            ]));

        $response->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'company_id' => $tenant['company']->id,
            'amount' => 50,
            'payment_gateway' => 'Multi',
            'status' => 'pending',
        ]);
    }
}
