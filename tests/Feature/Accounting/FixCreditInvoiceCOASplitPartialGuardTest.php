<?php

namespace Tests\Feature\Accounting;

use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * W2c fix (orchestrator residual R-c). {@see \App\Console\Commands\FixCreditInvoiceCOA}'s design
 * call E4 guard originally lived only inside the private `createCreditPaymentCOA()` method,
 * which `fixSplitPartialInvoice()` never calls — that method hand-rolls the IDENTICAL
 * `Transaction`/`JournalEntry` event directly (`reference_type => 'Payment'`,
 * `description => "Credit Payment for {invoice_number}"`), so a split/partial invoice for an
 * engine-ON company was never refused (W2b lead report §5, R-c). Extended to the shared
 * `fixInvoice()` dispatcher entry — both the 'credit' and 'split_partial' categories route
 * through it — so both are now covered.
 */
class FixCreditInvoiceCOASplitPartialGuardTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_refuses_a_split_partial_invoice_for_an_engine_on_company(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];

        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->assertTrue($company->fresh()->posting_engine_enabled);

        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => 100.0,
            'sub_amount' => 100.0,
            'currency' => 'KWD',
            'payment_type' => 'partial',
        ]);

        InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $tenant['client']->id,
            'amount' => 50.0,
            'status' => 'paid',
            'expiry_date' => now()->toDateString(),
            'type' => 'partial',
            'payment_gateway' => 'Credit',
            'payment_method' => 'Credit Balance',
            'service_charge' => 0,
        ]);

        // Console-output-string assertions are unreliable in this suite: TestCase::setUp()'s
        // `$this->artisan('db:seed', ...)` permanently rebinds Illuminate\Console\OutputStyle to
        // a stale Mockery buffer for the rest of the test (Laravel's
        // InteractsWithConsole::mockConsoleOutput() is never unbound), so `Artisan::output()`
        // reads empty regardless of what a LATER `Artisan::call()` actually printed — see
        // EnsureSystemLeavesTest's own docblock for the same finding. DB state plus the
        // command's own per-invoice Log::error() call (handle()'s catch block) are the proof
        // here instead.
        Log::spy();

        $exitCode = Artisan::call('fix:credit-invoice-coa', [
            '--type' => 'partial',
            '--invoice' => $invoice->id,
            '--force' => true,
        ]);

        $this->assertSame(1, $exitCode, 'the guard exception must be caught per-invoice and counted as an error, not crash the whole command.');

        Log::shouldHaveReceived('error')->once()->with(
            '[FIX ALL CREDIT INVOICE COA] Error',
            Mockery::on(function (array $context) use ($invoice, $company) {
                return $context['invoice_id'] === $invoice->id
                    && $context['category'] === 'split_partial'
                    && str_contains($context['error'], 'posting engine')
                    && str_contains($context['error'], (string) $company->id);
            })
        );

        $this->assertSame(
            0,
            Transaction::where('invoice_id', $invoice->id)->count(),
            'fixSplitPartialInvoice() must never have run (and hand-rolled a Transaction) for an engine-ON company.'
        );
        $this->assertSame(0, JournalEntry::where('invoice_id', $invoice->id)->count());
    }

    /**
     * Sanity counterpart: the SAME split/partial invoice, for an engine-OFF company, must still
     * be fixed normally — proves the new guard did not become unconditional.
     */
    public function test_still_fixes_a_split_partial_invoice_for_an_engine_off_company(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $this->assertFalse((bool) $company->fresh()->posting_engine_enabled);

        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => 100.0,
            'sub_amount' => 100.0,
            'currency' => 'KWD',
            'payment_type' => 'partial',
        ]);

        InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $tenant['client']->id,
            'amount' => 50.0,
            'status' => 'paid',
            'expiry_date' => now()->toDateString(),
            'type' => 'partial',
            'payment_gateway' => 'Credit',
            'payment_method' => 'Credit Balance',
            'service_charge' => 0,
        ]);

        \App\Models\Account::create(['name' => 'Liabilities', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $liabilities = \App\Models\Account::where('name', 'Liabilities')->where('company_id', $company->id)->first();
        $advances = \App\Models\Account::create(['name' => 'Advances', 'level' => 2, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $liabilities->id]);
        $client = \App\Models\Account::create(['name' => 'Client', 'level' => 3, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $advances->id]);
        \App\Models\Account::create(['name' => 'Payment Gateway', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $client->id]);
        $accountsReceivable = \App\Models\Account::create(['name' => 'Accounts Receivable', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        \App\Models\Account::create(['name' => 'Clients', 'level' => 2, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $accountsReceivable->id]);

        Artisan::call('fix:credit-invoice-coa', [
            '--type' => 'partial',
            '--invoice' => $invoice->id,
            '--force' => true,
        ]);

        $this->assertSame(
            1,
            Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->count(),
            'the guard must not fire for an engine-OFF company — fixSplitPartialInvoice() must still run.'
        );
    }
}
