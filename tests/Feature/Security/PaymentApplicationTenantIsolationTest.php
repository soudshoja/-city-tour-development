<?php

namespace Tests\Feature\Security;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-1: cross-tenant credit funds-transfer via the
 * payment-application endpoints (PaymentApplicationService and its three
 * InvoiceController wrappers). Proves company A can no longer draw down
 * company B's credit balance onto company A's invoice, nor read company
 * B's client/invoice payment data through these endpoints.
 */
class PaymentApplicationTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function createCreditFor(array $tenant, float $amount = 100.00): Credit
    {
        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'status' => 'completed',
        ]);

        return Credit::create([
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'client_id' => $tenant['client']->id,
            'payment_id' => $payment->id,
            'type' => Credit::TOPUP,
            'amount' => $amount,
            'description' => 'Topup credit for tenant isolation test',
        ]);
    }

    public function test_apply_payments_to_invoice_rejects_another_companys_credit(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);

        $creditB = $this->createCreditFor($tenantB, 100.00);

        $response = $this->actingAs($tenantA['user'])->postJson(route('invoice.apply-payments'), [
            'invoice_id' => $invoiceA->id,
            'payment_allocations' => [
                ['credit_id' => $creditB->id, 'amount' => 100.00],
            ],
            'payment_mode' => 'full',
        ]);

        // The service catches the authorization Exception and reports it as
        // a normal failed-application result (422), never a 200 success.
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        // Nothing was mutated: company B's credit balance is untouched and
        // no Credit/PaymentApplication row was created against company A's
        // invoice.
        $this->assertSame(100.00, (float) $creditB->fresh()->amount);
        $this->assertSame(0, Credit::where('invoice_id', $invoiceA->id)->count());
        $this->assertSame('unpaid', $invoiceA->fresh()->status);
    }

    public function test_apply_payments_to_invoice_rejects_another_companys_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceB = Invoice::factory()->create([
            'client_id' => $tenantB['client']->id,
            'agent_id' => $tenantB['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);

        $creditA = $this->createCreditFor($tenantA, 100.00);

        $response = $this->actingAs($tenantA['user'])->postJson(route('invoice.apply-payments'), [
            'invoice_id' => $invoiceB->id,
            'payment_allocations' => [
                ['credit_id' => $creditA->id, 'amount' => 100.00],
            ],
            'payment_mode' => 'full',
        ]);

        // The controller's own check (before the service is even called)
        // rejects this with a real 403.
        $response->assertForbidden();
        $this->assertSame(100.00, (float) $creditA->fresh()->amount);
    }

    public function test_apply_payments_to_invoice_succeeds_for_own_company_credit(): void
    {
        $tenantA = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);

        $creditA = $this->createCreditFor($tenantA, 100.00);

        $response = $this->actingAs($tenantA['user'])->postJson(route('invoice.apply-payments'), [
            'invoice_id' => $invoiceA->id,
            'payment_allocations' => [
                ['credit_id' => $creditA->id, 'amount' => 100.00],
            ],
            'payment_mode' => 'full',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        // Applying credit does not mutate the original TOPUP row; it books a
        // new, offsetting Credit::INVOICE row against the same payment_id,
        // so the available balance (the sum of that payment's Credit rows)
        // is what should net to zero, not the original row's own amount.
        $this->assertSame(0.0, (float) Credit::getAvailableBalanceByPayment($creditA->payment_id));
        $this->assertSame('paid', $invoiceA->fresh()->status);
    }

    public function test_get_available_payments_rejects_another_companys_client(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantA['user'])->postJson(route('invoice.available-payments'), [
            'client_id' => $tenantB['client']->id,
        ]);

        $response->assertForbidden();
    }

    public function test_get_invoice_payment_history_rejects_another_companys_invoice(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceB = Invoice::factory()->create([
            'client_id' => $tenantB['client']->id,
            'agent_id' => $tenantB['agent']->id,
        ]);

        $response = $this->actingAs($tenantA['user'])->getJson(route('invoice.payment-history', ['invoiceId' => $invoiceB->id]));

        $response->assertForbidden();
    }

    public function test_link_payments_to_invoice_partial_rejects_another_companys_payment(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $invoiceA = Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
            'amount' => 50.00,
            'sub_amount' => 50.00,
        ]);

        $paymentB = Payment::factory()->create([
            'company_id' => $tenantB['company']->id,
            'agent_id' => $tenantB['agent']->id,
            'client_id' => $tenantB['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenantB['user']->id,
            'status' => 'completed',
        ]);

        $service = new \App\Services\PaymentApplicationService();
        $invoicePartial = \App\Models\InvoicePartial::create([
            'invoice_id' => $invoiceA->id,
            'invoice_number' => $invoiceA->invoice_number,
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
            'amount' => 50.00,
            'status' => 'paid',
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'payment_method' => 'Credit Balance',
            'service_charge' => 0,
        ]);

        $this->actingAs($tenantA['user']);

        // Two layers now stop this: Payment's own BelongsToCompany global
        // scope (active because a user is authenticated) means
        // Payment::findOrFail() for company B's payment ID resolves nothing
        // at all from company A's session, and the abort_unless() this
        // hotfix added is the layer that still catches it when that scope
        // is bypassed (e.g. a queued job with no authenticated user). Either
        // way, no PaymentApplication/Credit row may be created against
        // company A's invoice from company B's payment.
        $threw = false;

        try {
            $service->linkPaymentsToInvoicePartial($invoiceA, $invoicePartial, [
                ['payment_id' => $paymentB->id, 'amount' => 50.00],
            ]);
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Linking another company\'s payment must fail, not silently succeed.');
        $this->assertSame(0, \App\Models\PaymentApplication::where('invoice_id', $invoiceA->id)->count());
        $this->assertSame(0, Credit::where('invoice_id', $invoiceA->id)->count());
    }

    /**
     * Regression coverage for the ACTOR direction of HF-1, distinct from
     * every test above (which all mismatch the invoice's company against
     * the credit/payment's company). Here the invoice and its credit
     * BOTH belong to company B — internally consistent with each other —
     * and only the ACTING USER belongs to a different company (A). Neither
     * Invoice nor Credit carries a BelongsToCompany global scope (only
     * Payment does), so PaymentApplicationService::applyPaymentsToInvoice()
     * is called directly here (bypassing InvoiceController's own separate
     * getCompanyId(Auth::user()) check on the invoice.apply-payments route)
     * to prove the SERVICE itself — not just one particular controller
     * wrapper — rejects an acting user who doesn't belong to the invoice's
     * company. This is exactly the check a prior "fix" deleted, wrongly
     * believing CreateBulkInvoicesJob needed it gone (it doesn't — see
     * PaymentApplicationService::applyPaymentsToInvoice()'s own comment).
     */
    public function test_apply_payments_to_invoice_rejects_when_acting_user_belongs_to_a_different_company_than_the_invoice(): void
    {
        $tenantA = $this->createTenant(); // attacker
        $tenantB = $this->createTenant(); // victim: owns both invoice AND credit

        $invoiceB = Invoice::factory()->create([
            'client_id' => $tenantB['client']->id,
            'agent_id' => $tenantB['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);

        $creditB = $this->createCreditFor($tenantB, 100.00);

        $this->actingAs($tenantA['user']);

        $service = new \App\Services\PaymentApplicationService();

        $threw = false;
        try {
            $service->applyPaymentsToInvoice($invoiceB->id, [
                ['credit_id' => $creditB->id, 'amount' => 100.00],
            ], 'full');
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue(
            $threw,
            'A user of company A must not be able to apply company B\'s own credit to company B\'s own invoice, even though both belong to company B.'
        );
        $this->assertSame(100.00, (float) $creditB->fresh()->amount);
        $this->assertSame('unpaid', $invoiceB->fresh()->status);
    }

    /**
     * Same ACTOR-direction gap as above, for linkPaymentsToInvoicePartial()
     * (the method savePartial() calls) — using a credit_id allocation
     * rather than payment_id specifically because Credit carries no
     * BelongsToCompany global scope (only Payment does), so this exercises
     * ONLY the restored abort_unless() actor check, not that unrelated scope.
     */
    public function test_link_payments_to_invoice_partial_rejects_when_acting_user_belongs_to_a_different_company_than_the_invoice(): void
    {
        $tenantA = $this->createTenant(); // attacker
        $tenantB = $this->createTenant(); // victim: owns invoice, partial, and credit

        $invoiceB = Invoice::factory()->create([
            'client_id' => $tenantB['client']->id,
            'agent_id' => $tenantB['agent']->id,
            'amount' => 50.00,
            'sub_amount' => 50.00,
        ]);

        $creditB = $this->createCreditFor($tenantB, 50.00);

        $invoicePartial = \App\Models\InvoicePartial::create([
            'invoice_id' => $invoiceB->id,
            'invoice_number' => $invoiceB->invoice_number,
            'client_id' => $tenantB['client']->id,
            'agent_id' => $tenantB['agent']->id,
            'amount' => 50.00,
            'status' => 'paid',
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'payment_method' => 'Credit Balance',
            'service_charge' => 0,
        ]);

        $this->actingAs($tenantA['user']);

        $service = new \App\Services\PaymentApplicationService();

        $threw = false;
        try {
            $service->linkPaymentsToInvoicePartial($invoiceB, $invoicePartial, [
                ['credit_id' => $creditB->id, 'amount' => 50.00],
            ]);
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue(
            $threw,
            'A user of company A must not be able to link company B\'s own credit to company B\'s own invoice partial.'
        );
        $this->assertSame(50.00, (float) $creditB->fresh()->amount);
        $this->assertSame(0, \App\Models\PaymentApplication::where('invoice_id', $invoiceB->id)->count());
        $this->assertSame(0, Credit::where('invoice_id', $invoiceB->id)->count());
    }
}
