<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\ClientController;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Co-writer migration proof for ClientController::addCredit()'s ENTRY 4 --
 * the write whose balance expression reads
 * `'balance' => $clientAdvancePaymentGatewayLedgerBalance + $clientCreditAmount`.
 * The "Client Advance / Payment Gateway" account it writes to is the SAME
 * account tree (Liabilities -> Client -> Payment Gateway) that
 * CheckMyFatoorahPayments::handle() writes to via
 * `'balance' => $paymentGatewayLedgerBalance + $payment->amount` -- see
 * tests/Feature/Accounting/CheckMyFatoorahPaymentsLedgerBalanceTest.php.
 * Before this build, the two co-writers used opposite legacy actual_balance
 * conventions for the identical operation (a credit to this account); after
 * this build both compute journal_entries.balance via
 * TrialBalanceService::getCurrentAccountBalance() and both apply the same
 * canonical `ledgerBalance + amount` formula for a credit-normal account.
 *
 * The account is seeded with a deliberately WRONG actual_balance (0.00)
 * while a pre-existing journal_entries row gives it a real ledger balance of
 * 1234.567 -- a fils-precision value the legacy decimal(10,2) actual_balance
 * column could never hold, so the two numbers cannot collide by accident. If
 * ClientController::addCredit()'s ENTRY 4 is reverted to read
 * ->actual_balance instead of the ledger-derived value, the written
 * journal_entries.balance drops to
 * 0.00 + $payment->amount and this test goes red -- verified by an actual
 * revert/run/restore cycle (see PROOF in the task response).
 */
class ClientControllerAddCreditLedgerBalanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_client_advance_journal_entry_carries_the_ledger_derived_balance_not_actual_balance(): void
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
            'payment_method_id' => null,
            // Deliberately unmatched by any Charge/PaymentMethod row so
            // ChargeService::calculate() returns a clean 0 accountingFee and
            // clientCreditAmount lands exactly on $payment->amount.
            'payment_gateway' => 'NoMatchingChargeGateway',
            'voucher_number' => 'VOU-LEDGER-CC-1',
            'status' => 'completed',
            'amount' => 100.00,
        ]);

        $result = app(ClientController::class)->addCredit($payment);

        $this->assertSame('success', $result['status'] ?? null, $result['message'] ?? 'addCredit failed unexpectedly');

        $entry = JournalEntry::where('account_id', $paymentGateway->id)
            ->where('debit', 0)
            ->where('credit', 100.00)
            ->first();

        $this->assertNotNull($entry, 'Expected the Payment Gateway credit journal entry to have been created.');

        // Ledger-derived (1234.567 + amount, credit-normal), NOT
        // actual_balance-derived (0.00 + amount) -- see
        // ClientController::addCredit()'s ENTRY 4.
        $this->assertEqualsWithDelta(1234.567 + 100.00, (float) $entry->balance, 0.001);
        $this->assertNotEqualsWithDelta(0.00 + 100.00, (float) $entry->balance, 0.001);
    }
}
