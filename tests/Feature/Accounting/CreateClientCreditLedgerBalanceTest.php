<?php

namespace Tests\Feature\Accounting;

use App\Console\Commands\CreateClientCredit;
use App\Enums\ChargeType;
use App\Models\Account;
use App\Models\Charge;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Migration proof for CreateClientCredit::processCredit(), one of the
 * "5 co-writer sites" call sites named in this build's task brief:
 *   - the bank/gateway asset account JournalEntry::create() call (debit-normal)
 *     previously wrote NO 'balance' value at all; it now carries the same
 *     ledger-derived value every other migrated writer of this account's
 *     balance uses.
 *   - the gateway expense account JournalEntry::create() call (debit-normal)
 *     previously read the hand-maintained actual_balance column, same fix
 *     pattern as ClientController::addCredit() and
 *     PaymentController's invoice-payment COA writer apply to their own
 *     gateway expense accounts.
 *
 * Both accounts are seeded with a deliberately WRONG actual_balance (0.00)
 * while a pre-existing journal_entries row gives each a real ledger balance
 * of 1234.567 -- a fils-precision value the legacy decimal(10,2)
 * actual_balance column could never hold, so the two numbers cannot collide
 * by accident.
 */
class CreateClientCreditLedgerBalanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_process_credit_writes_ledger_derived_balances_on_both_gateway_accounts(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];

        $assetsRoot = Account::create([
            'name' => 'Assets', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);
        $expensesRoot = Account::create([
            'name' => 'Expenses', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);

        // Deliberately wrong actual_balance (0.00) on both gateway accounts.
        $bankPaymentFee = Account::create([
            'name' => 'Gateway Bank CCC', 'level' => 2, 'actual_balance' => 0.00,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $assetsRoot->id, 'root_id' => $assetsRoot->id,
        ]);
        $bankCOAFee = Account::create([
            'name' => 'Gateway Fee CCC', 'level' => 2, 'actual_balance' => 0.00,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $expensesRoot->id, 'root_id' => $expensesRoot->id,
        ]);

        foreach ([$bankPaymentFee, $bankCOAFee] as $account) {
            DB::table('journal_entries')->insert([
                'name' => $account->name,
                'transaction_id' => null,
                'company_id' => $company->id,
                'account_id' => $account->id,
                'branch_id' => $tenant['branch']->id,
                'transaction_date' => now(),
                'description' => 'pre-existing ledger balance fixture',
                'debit' => 1234.567,
                'credit' => 0,
                'balance' => null,
                'voucher_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Deterministic accountingFee = 5.00 (Flat Rate, no percent rounding),
        // matched by BOTH the direct-name Charge lookup in processCredit()
        // and ChargeService::calculate()'s own fallback lookup.
        Charge::create([
            'name' => 'CreditGatewayLB',
            'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 5.00,
            'charge_type' => 'Flat Rate',
            'self_charge' => 5.00,
            'extra_charge' => 0,
            'paid_by' => 'Company',
            'company_id' => $company->id,
            'acc_fee_bank_id' => $bankPaymentFee->id,
            'acc_fee_id' => $bankCOAFee->id,
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_method_id' => null,
            'payment_gateway' => 'CreditGatewayLB',
            'voucher_number' => 'VOU-LEDGER-CCC-1',
            'status' => 'completed',
            'amount' => 100.00,
        ]);

        $result = app(CreateClientCredit::class)->processCredit($payment);

        $this->assertSame('success', $result['status'] ?? null, $result['message'] ?? 'processCredit failed unexpectedly');

        $accountingFee = 5.00;
        $netAssetAmount = 100.00 - $accountingFee;

        $assetEntry = JournalEntry::where('account_id', $bankPaymentFee->id)
            ->where('debit', 100.00)
            ->first();
        $expenseEntry = JournalEntry::where('account_id', $bankCOAFee->id)
            ->where('debit', $accountingFee)
            ->first();

        $this->assertNotNull($assetEntry, 'Expected the gateway asset debit journal entry to have been created.');
        $this->assertNotNull($expenseEntry, 'Expected the gateway expense debit journal entry to have been created.');

        // Ledger-derived (1234.567 + gross $payment->amount) — this call site
        // previously wrote NO 'balance' value at all. 'balance' must match
        // this entry's own 'debit' (gross 100.00), not the fee-netted
        // $netAssetAmount (95.00) — see the docblock at the JournalEntry::create()
        // call site in CreateClientCredit.php for why: 1234.567 + 100.00 = 1334.567.
        $this->assertEqualsWithDelta(1234.567 + $payment->amount, (float) $assetEntry->balance, 0.001);
        $this->assertNotEqualsWithDelta(1234.567 + $netAssetAmount, (float) $assetEntry->balance, 0.001);
        $this->assertNotEqualsWithDelta((float) $bankPaymentFee->actual_balance + $netAssetAmount, (float) $assetEntry->balance, 0.001);

        // Ledger-derived (1234.567 + accountingFee), NOT actual_balance-derived
        // (0.00 + accountingFee).
        $this->assertEqualsWithDelta(1234.567 + $accountingFee, (float) $expenseEntry->balance, 0.001);
        $this->assertNotEqualsWithDelta((float) $bankCOAFee->actual_balance + $accountingFee, (float) $expenseEntry->balance, 0.001);
    }

    /**
     * Regression proof (undoes a prior build's defect): a company-scoped
     * `Account::where('name', 'Clients')->first()` lookup was added to
     * processCredit() that threw a typed exception when no 'Clients' account
     * existed for the paying company -- but the resolved account id was
     * never written to any JournalEntry/Transaction/Credit row (its only
     * reader was a Log::info added for that lookup's own test), and the
     * throw fired inside the SECOND DB transaction, after the Credit row
     * had already been committed by the FIRST. Net effect: a company with no
     * literal 'Clients' account got a committed Credit with no journal
     * entries behind it.
     *
     * The lookup, the throw, and the Log::info have all been removed. What
     * this test pins is that a company with NO 'Clients' account at all now
     * gets the Credit row AND both gateway journal entries (asset debit +
     * fee expense debit) committed. Re-adding the 'Clients' lookup with its
     * throw turns this test red again.
     *
     * NOT A RESTORATION OF HEAD — measured, so nobody reasons from the wrong
     * premise. Commit 97f0256f6 does NOT behave this way either: HEAD's
     * `$receivableAccount = Account::where('name', 'Clients')->first();`
     * immediately reads `$receivableAccount->id`, which on null becomes an
     * ErrorException, is swallowed by processCredit()'s own
     * `catch (Exception $e)`, rolls the second transaction back and returns
     * `['status' => 'error']` — the same visible outcome as the defect
     * removed above. Running this exact test against
     * `git show HEAD:app/Console/Commands/CreateClientCredit.php` fails with
     * "Failed to add JournalEntry / -'success' +'error'". This test therefore
     * asserts behaviour that is strictly BETTER than HEAD's, and its red
     * output does not by itself distinguish the removed defect from HEAD.
     *
     * SEPARATE, STILL OPEN (pre-existing at HEAD, out of this test's scope):
     * name-based 'Clients' lookups are ambiguous in the first place — the COA
     * seeder creates two accounts named 'Clients' per company (one under
     * Accounts Receivable, one under Refund Payable). Any future receivable
     * resolution here must go through the purpose-code registry, not a name.
     */
    public function test_process_credit_commits_credit_and_journal_entries_without_a_clients_account(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];

        $assetsRoot = Account::create([
            'name' => 'Assets', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);
        $expensesRoot = Account::create([
            'name' => 'Expenses', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);

        $bankPaymentFee = Account::create([
            'name' => 'Gateway Bank NoClients', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $assetsRoot->id, 'root_id' => $assetsRoot->id,
        ]);
        $bankCOAFee = Account::create([
            'name' => 'Gateway Fee NoClients', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $expensesRoot->id, 'root_id' => $expensesRoot->id,
        ]);

        // Deliberately NO 'Clients' account for this company -- the exact
        // condition that used to throw SystemAccountNotFoundException.

        Charge::create([
            'name' => 'NoClientsGateway',
            'type' => ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 5.00,
            'charge_type' => 'Flat Rate',
            'self_charge' => 5.00,
            'extra_charge' => 0,
            'paid_by' => 'Company',
            'company_id' => $company->id,
            'acc_fee_bank_id' => $bankPaymentFee->id,
            'acc_fee_id' => $bankCOAFee->id,
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_method_id' => null,
            'payment_gateway' => 'NoClientsGateway',
            'voucher_number' => 'VOU-NOCLIENTS-1',
            'status' => 'completed',
            'amount' => 100.00,
        ]);

        $result = app(CreateClientCredit::class)->processCredit($payment);

        $this->assertSame('success', $result['status'] ?? null, $result['message'] ?? 'processCredit failed unexpectedly');

        $this->assertDatabaseHas('credits', [
            'client_id' => $tenant['client']->id,
            'payment_id' => $payment->id,
            'type' => 'Topup',
        ]);

        $accountingFee = 5.00;

        $assetEntry = JournalEntry::where('account_id', $bankPaymentFee->id)
            ->where('debit', 100.00)
            ->first();
        $expenseEntry = JournalEntry::where('account_id', $bankCOAFee->id)
            ->where('debit', $accountingFee)
            ->first();

        $this->assertNotNull($assetEntry, 'Expected the gateway asset debit journal entry to have been created even with no Clients account.');
        $this->assertNotNull($expenseEntry, 'Expected the gateway expense debit journal entry to have been created even with no Clients account.');
    }
}
