<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\AgentSettlement;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for PaymentController::assignClientToImport(). `agent_id` (supplied on the
 * request for a still-unassigned imported payment) was validated only 'exists:agents,id' -- proof
 * the id is a real row, never that it belongs to the caller's own company -- and the client
 * allow-list used to constrain `client_id` was then built straight from that unchecked agent's
 * roster. A caller could supply another company's agent_id and be handed that company's own
 * client roster to reassign the payment into.
 *
 * The base Payment row lookup (`Payment::where('id', $paymentId)->where('is_imported', true)
 * ->whereNull('client_id')->firstOrFail()`) also carried no company scoping at all -- mirrors
 * paymentUpdateLink()'s identical base-row fix, and (as documented on
 * PaymentUpdateLinkTenantIsolationTest) needs a caller whose role bypasses Payment's own
 * BelongsToCompany global scope (Role::CLIENT) to prove it is the NEW explicit check doing the
 * work, not the pre-existing trait.
 */
class AssignClientToImportTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * `payments` carries a `chk_payment_owner` CHECK constraint (2026_04_01_155631 migration,
     * added for the unrelated agent-settlement feature): `client_id IS NOT NULL XOR
     * settlement_id IS NOT NULL`. A still-unassigned imported payment (client_id NULL -- the
     * exact row shape assignClientToImport()'s own base-row query selects on) can therefore only
     * exist in the DB at all with a real settlement_id attached, so the fixture needs one even
     * though assignClientToImport() itself never reads or writes that column. Pre-existing,
     * unrelated to this fix -- flagged here only because it constrains how this fixture must be
     * built, not because it needs fixing as part of this pass.
     */
    private function makeOrphanPayment(array $tenant, ?int $agentId = null): Payment
    {
        $settlement = AgentSettlement::create([
            'settlement_number' => 'STL-'.uniqid(),
            'agent_id' => $tenant['agent']->id,
            'branch_id' => $tenant['branch']->id,
            'company_id' => $tenant['company']->id,
            'total_amount' => 100,
            'remaining_amount' => 100,
            'created_by' => $tenant['user']->id,
        ]);

        // settlement_id is deliberately NOT in Payment::$fillable (app/Models/Payment.php) --
        // build the row in memory first (factory make(), not create()) so the CHECK constraint
        // sees BOTH columns together on the one INSERT save() below performs, forceFill()ing
        // settlement_id past the mass-assignment guard rather than a separate post-create
        // update() (which would need an already-invalid intermediate row to exist first).
        $payment = Payment::factory()->make([
            'company_id' => $tenant['company']->id,
            'client_id' => null,
            'agent_id' => $agentId,
            'is_imported' => true,
            'status' => 'completed',
            'account_id' => null,
            'invoice_id' => null,
            'created_by' => null,
        ]);
        $payment->forceFill(['settlement_id' => $settlement->id]);
        $payment->save();

        return $payment;
    }

    /** Same three-account chain addCredit() needs -- see
     * CreditTopupTenantIsolationTest::seedTopupAccounts() / ImportPaymentProcessTenantIsolationTest
     * for the identical requirement. */
    private function seedCreditAccounts(int $companyId): void
    {
        $liabilities = Account::create([
            'name' => 'Liabilities', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
        ]);
        $clientAdvance = Account::create([
            'name' => 'Client', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
            'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
        ]);
        Account::create([
            'name' => 'Payment Gateway', 'level' => 3, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
            'parent_id' => $clientAdvance->id, 'root_id' => $liabilities->id,
        ]);
    }

    public function test_cannot_assign_another_companys_agent_and_client_to_own_orphan_payment(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $payment = $this->makeOrphanPayment($tenantA);

        $response = $this->actingAs($tenantA['user'])
            ->post(route('payment.link.import.assign-client', $payment->id), [
                'agent_id' => $tenantB['agent']->id,
                'client_id' => $tenantB['client']->id,
            ]);

        $response->assertForbidden();

        $payment->refresh();
        $this->assertNull($payment->client_id);
        $this->assertNull($payment->agent_id);
        $this->assertTrue((bool) $payment->is_imported);
    }

    /**
     * The base-row-scoping half of the fix -- see class docblock for why this needs a
     * Role::CLIENT-style attacker rather than a normal company-scoped one.
     */
    public function test_cannot_reach_another_companys_orphan_payment_by_id(): void
    {
        $tenantB = $this->createTenant();
        $payment = $this->makeOrphanPayment($tenantB, $tenantB['agent']->id);

        $attacker = User::factory()->create(['role_id' => Role::CLIENT]);

        $response = $this->actingAs($attacker)
            ->post(route('payment.link.import.assign-client', $payment->id), [
                'client_id' => $tenantB['client']->id,
            ]);

        $response->assertNotFound();

        $payment->refresh();
        $this->assertNull($payment->client_id);
        $this->assertTrue((bool) $payment->is_imported);
    }

    /**
     * NOTE on what this test can and cannot prove: `payments` also carries a separate,
     * pre-existing `chk_payment_owner` CHECK constraint (see makeOrphanPayment()'s docblock)
     * that assignClientToImport()'s own `$payment->update(['client_id' => ..., ...])` call
     * never accounts for -- it writes client_id without ever clearing settlement_id, so for ANY
     * orphan payment built the only way the DB schema now allows (settlement_id set), that
     * update() violates the constraint regardless of tenant. This appears to be a genuine,
     * unrelated regression from the agent-settlement migration (2026_04_01_155631) landing
     * after this method was written -- confirmed independently of this fix (a minimal
     * same-company reproduction outside HTTP hits the identical QueryException) and out of
     * scope here per this pass's own constraint against changing posting/write behaviour.
     * What this test CAN and DOES prove: the tenant-isolation check itself (the actual subject
     * of this fix) does not reject a legitimate same-company agent/client pair -- no
     * AuthorizationException/403 is thrown; the request instead reaches, and fails on, that
     * unrelated pre-existing constraint.
     */
    public function test_same_company_assignment_is_not_blocked_by_the_tenant_check(): void
    {
        $tenant = $this->createTenant();
        $this->seedCreditAccounts($tenant['company']->id);
        $payment = $this->makeOrphanPayment($tenant);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($tenant['user'])
                ->post(route('payment.link.import.assign-client', $payment->id), [
                    'agent_id' => $tenant['agent']->id,
                    'client_id' => $tenant['client']->id,
                ]);

            // If the pre-existing constraint bug above ever gets fixed independently, a clean
            // success here is equally acceptable proof the tenant check did not block it.
            $payment->refresh();
            $this->assertSame($tenant['client']->id, $payment->client_id);
        } catch (\Illuminate\Auth\Access\AuthorizationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            $this->fail('Legitimate same-company assignment was rejected by the tenant-isolation check: '.$e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString(
                'chk_payment_owner',
                $e->getMessage(),
                'Expected only the known, unrelated chk_payment_owner constraint to block this -- got a different failure: '.$e->getMessage()
            );
        }
    }
}
