<?php

namespace Tests\Feature\Accounting;

use App\Models\Credit;
use App\Models\Payment;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Representative test for the shared RefusesWhenPostingEngineEnabled trait
 * (app/Console/Concerns/RefusesWhenPostingEngineEnabled.php) -- the same guard
 * FixCreditInvoiceCOA::fixInvoice()/createCreditPaymentCOA() established first
 * (see FixCreditInvoiceCOAPostingEngineGuardTest.php), extracted so every OTHER
 * legacy maintenance/backfill command in this family (FixProfitAndCommission,
 * FixPaymentLinkCOA, FixOldProfit, FixInvoiceCoa, FixGatewayCharges,
 * FixPaymentGatewayCOA, CreateClientCredit, UpdateOldTaskToTransaction,
 * RunAutoBilling) enforces the identical rule via the trait instead of
 * re-deriving it per file.
 *
 * This test drives `create:client-credit` end-to-end (real Artisan::call(), real
 * console output) as the ONE representative command for the sweep -- proving,
 * in one place, that the trait's contract holds: an engine-ON company is
 * skipped with a loud, company-named warning line and writes nothing, while an
 * engine-OFF company in the SAME run is processed unchanged.
 */
class RefusesWhenPostingEngineEnabledGuardTest extends TestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_engine_on_company_is_skipped_with_warning_while_engine_off_company_is_processed(): void
    {
        $tenantOn = $this->createTenant();
        $tenantOff = $this->createTenant();

        $companyOn = $tenantOn['company'];
        $companyOff = $tenantOff['company'];

        // Flip the per-company flag ON directly on the column the guard reads
        // (Company::posting_engine_enabled) -- the same flag
        // FixCreditInvoiceCOAPostingEngineGuardTest exercises via the
        // `accounting:engine` command; setting it directly here is equivalent
        // and keeps this test focused on the guard, not that command.
        $companyOn->update(['posting_engine_enabled' => true]);
        $companyOff->update(['posting_engine_enabled' => false]);

        $paymentOn = Payment::factory()->create([
            'agent_id' => $tenantOn['agent']->id,
            'client_id' => $tenantOn['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenantOn['user']->id,
            'payment_method_id' => null,
            'payment_gateway' => 'GuardTestGatewayOn',
            'voucher_number' => 'VOU-GUARD-ON-1',
            'status' => 'completed',
            'amount' => 40.00,
        ]);

        $paymentOff = Payment::factory()->create([
            'agent_id' => $tenantOff['agent']->id,
            'client_id' => $tenantOff['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenantOff['user']->id,
            'payment_method_id' => null,
            'payment_gateway' => 'GuardTestGatewayOff',
            'voucher_number' => 'VOU-GUARD-OFF-1',
            'status' => 'completed',
            'amount' => 40.00,
        ]);

        $exitCode = Artisan::call('create:client-credit', ['--proceed' => true]);
        $output = Artisan::output();

        // ON-company: skipped, with a loud warning naming the company and why.
        $this->assertSame(0, Credit::where('payment_id', $paymentOn->id)->count(), 'Engine-ON company must NOT have a Credit row written for it.');
        $this->assertStringContainsString('posting engine is enabled', $output, 'A loud warning must be emitted for the skipped engine-ON company.');
        $this->assertStringContainsString((string) $companyOn->id, $output, 'The warning must name the skipped company.');

        // OFF-company: processed unchanged, in the SAME run.
        $this->assertSame(1, Credit::where('payment_id', $paymentOff->id)->count(), 'Engine-OFF company must still be processed normally.');

        // At least one company was processed, so this is NOT the "processed
        // nothing due to refusals" case -- exit code must stay success.
        $this->assertSame(0, $exitCode, 'A run that processed at least one engine-OFF company must exit 0, even though another company was refused.');
    }

    public function test_exits_non_zero_when_every_candidate_is_refused(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $company->update(['posting_engine_enabled' => true]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_method_id' => null,
            'payment_gateway' => 'GuardTestGatewayAllOn',
            'voucher_number' => 'VOU-GUARD-ALL-ON-1',
            'status' => 'completed',
            'amount' => 40.00,
        ]);

        $exitCode = Artisan::call('create:client-credit', ['--proceed' => true]);

        $this->assertSame(0, Credit::where('payment_id', $payment->id)->count());
        // Every candidate this run considered was refused -- the command
        // processed literally nothing, so the exit code must be non-zero.
        $this->assertSame(1, $exitCode);
    }
}
