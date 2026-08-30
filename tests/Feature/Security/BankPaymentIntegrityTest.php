<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\AccountingController;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-3: AccountingController::storeBankPayment()
 * used to (a) interpolate the raw request 'amount' straight into
 * DB::raw() SQL with no validation at all, (b) apply BOTH balance updates
 * to the SAME bank account (so the bank always netted to zero and the
 * counterparty account was never touched), and (c) assign the *string*
 * 'required|integer' as the company_id value for admin users.
 *
 * NOTE: this method has no registered HTTP route on either prod or local
 * (verified via routes/web.php and view templates) -- it is presently
 * unreachable dead code. The fix is still applied because the method-level
 * bugs are real and the endpoint could be wired up at any time; these
 * tests call the controller method directly rather than through the HTTP
 * layer, since there is no route to hit.
 */
class BankPaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function makeAccount(int $companyId, string $name, float $balance = 1000.00): Account
    {
        return Account::create([
            'name' => $name,
            'level' => 4,
            'actual_balance' => $balance,
            'opening_balance' => $balance,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $companyId,
        ]);
    }

    public function test_amount_is_validated_and_not_interpolated_into_raw_sql(): void
    {
        $tenant = $this->createTenant();
        $bank = $this->makeAccount($tenant['company']->id, 'Test Bank', 1000.00);
        $counterparty = $this->makeAccount($tenant['company']->id, 'Test Supplier', 500.00);

        $this->actingAs($tenant['user']);

        // A classic SQL-injection payload in the 'amount' field. Before the
        // fix this was interpolated raw into DB::raw("actual_balance - {$request->amount}"),
        // giving an attacker a write primitive over the accounts table.
        // After the fix, 'amount' => 'required|numeric|min:0' rejects it
        // outright with a ValidationException before any query runs.
        $request = Request::create('/accounting/store-bank-payment', 'POST', [
            'transaction_date' => now()->toDateString(),
            'account_id' => $counterparty->id,
            'bank_account' => $bank->id,
            'branch_id' => $tenant['branch']->id,
            'description' => 'Injection attempt',
            'type' => 'bank_payment',
            'amount' => '0; DROP TABLE accounts;--',
        ]);
        $request->setUserResolver(fn () => $tenant['user']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->callStoreBankPaymentIgnoringDeadRedirect($request);
    }

    public function test_debits_bank_and_credits_the_actual_counterparty_account(): void
    {
        $tenant = $this->createTenant();
        $bank = $this->makeAccount($tenant['company']->id, 'Test Bank', 1000.00);
        $counterparty = $this->makeAccount($tenant['company']->id, 'Test Supplier', 500.00);

        $this->actingAs($tenant['user']);

        $request = Request::create('/accounting/store-bank-payment', 'POST', [
            'transaction_date' => now()->toDateString(),
            'account_id' => $counterparty->id,
            'bank_account' => $bank->id,
            'branch_id' => $tenant['branch']->id,
            'description' => 'Supplier settlement',
            'type' => 'bank_payment',
            'amount' => 150.00,
        ]);
        $request->setUserResolver(fn () => $tenant['user']);

        $this->callStoreBankPaymentIgnoringDeadRedirect($request);

        // Before the fix, BOTH updates targeted the bank account (decrement
        // then increment by the same amount), so the bank always netted to
        // zero and the counterparty was never touched at all.
        $this->assertSame(850.00, (float) $bank->fresh()->actual_balance);
        $this->assertSame(650.00, (float) $counterparty->fresh()->actual_balance);
    }

    public function test_company_id_is_a_real_id_not_the_validation_rule_string(): void
    {
        $tenant = $this->createTenant();
        $bank = $this->makeAccount($tenant['company']->id, 'Test Bank', 1000.00);
        $counterparty = $this->makeAccount($tenant['company']->id, 'Test Supplier', 500.00);

        $this->actingAs($tenant['user']);

        $request = Request::create('/accounting/store-bank-payment', 'POST', [
            'transaction_date' => now()->toDateString(),
            'account_id' => $counterparty->id,
            'bank_account' => $bank->id,
            'branch_id' => $tenant['branch']->id,
            'description' => 'Supplier settlement',
            'type' => 'bank_payment',
            'amount' => 25.00,
        ]);
        $request->setUserResolver(fn () => $tenant['user']);

        $this->callStoreBankPaymentIgnoringDeadRedirect($request);

        $entries = \App\Models\JournalEntry::where('description', 'like', 'Supplier settlement%')->get();
        $this->assertGreaterThan(0, $entries->count());

        foreach ($entries as $entry) {
            // Before the fix this column held the literal string
            // "required|integer" for admin users; it must always be the
            // real numeric company id.
            $this->assertSame($tenant['company']->id, $entry->company_id);
        }
    }

    /**
     * storeBankPayment() ends with redirect()->route('bank-payment.create'),
     * a route name that has never existed (the live route is the plural
     * 'bank-payments.create' on BankPaymentController, confirming this
     * method is unreachable dead code). That trailing RouteNotFoundException
     * is irrelevant to what these tests verify -- the balance/journal
     * effects, which happen inside the DB transaction before that final
     * redirect line -- so it is swallowed here rather than worked around by
     * changing the production method's unrelated dead branch.
     */
    private function callStoreBankPaymentIgnoringDeadRedirect(Request $request): void
    {
        try {
            app(AccountingController::class)->storeBankPayment($request);
        } catch (\Illuminate\Routing\Exceptions\UrlGenerationException|\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
        }
    }
}
