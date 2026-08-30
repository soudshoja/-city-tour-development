<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\PaymentController;
use App\Models\Account;
use App\Models\Charge;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Verifier finding B: proves the ledger-derived value actually LANDS in
 * journal_entries.balance at PaymentController::createInvoicePaymentCOA()'s
 * two migrated sites -- the $gatewayAssetLedgerBalance read and its
 * `'balance' => $gatewayAssetLedgerBalance + $netAmount` write, and the
 * $gatewayExpenseLedgerBalance read and its
 * `'balance' => $gatewayExpenseLedgerBalance + $accountingFee` write -- by
 * exercising that real private method (same technique as
 * tests/Feature/Security/PaymentControllerHotfixTest.php's HF-6 test)
 * rather than re-deriving the arithmetic in isolation.
 *
 * Each gateway account is seeded with a deliberately WRONG actual_balance
 * (0.00) while a pre-existing journal_entries row gives it a real ledger
 * balance of 1234.567 -- a fils-precision value the legacy decimal(10,2)
 * actual_balance column could never hold, so the two numbers cannot collide
 * by accident. If either call site is reverted to read
 * ->actual_balance instead of TrialBalanceService::getCurrentAccountBalance(),
 * the written journal_entries.balance drops to 0.00 + the delta and this
 * test goes red -- verified by an actual revert/run/restore cycle, not
 * inferred (see PROOF in the task response).
 */
class LedgerDerivedBalanceCallSitesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function invokePrivate(object $object, string $method, array $args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    public function test_gateway_asset_and_gateway_expense_journal_entries_carry_the_ledger_derived_balance_not_actual_balance(): void
    {
        $tenant = $this->createTenant();
        $company = $tenant['company'];

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => 100.00,
            'sub_amount' => 100.00,
        ]);
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $tenant['agent']->id,
        ]);
        InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
        ]);

        // Real root accounts so TrialBalanceService::getCurrentAccountBalance()
        // can classify the two gateway leaves below as debit-normal (Assets /
        // Expenses) — a flat, parentless account name it doesn't recognize
        // would otherwise fall through to the credit-normal branch and
        // silently invert every assertion below.
        $assetsRoot = Account::create([
            'name' => 'Assets', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);
        $expensesRoot = Account::create([
            'name' => 'Expenses', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);

        // Deliberately wrong actual_balance (0.00) on both gateway accounts.
        // The pre-existing journal entry below gives each a true ledger
        // balance of 1234.567 -- a fils value decimal(10,2) actual_balance
        // could never have held, so a reverted call site cannot accidentally
        // produce the same number.
        $gatewayAsset = Account::create([
            'name' => 'Gateway Asset LB', 'level' => 2, 'actual_balance' => 0.00,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $assetsRoot->id, 'root_id' => $assetsRoot->id,
        ]);
        $gatewayExpense = Account::create([
            'name' => 'Gateway Expense LB', 'level' => 2, 'actual_balance' => 0.00,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
            'parent_id' => $expensesRoot->id, 'root_id' => $expensesRoot->id,
        ]);
        $clientsAccount = Account::create([
            'name' => 'Clients', 'level' => 4, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $company->id,
        ]);

        foreach ([$gatewayAsset, $gatewayExpense] as $account) {
            \Illuminate\Support\Facades\DB::table('journal_entries')->insert([
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

        // Charge configured so ChargeService::calculate() returns a non-zero
        // accountingFee (Flat Rate self_charge = accounting fee = charge
        // amount here), so netAmount != finalPaidAmount and both journal
        // entries below are meaningfully distinct.
        Charge::create([
            'name' => 'MyFatoorah',
            'type' => \App\Enums\ChargeType::PAYMENT_GATEWAY->value,
            'amount' => 5.00,
            'charge_type' => 'Flat Rate',
            'self_charge' => 5.00,
            'extra_charge' => 0,
            'paid_by' => 'Company',
            'company_id' => $company->id,
            'acc_fee_bank_id' => $gatewayAsset->id,
            'acc_fee_id' => $gatewayExpense->id,
        ]);

        // Deliberately no payment_method_id: ChargeService::calculate() would
        // otherwise prefer PaymentMethod::service_charge/self_charge (random
        // per PaymentMethodFactory) over the deterministic Charge row above.
        // Falling through to the Charge-table lookup keeps accountingFee
        // fixed at 5.00 so the expected balance is exact, not just "not
        // equal to actual_balance-derived".
        $payment = Payment::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_method_id' => null,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        $controller = app(PaymentController::class);

        $result = $this->invokePrivate($controller, 'createInvoicePaymentCOA', [
            $payment, 100.00, 'MyFatoorah', null, 'REF-LEDGER-1',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'createInvoicePaymentCOA failed unexpectedly');

        $accountingFee = 5.00;
        $netAmount = 100.00 - $accountingFee;

        $assetEntry = JournalEntry::where('transaction_id', $result['transaction_id'])
            ->where('account_id', $gatewayAsset->id)
            ->first();
        $expenseEntry = JournalEntry::where('transaction_id', $result['transaction_id'])
            ->where('account_id', $gatewayExpense->id)
            ->first();

        $this->assertNotNull($assetEntry);
        $this->assertNotNull($expenseEntry);

        // Ledger-derived (1234.567 + netAmount), NOT actual_balance-derived
        // (0.00 + netAmount) -- PaymentController::createInvoicePaymentCOA(),
        // `'balance' => $gatewayAssetLedgerBalance + $netAmount`.
        $this->assertEqualsWithDelta(1234.567 + $netAmount, (float) $assetEntry->balance, 0.001);
        $this->assertNotEqualsWithDelta((float) $gatewayAsset->actual_balance + $netAmount, (float) $assetEntry->balance, 0.001);

        // Ledger-derived (1234.567 + accountingFee), NOT actual_balance-derived
        // (0.00 + accountingFee) -- PaymentController::createInvoicePaymentCOA(),
        // `'balance' => $gatewayExpenseLedgerBalance + $accountingFee`.
        $this->assertEqualsWithDelta(1234.567 + $accountingFee, (float) $expenseEntry->balance, 0.001);
        $this->assertNotEqualsWithDelta((float) $gatewayExpense->actual_balance + $accountingFee, (float) $expenseEntry->balance, 0.001);
    }
}
