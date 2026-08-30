<?php

namespace Tests\Feature\Accounting;

use App\Console\Commands\FixCreditInvoiceCOA;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * KEY: pas-credit. Design call E4: FixCreditInvoiceCOA::createCreditPaymentCOA() (private,
 * NOT cut over to the posting engine — P5.17 retirement is tracked separately) must refuse to
 * run for a company whose posting_engine_enabled flag is true, rather than hand-rolling
 * Transaction/JournalEntry rows the engine now owns for that company. This is an entry guard
 * only — the method's own legacy body is otherwise untouched.
 */
class FixCreditInvoiceCOAPostingEngineGuardTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function invokePrivateCreateCreditPaymentCOA(Invoice $invoice): void
    {
        $command = new FixCreditInvoiceCOA();
        $reflection = new \ReflectionMethod($command, 'createCreditPaymentCOA');
        $reflection->setAccessible(true);
        $reflection->invoke($command, $invoice);
    }

    private function makeInvoice(array $tenant, float $amount = 100.0): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => $amount,
            'sub_amount' => $amount,
            'currency' => 'KWD',
        ]);
    }

    public function test_refuses_when_posting_engine_is_enabled_for_the_company(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $invoice = $this->makeInvoice($tenant);

        // Flip the per-company flag ON via the supported operator gesture — independent of
        // whether the company's chart of accounts (CoaSeeder) or system_accounts
        // (SystemAccountsSeeder) have been seeded at all; this guard must refuse regardless.
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->assertTrue($company->fresh()->posting_engine_enabled);

        try {
            $this->invokePrivateCreateCreditPaymentCOA($invoice);
            $this->fail('Expected an exception refusing to run against a posting-engine-enabled company.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('posting engine', $e->getMessage());
            $this->assertStringContainsString((string) $company->id, $e->getMessage());
        }

        // Nothing else was written — the guard fires before any Transaction/JournalEntry read
        // or write in the method's legacy body.
        $this->assertSame(0, Transaction::where('invoice_id', $invoice->id)->count());
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_runs_normally_when_posting_engine_is_disabled_for_the_company(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $invoice = $this->makeInvoice($tenant);
        $this->assertFalse((bool) $company->fresh()->posting_engine_enabled);

        // No legacy accounts seeded on purpose: proves the guard did NOT fire (which would throw
        // a "posting engine" message) by instead reaching the method's own pre-existing
        // "Required accounts not found" guard further down its legacy body.
        try {
            $this->invokePrivateCreateCreditPaymentCOA($invoice);
            $this->fail('Expected the legacy "Required accounts not found" exception.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Required accounts not found', $e->getMessage());
        }
    }

    public function test_runs_and_posts_when_company_has_no_posting_engine_row_state(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        $invoice = $this->makeInvoice($tenant, 100.0);

        // Legacy account hierarchy the method's own (untouched) body resolves by name.
        Account::create(['name' => 'Liabilities', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        $liabilities = Account::where('name', 'Liabilities')->where('company_id', $company->id)->first();
        $advances = Account::create(['name' => 'Advances', 'level' => 2, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $liabilities->id]);
        $client = Account::create(['name' => 'Client', 'level' => 3, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $advances->id]);
        Account::create(['name' => 'Payment Gateway', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $client->id]);
        $accountsReceivable = Account::create(['name' => 'Accounts Receivable', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id]);
        Account::create(['name' => 'Clients', 'level' => 2, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id, 'parent_id' => $accountsReceivable->id]);

        $this->invokePrivateCreateCreditPaymentCOA($invoice);

        $transaction = Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->first();
        $this->assertNotNull($transaction, 'the guard must not have fired for a company with the flag off');
    }
}
