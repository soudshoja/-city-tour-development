<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Credit;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for PaymentController::importPaymentProcess(). Reachable three ways --
 * PaymentController::importFromInvoice() (after a real gateway lookup), and directly via POST
 * payment/link/store with `source=import` (both paymentStoreLink()'s own top-of-method branch
 * and paymentStoreLinkProcess()'s identical one immediately forward here) -- all three share this
 * ONE method, so a single fix here closes all three doors.
 *
 * `$companyId = getCompanyId(Auth::user())` was already correctly the CALLER's own company, but
 * `client_id`/`agent_id` themselves were never checked against it -- an authenticated user could
 * import a gateway payment (written straight to status: 'completed', no approval step) against
 * another company's client/agent, landing real money in the wrong tenant's books.
 *
 * The fix requires the resolved client and agent to both belong to $companyId, aborting 403
 * otherwise -- no "unscoped admin" branch needed since $companyId here is already the verified
 * caller company, not an attacker-supplied value.
 */
class ImportPaymentProcessTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * The Liabilities -> Client -> Payment Gateway leg addCredit() (called at the end of a
     * successful import) needs to post its CREDIT Liability journal entry -- same three-account
     * chain CreditTopupTenantIsolationTest::seedTopupAccounts() builds for the identical
     * requirement in creditTopup().
     */
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

    private function importPayload(array $overrides = []): array
    {
        return array_merge([
            'source' => 'import',
            // Deliberately NOT MyFatoorah/Hesabe/Tap -- those three branches read a
            // gateway-specific session payload (session()->pull('fatoorah_import') etc.) that a
            // direct HTTP attack payload never populates; a plain gateway name skips all three
            // and isolates this test to the tenant-isolation check under test.
            'payment_gateway' => 'Knet',
            'amount' => 50,
        ], $overrides);
    }

    public function test_cannot_import_a_payment_for_a_client_and_agent_both_belonging_to_another_company(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantA['user'])
            ->post(route('payment.link.store'), $this->importPayload([
                'client_id' => $tenantB['client']->id,
                'agent_id' => $tenantB['agent']->id,
            ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cannot_import_a_payment_for_own_client_paired_with_another_companys_agent(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantA['user'])
            ->post(route('payment.link.store'), $this->importPayload([
                'client_id' => $tenantA['client']->id,
                'agent_id' => $tenantB['agent']->id,
            ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_same_company_import_still_works_and_lands_in_the_callers_own_company(): void
    {
        $tenant = $this->createTenant();
        $this->seedCreditAccounts($tenant['company']->id);

        $response = $this->actingAs($tenant['user'])
            ->post(route('payment.link.store'), $this->importPayload([
                'client_id' => $tenant['client']->id,
                'agent_id' => $tenant['agent']->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment = Payment::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame($tenant['company']->id, $payment->company_id);
        $this->assertSame($tenant['client']->id, $payment->client_id);
        $this->assertSame($tenant['agent']->id, $payment->agent_id);
        $this->assertSame('completed', $payment->status);

        $this->assertDatabaseHas('credits', [
            'client_id' => $tenant['client']->id,
            'company_id' => $tenant['company']->id,
            'type' => Credit::TOPUP,
        ]);
    }
}
