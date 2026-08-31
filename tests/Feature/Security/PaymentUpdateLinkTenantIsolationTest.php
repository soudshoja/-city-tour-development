<?php

namespace Tests\Feature\Security;

use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the WORST hole of this pass:
 * PaymentController::paymentUpdateLink() ran `Payment::find($paymentId)` with NO company
 * scoping at all, then overwrote client_id/agent_id/amount straight from request input with no
 * exists: validation and no company check whatsoever -- any authenticated user could load and
 * mutate ANY company's payment row by guessing an integer id.
 *
 * Note on Payment's own `BelongsToCompany` global scope (app/Traits/BelongsToCompany.php): it
 * already filters every Payment query to `getCompanyId(Auth::user())`, but ONLY for roles that
 * helper resolves a company for (Admin/Company/Branch/Agent/Accountant) -- for any other
 * authenticated role (e.g. Role::CLIENT, which getCompanyId()'s switch has no case for) it falls
 * through to `default: return null`, and the trait's own `if ($id !== null)` guard then adds NO
 * filter at all, silently reopening the exact hole this fix closes. The
 * "another company's payment id" tests below therefore act as a Role::CLIENT caller specifically,
 * so they exercise the NEW explicit check in paymentUpdateLink() rather than accidentally passing
 * only because the pre-existing trait already covered a Role::COMPANY-style attacker.
 *
 * The fix: (a) scope the base Payment lookup itself to the caller's own company (or, for an
 * unscoped admin, no filter -- mirrors assertSameCompanyOrUnscopedAdmin()'s own carve-out), (b)
 * validate client_id/agent_id/amount with exists:/numeric rules, (c) require any client/agent
 * supplied to belong to the SAME company as the (now company-scoped) payment.
 */
class PaymentUpdateLinkTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function makePayment(int $companyId, int $clientId, int $agentId, float $amount = 100): Payment
    {
        return Payment::factory()->create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'agent_id' => $agentId,
            'amount' => $amount,
            'status' => 'pending',
            'account_id' => null,
            'invoice_id' => null,
            'created_by' => null,
        ]);
    }

    public function test_cannot_reassign_own_payment_to_another_companys_client(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $payment = $this->makePayment($tenantA['company']->id, $tenantA['client']->id, $tenantA['agent']->id);

        $response = $this->actingAs($tenantA['user'])
            ->put(route('payment.link.update', $payment->id), [
                'client_id' => $tenantB['client']->id,
            ]);

        $response->assertForbidden();
        $this->assertSame($tenantA['client']->id, $payment->fresh()->client_id);
    }

    public function test_cannot_reassign_own_payment_to_another_companys_agent(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $payment = $this->makePayment($tenantA['company']->id, $tenantA['client']->id, $tenantA['agent']->id);

        $response = $this->actingAs($tenantA['user'])
            ->put(route('payment.link.update', $payment->id), [
                'agent_id' => $tenantB['agent']->id,
            ]);

        $response->assertForbidden();
        $this->assertSame($tenantA['agent']->id, $payment->fresh()->agent_id);
    }

    /**
     * The base-row-scoping half of the fix: a caller whose role bypasses Payment's own
     * BelongsToCompany global scope (see class docblock) must still be unable to reach another
     * company's payment row at all, let alone mutate it.
     */
    public function test_cannot_load_another_companys_payment_by_id(): void
    {
        $tenantB = $this->createTenant();
        $payment = $this->makePayment($tenantB['company']->id, $tenantB['client']->id, $tenantB['agent']->id, 250);

        $attacker = User::factory()->create(['role_id' => Role::CLIENT]);

        $response = $this->actingAs($attacker)
            ->put(route('payment.link.update', $payment->id), [
                'amount' => 999,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Payment not found.');
        $this->assertEqualsWithDelta(250.0, (float) $payment->fresh()->amount, 0.001);
    }

    public function test_same_company_update_still_works_unchanged(): void
    {
        $tenant = $this->createTenant();
        $payment = $this->makePayment($tenant['company']->id, $tenant['client']->id, $tenant['agent']->id);

        $response = $this->actingAs($tenant['user'])
            ->put(route('payment.link.update', $payment->id), [
                'client_id' => $tenant['client']->id,
                'agent_id' => $tenant['agent']->id,
                'amount' => 250,
                'phone' => '99999999',
            ]);

        $response->assertRedirect(route('payment.link.index'));
        $response->assertSessionHas('success');

        $payment->refresh();
        $this->assertEqualsWithDelta(250.0, (float) $payment->amount, 0.001);
        $this->assertSame($tenant['client']->id, $payment->client_id);
        $this->assertSame($tenant['agent']->id, $payment->agent_id);
    }
}
