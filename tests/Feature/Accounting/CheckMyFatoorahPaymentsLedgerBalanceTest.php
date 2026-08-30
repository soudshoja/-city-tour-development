<?php

namespace Tests\Feature\Accounting;

use App\Console\Commands\CheckMyFatoorahPayments;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Verifier finding B: proves the ledger-derived value actually LANDS in
 * journal_entries.balance at the `'balance' => $paymentGatewayLedgerBalance +
 * $payment->amount` JournalEntry::create() call in
 * CheckMyFatoorahPayments::handle(), by running the real command handle()
 * end-to-end (Http::fake() stands in for
 * MyFatoorah's getPaymentStatus API) rather than re-deriving the arithmetic
 * in isolation.
 *
 * The "Payment Gateway" account is seeded with a deliberately WRONG
 * actual_balance (0.00) while a pre-existing journal_entries row gives it a
 * real ledger balance of 1234.567 -- a fils-precision value the legacy
 * decimal(10,2) actual_balance column could never hold, so the two numbers
 * cannot collide by accident.
 *
 * "Payment Gateway" sits under Liabilities -> Client -> Payment Gateway, a
 * CREDIT-normal account: TrialBalanceService::getCurrentAccountBalance()
 * derives this from the account's own root ('Liabilities'), the same rule
 * CoaController.php's $rootConfig and JournalEntryController's
 * running-balance switch use independently. A credit to a credit-normal
 * account INCREASES its balance, so the correct written value is
 * `ledgerBalance + $payment->amount`, not `- $payment->amount` (the sign
 * this test used to assert before this account was correctly classified —
 * see git history). If that JournalEntry::create() call in
 * CheckMyFatoorahPayments::handle() is reverted to read ->actual_balance, or
 * the arithmetic is reverted to `-`, this test goes red -- verified by an
 * actual revert/run/restore cycle (see PROOF in the task response).
 */
class CheckMyFatoorahPaymentsLedgerBalanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_payment_gateway_journal_entry_carries_the_ledger_derived_balance_not_actual_balance(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];

        $liabilitiesAccount = Account::create([
            'name' => 'Liabilities', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);
        $clientAdvance = Account::create([
            'name' => 'Client', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $liabilitiesAccount->id,
            'root_id' => $liabilitiesAccount->id,
        ]);
        // Deliberately wrong actual_balance (0.00). The pre-existing journal
        // entry below gives it a true ledger balance of 1234.567 -- a fils
        // value decimal(10,2) actual_balance could never have held.
        // root_id points at the TOP-level root (Liabilities), not the
        // immediate parent (Client) -- matches AccountService::resolveRoot()'s
        // convention, which TrialBalanceService::getCurrentAccountBalance()
        // relies on to classify this account as credit-normal.
        $paymentGateway = Account::create([
            'name' => 'Payment Gateway', 'level' => 3, 'actual_balance' => 0.00,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $clientAdvance->id,
            'root_id' => $liabilitiesAccount->id,
        ]);

        DB::table('journal_entries')->insert([
            'name' => $paymentGateway->name,
            'transaction_id' => null,
            'company_id' => $company->id,
            'account_id' => $paymentGateway->id,
            'branch_id' => $tenant['branch']->id,
            'transaction_date' => now(),
            'description' => 'pre-existing ledger balance fixture',
            'debit' => 0,
            'credit' => 1234.567,
            'balance' => null,
            'voucher_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'MyFatoorah',
            'payment_reference' => 'MF-INV-LEDGER-1',
            'status' => 'initiate',
            'amount' => 100.00,
        ]);

        Http::fake([
            '*/getPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceStatus' => 'Paid',
                    'InvoiceValue' => 100.00,
                    'InvoiceId' => 999,
                    'InvoiceReference' => 'MF-REF-LEDGER-1',
                    'InvoiceTransactions' => [['AuthorizationId' => 'AUTH-LEDGER-1']],
                    'UserDefinedField' => json_encode(['process' => 'invoice']),
                ],
            ], 200),
        ]);

        $exitCode = $this->artisan('app:myfatoorah-check-status', ['invoiceId' => 'MF-INV-LEDGER-1'])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame('completed', $payment->fresh()->status);

        $entry = JournalEntry::where('account_id', $paymentGateway->id)
            ->where('debit', 0)
            ->where('credit', 100.00)
            ->first();

        $this->assertNotNull($entry, 'Expected the Payment Gateway credit journal entry to have been created.');

        // Ledger-derived (1234.567 + amount, credit-normal), NOT
        // actual_balance-derived (0.00 - amount) and NOT the pre-fix
        // debit-normal-assumed sign (1234.567 - amount) -- see the
        // `'balance' => $paymentGatewayLedgerBalance + $payment->amount` line
        // in CheckMyFatoorahPayments::handle().
        $this->assertEqualsWithDelta(1234.567 + 100.00, (float) $entry->balance, 0.001);
        $this->assertNotEqualsWithDelta(0.00 - 100.00, (float) $entry->balance, 0.001);
        $this->assertNotEqualsWithDelta(1234.567 - 100.00, (float) $entry->balance, 0.001);
    }
}
