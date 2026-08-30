<?php

namespace Tests\Unit\Services;

use App\Exceptions\Accounting\CrossTenantAccountException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Coverage for TrialBalanceService::getCurrentAccountBalance() — the
 * ledger-derived CURRENT BALANCE (opening_balance + movement, signed in the
 * account's own normal direction) that replaces the hand-maintained
 * accounts.actual_balance decimal(10,2) column at every migrated call site:
 *   - CheckMyFatoorahPayments::handle()'s Payment Gateway JournalEntry write
 *   - PaymentController's invoice-payment COA writer (gateway asset + gateway
 *     expense JournalEntry writes)
 *   - ClientController::addCredit()'s gateway asset, gateway expense, and
 *     Client Advance JournalEntry writes
 *   - CreateClientCredit::processCredit()'s gateway asset and gateway expense
 *     JournalEntry writes
 *
 * This is the real test file/class for this method — do not confuse it with
 * "AccountingP1LedgerBalanceTest", a name that appeared in an earlier
 * docblock but never existed as an actual file.
 *
 * These tests insert journal_entries rows directly via DB::table()->insert()
 * (mirroring tests/Unit/Services/Accounting/AccountingInvariantsTest.php's
 * insertRawJournalEntry fixture) rather than going through PostingService,
 * because the point under test is the read side, not the posting engine.
 *
 * IMPORTANT fixture note: the method now derives an account's debit/credit
 * normal side FROM THE ACCOUNT'S OWN ROOT (its `root_id` for a non-root
 * account, or its own name if it IS a root — see
 * AccountService::resolveRoot()'s convention, which the accessor's private
 * resolveAccountNormalSide() mirrors). A bare Account::factory()->create()
 * (random name, parent_id = null) is therefore classified as its OWN root
 * with a random name, which is NOT 'Assets'/'Expenses' — i.e. it reads as
 * CREDIT-normal by default. Tests below that need a specific debit/credit
 * side build an explicit root (named 'Assets', 'Expenses', or 'Liabilities')
 * rather than relying on a bare factory account, so the assertion is about
 * the accessor's arithmetic, not an accident of which side the fallback
 * classification happens to land on.
 */
class TrialBalanceServiceLedgerBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /** A level-1 root account (parent_id = null), named so it is recognizable to getNormalBalance()'s rule. */
    private function makeRoot(Company $company, string $name): Account
    {
        return Account::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'parent_id' => null,
            'root_id' => null, // AccountFactory's afterCreating hook backfills this to its own id.
            'level' => 1,
        ]);
    }

    /** A leaf under $root, with root_id correctly pointing at the TOP-level root (not the immediate parent). */
    private function makeChild(Company $company, Account $root, string $name, array $extra = []): Account
    {
        return Account::factory()->create(array_merge([
            'company_id' => $company->id,
            'name' => $name,
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'level' => $root->level + 1,
        ], $extra));
    }

    private function insertJournalEntry(Company $company, Branch $branch, Account $account, float $debit, float $credit): void
    {
        DB::table('journal_entries')->insert([
            'name' => $account->name,
            'transaction_id' => null,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'transaction_date' => now(),
            'description' => 'TrialBalanceServiceLedgerBalanceTest fixture line',
            'debit' => $debit,
            'credit' => $credit,
            'balance' => null,
            'voucher_number' => null,
            'currency' => 'KWD',
            'exchange_rate' => 1.0,
            'amount' => max($debit, $credit),
            'reconciled' => 0,
            'original_currency' => 'KWD',
            'original_amount' => max($debit, $credit),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The bug this build fixes: a Liability (credit-normal) account, given an
     * opening_balance of 100.000 and a single credit of 1234.567, must read
     * 1334.567 (opening + credit, since a credit INCREASES a credit-normal
     * account) — never -1334.567 (the old default-to-debit-normal sign
     * inversion) and never 1234.567 (the old opening_balance omission).
     * Mirrors the real Liabilities -> Client -> Payment Gateway tree used by
     * CheckMyFatoorahPayments.php and ClientController.php.
     */
    public function test_liability_account_balance_is_opening_plus_credit_movement(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);

        $liabilities = $this->makeRoot($company, 'Liabilities');
        $client = $this->makeChild($company, $liabilities, 'Client');
        $paymentGateway = $this->makeChild($company, $liabilities, 'Payment Gateway', [
            'parent_id' => $client->id,
            'opening_balance' => 100.000,
        ]);

        $this->insertJournalEntry($company, $branch, $paymentGateway, 0, 1234.567);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $paymentGateway->id);

        $this->assertEqualsWithDelta(1334.567, $balance, 0.0001);
    }

    /**
     * The debit-normal counterpart: an Asset account with the SAME numbers
     * (opening_balance 100.000, a credit of 1234.567 posted against it — e.g.
     * a withdrawal) must read -1134.567 (opening + (debit - credit), a credit
     * DECREASES a debit-normal account), proving the sign flips correctly
     * based on the account's own root rather than being hardcoded either way.
     */
    public function test_asset_account_balance_is_opening_plus_debit_minus_credit_movement(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);

        $assets = $this->makeRoot($company, 'Assets');
        $bank = $this->makeChild($company, $assets, 'Bank', [
            'opening_balance' => 100.000,
        ]);

        $this->insertJournalEntry($company, $branch, $bank, 0, 1234.567);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $bank->id);

        $this->assertEqualsWithDelta(-1134.567, $balance, 0.0001);
    }

    /**
     * Baseline debit-normal arithmetic (no opening balance) — SUM(debit) -
     * SUM(credit) for an explicit Assets-tree account.
     */
    public function test_derived_balance_equals_sum_debit_minus_credit_for_a_debit_normal_account(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $assets = $this->makeRoot($company, 'Assets');
        $account = $this->makeChild($company, $assets, 'Cash');

        $this->insertJournalEntry($company, $branch, $account, 100.00, 0);
        $this->insertJournalEntry($company, $branch, $account, 0, 40.00);
        $this->insertJournalEntry($company, $branch, $account, 25.50, 0);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);

        $this->assertEqualsWithDelta(100.00 - 40.00 + 25.50, $balance, 0.0001);
    }

    /**
     * Baseline credit-normal arithmetic (no opening balance) — SUM(credit) -
     * SUM(debit) for an explicit Liabilities-tree account. Documents the
     * automatic-derivation behavior that replaced the removed
     * `bool $creditPositive` parameter: the SAME totals produce a DIFFERENT
     * sign than the debit-normal test above, purely because this account's
     * root is 'Liabilities' instead of 'Assets'.
     */
    public function test_derived_balance_equals_sum_credit_minus_debit_for_a_credit_normal_account(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $liabilities = $this->makeRoot($company, 'Liabilities');
        $account = $this->makeChild($company, $liabilities, 'Loan Payable');

        $this->insertJournalEntry($company, $branch, $account, 0, 300.00);
        $this->insertJournalEntry($company, $branch, $account, 50.00, 0);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);

        $this->assertEqualsWithDelta(300.00 - 50.00, $balance, 0.0001);
    }

    /**
     * An account with no journal entries and no opening balance must derive
     * to a clean 0.00, not null / an SQL error from COALESCE-less SUM() over
     * zero rows. Uses a bare factory account: with nothing posted and no
     * opening balance, debit-normal vs. credit-normal classification cannot
     * change the (zero) result, so the ambiguity of a random factory name
     * doesn't matter here.
     */
    public function test_derived_balance_is_zero_with_no_journal_entries(): void
    {
        $company = Company::factory()->create();
        $account = Account::factory()->create(['company_id' => $company->id]);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);

        $this->assertSame(0.0, $balance);
    }

    /**
     * Documents exactly why accounts.actual_balance (decimal(10,2)) is being
     * retired in favor of this ledger-derived value: journal_entries.debit/
     * credit are decimal(15,3) — fils-capable — so three postings of 0.001
     * KWD sum to a ledger balance of 0.003, a value decimal(10,2) cannot
     * represent at all (it would round to 0.00, silently losing the entire
     * amount). The derived balance must preserve the third decimal place
     * that the legacy column structurally cannot.
     */
    public function test_derived_balance_preserves_a_fils_value_the_legacy_column_cannot_represent(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $assets = $this->makeRoot($company, 'Assets');
        $account = $this->makeChild($company, $assets, 'Fils Cash');

        $this->insertJournalEntry($company, $branch, $account, 0.001, 0);
        $this->insertJournalEntry($company, $branch, $account, 0.001, 0);
        $this->insertJournalEntry($company, $branch, $account, 0.001, 0);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);

        $this->assertEqualsWithDelta(0.003, $balance, 0.0000001);

        // The value decimal(10,2) rounding would have produced instead —
        // proving the legacy column's precision loss is real, not theoretical.
        $this->assertSame(0.0, round($balance, 2));
        $this->assertNotEquals(round($balance, 2), $balance);
    }

    /**
     * Soft-deleted journal entries must not contribute to the derived
     * balance — mirrors the whereNull('deleted_at') filter every existing
     * ledger query in this codebase applies (TrialBalanceService's own
     * getAccountBalances/getOpeningBalances, and
     * AccountingInvariantsTest's fixture).
     */
    public function test_soft_deleted_journal_entries_are_excluded(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $assets = $this->makeRoot($company, 'Assets');
        $account = $this->makeChild($company, $assets, 'Cash');

        $this->insertJournalEntry($company, $branch, $account, 100.00, 0);

        DB::table('journal_entries')
            ->where('account_id', $account->id)
            ->update(['deleted_at' => now()]);

        $this->insertJournalEntry($company, $branch, $account, 10.00, 0);

        $balance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);

        $this->assertEqualsWithDelta(10.00, $balance, 0.0001);
    }

    /**
     * Tenant guard (verifier finding A): the accessor takes a bare account id
     * and reads journal_entries via DB::table(), bypassing Account's
     * BelongsToCompany global scope entirely. Passing the WRONG company id
     * for a real account must throw CrossTenantAccountException, not
     * silently derive and return that other tenant's ledger balance.
     */
    public function test_throws_cross_tenant_exception_when_account_belongs_to_a_different_company(): void
    {
        $ownerCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $branch = $this->makeBranch($ownerCompany);
        $account = Account::factory()->create(['company_id' => $ownerCompany->id]);

        $this->insertJournalEntry($ownerCompany, $branch, $account, 500.00, 0);

        $this->expectException(CrossTenantAccountException::class);

        app(TrialBalanceService::class)->getCurrentAccountBalance($otherCompany->id, $account->id);
    }

    /**
     * Same guard, non-existent account id — must throw the same typed
     * exception rather than a generic null-property error or a false "0.00"
     * balance.
     */
    public function test_throws_cross_tenant_exception_when_account_does_not_exist(): void
    {
        $company = Company::factory()->create();

        $this->expectException(CrossTenantAccountException::class);

        app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, 999999999);
    }

    /**
     * Corrupt-tree guard: a non-root account (parent_id set) whose root_id
     * does not resolve to a real account in the same company must fail
     * loudly (RuntimeException) rather than silently defaulting to either
     * normal side — the same posture AccountService::resolveRoot() takes for
     * the identical corruption on the write path.
     */
    public function test_throws_when_non_root_accounts_root_id_does_not_resolve(): void
    {
        $company = Company::factory()->create();
        // parent_id is foreign-key constrained to a real accounts row, so the
        // parent must genuinely exist; root_id has no such constraint, and is
        // set here to an id that does not — the corruption under test.
        $parent = Account::factory()->create(['company_id' => $company->id]);
        $orphan = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $parent->id,
            'root_id' => 999999999,
        ]);

        $this->expectException(\RuntimeException::class);

        app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $orphan->id);
    }

    /**
     * ACCESSOR BEHAVIOUR TEST, not a call-site guard. This builds the same
     * Liabilities -> Client -> Payment Gateway tree shape CheckMyFatoorahPayments.php
     * writes against, then re-derives the '+ $payment->amount' arithmetic
     * that call site applies BY HAND in this test — it never invokes
     * CheckMyFatoorahPayments itself. Reverting that command's arithmetic or
     * its ->actual_balance/ledger-balance source changes nothing this test
     * observes, so it CANNOT go red for either regression; the
     * assertNotEqualsWithDelta() lines below only document what the correct
     * value is NOT, computed from local variables, not from production code.
     *
     * The real call-site guard — the one that actually runs the command and
     * would go red on either regression — is
     * tests/Feature/Accounting/CheckMyFatoorahPaymentsLedgerBalanceTest.php,
     * which invokes app:myfatoorah-check-status end-to-end via
     * $this->artisan(). What this test DOES prove is narrower but still
     * real: TrialBalanceService::getCurrentAccountBalance() correctly
     * derives a credit-normal balance (opening + credit movement) for this
     * specific tree shape.
     */
    public function test_check_my_fatoorah_payments_site_writes_ledger_derived_balance_not_actual_balance(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $liabilities = $this->makeRoot($company, 'Liabilities');
        $client = $this->makeChild($company, $liabilities, 'Client');
        $account = $this->makeChild($company, $liabilities, 'Payment Gateway', [
            'parent_id' => $client->id,
            'actual_balance' => 0.00, // deliberately wrong vs. the ledger below
        ]);

        $this->insertJournalEntry($company, $branch, $account, 0, 1234.567);

        $ledgerBalance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);
        $paymentAmount = 100.00;
        $expectedJournalBalance = $ledgerBalance + $paymentAmount; // mirrors the `'balance' => $paymentGatewayLedgerBalance + $payment->amount` line in CheckMyFatoorahPayments::handle()

        $this->assertEqualsWithDelta(1234.567, $ledgerBalance, 0.0001);
        $this->assertNotEqualsWithDelta((float) $account->actual_balance - $paymentAmount, $expectedJournalBalance, 0.0001);
        $this->assertNotEqualsWithDelta($ledgerBalance - $paymentAmount, $expectedJournalBalance, 0.0001);
    }

    /**
     * ACCESSOR BEHAVIOUR TEST, not a call-site guard — see the docblock on
     * test_check_my_fatoorah_payments_site_writes_ledger_derived_balance_not_actual_balance()
     * above for why: '+ $netAmount' is re-derived by hand here, not read from
     * the `'balance' => $gatewayAssetLedgerBalance + $netAmount` line in
     * PaymentController's invoice-payment COA writer, so reverting that call
     * site to ->actual_balance cannot turn this test red. The real call-site guard
     * is tests/Feature/Accounting/LedgerDerivedBalanceCallSitesTest.php,
     * which exercises the actual private createInvoicePaymentCOA() method.
     * What this test DOES prove: TrialBalanceService::getCurrentAccountBalance()
     * correctly derives a debit-normal balance for an Assets-tree account.
     */
    public function test_payment_controller_gateway_asset_site_writes_ledger_derived_balance_not_actual_balance(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $assets = $this->makeRoot($company, 'Assets');
        $account = $this->makeChild($company, $assets, 'Gateway Asset', [
            'actual_balance' => 0.00,
        ]);

        $this->insertJournalEntry($company, $branch, $account, 1234.567, 0);

        $ledgerBalance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);
        $netAmount = 50.00;
        $expectedJournalBalance = $ledgerBalance + $netAmount; // mirrors the `'balance' => $gatewayAssetLedgerBalance + $netAmount` line in PaymentController's invoice-payment COA writer

        $this->assertEqualsWithDelta(1234.567, $ledgerBalance, 0.0001);
        $this->assertNotEqualsWithDelta((float) $account->actual_balance + $netAmount, $expectedJournalBalance, 0.0001);
    }

    /**
     * ACCESSOR BEHAVIOUR TEST, not a call-site guard — same caveat as the two
     * tests above: '+ $accountingFee' is re-derived by hand here, not read
     * from the `'balance' => $gatewayExpenseLedgerBalance + $accountingFee`
     * line in PaymentController's invoice-payment COA writer, so this test
     * cannot detect a regression at that call site. The real call-site guard is
     * tests/Feature/Accounting/LedgerDerivedBalanceCallSitesTest.php. What
     * this test DOES prove: TrialBalanceService::getCurrentAccountBalance()
     * correctly derives a debit-normal balance for an Expenses-tree account.
     */
    public function test_payment_controller_gateway_expense_site_writes_ledger_derived_balance_not_actual_balance(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $expenses = $this->makeRoot($company, 'Expenses');
        $account = $this->makeChild($company, $expenses, 'Gateway Expense', [
            'actual_balance' => 0.00,
        ]);

        $this->insertJournalEntry($company, $branch, $account, 1234.567, 0);

        $ledgerBalance = app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);
        $accountingFee = 5.00;
        $expectedJournalBalance = $ledgerBalance + $accountingFee; // mirrors the `'balance' => $gatewayExpenseLedgerBalance + $accountingFee` line in PaymentController's invoice-payment COA writer

        $this->assertEqualsWithDelta(1234.567, $ledgerBalance, 0.0001);
        $this->assertNotEqualsWithDelta((float) $account->actual_balance + $accountingFee, $expectedJournalBalance, 0.0001);
    }

    /**
     * Data-quality guard: resolveAccountNormalSide() falls through to a
     * credit-normal default for any root name that is not one of the five
     * canonical roots (Assets/Expenses/Liabilities/Equity/Income) — a silent
     * guess with real bookkeeping consequences (wrong sign on every balance
     * derived from it), so it must be logged rather than passing unnoticed.
     * Proves the warning fires with company_id, account_id, and the
     * unrecognized root name attached. Removing the Log::warning() call
     * turns this test red.
     */
    public function test_logs_a_warning_when_the_account_root_name_is_not_recognized(): void
    {
        Log::spy();

        $company = Company::factory()->create();
        $root = $this->makeRoot($company, 'Weird Root');
        $account = $this->makeChild($company, $root, 'Mystery Account');

        app(TrialBalanceService::class)->getCurrentAccountBalance($company->id, $account->id);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'TrialBalanceService::resolveAccountNormalSide() root name not recognized, defaulting to credit-normal',
                [
                    'company_id' => $company->id,
                    'account_id' => $account->id,
                    'root_name' => 'Weird Root',
                ]
            );
    }
}
