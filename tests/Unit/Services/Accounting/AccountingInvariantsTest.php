<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Support\AccountingInvariants;
use Tests\TestCase;

/**
 * Regression coverage for the P1 fix-round HIGH finding "The C1 'global invariant' is vacuous —
 * assertCompanyLedgerBalanced can never fail for this suite"
 * (.planning/P1-VERIFICATION-FINDINGS.json findings[], anchored at
 * database/factories/AccountFactory.php:50).
 *
 * Root cause: AccountFactory hardcoded root_id => null, and
 * TrialBalanceService::getAccountBalances() INNER JOINs accounts AS root ON root.id = a.root_id
 * (app/Services/TrialBalanceService.php:93) — so every factory-made account was silently dropped
 * from the trial balance result set, totals.debit/totals.credit were always 0, and
 * AccountingInvariants::assertCompanyLedgerBalanced() returned is_balanced = true
 * unconditionally, no matter what the engine (or a bypassing writer) actually posted. The fix
 * lives in database/factories/AccountFactory.php (configure()'s afterCreating backfill); this
 * class exists solely to PROVE that fix actually makes the invariant capable of failing — "an
 * invariant that cannot fail is worse than no invariant."
 *
 * Deliberately does NOT extend Tests\Support\AccountingTestCase: that class's tearDown() hook
 * calls this exact assertion automatically for every tracked company, which would make a test
 * that intentionally leaves a company's ledger unbalanced fail twice, for confusing, overlapping
 * reasons. This class uses the AccountingInvariants trait directly instead, the same way
 * AccountingTestCase does, so it exercises the real, shared assertion code — not a reimplementation
 * of it.
 */
class AccountingInvariantsTest extends TestCase
{
    use AccountingInvariants;
    use RefreshDatabase;

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * Proof, part 1: the factory fix actually makes accounts visible to TrialBalanceService.
     * Calls TrialBalanceService directly (bypassing the assertCompanyLedgerBalanced wrapper) so a
     * future regression that re-breaks the root_id join is caught even if that wrapper's own
     * boolean check is later weakened or removed.
     */
    public function test_factory_made_accounts_are_visible_to_trial_balance_service(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $account = Account::factory()->create(['company_id' => $company->id]);

        $this->assertNotNull(
            $account->root_id,
            'AccountFactory must attach a real, non-null root_id — TrialBalanceService INNER '
                .'JOINs accounts AS root ON root.id = a.root_id, so a NULL root_id silently drops '
                .'the account from every trial balance.'
        );

        $this->insertRawJournalEntry($company, $branch, $account, debit: 40.000, credit: 0.0);
        $this->insertRawJournalEntry($company, $branch, $account, debit: 0.0, credit: 40.000);

        $result = app(TrialBalanceService::class)->generate(
            $company->id,
            now()->subYear(),
            now()->addYear(),
            ['show_zero' => true]
        );

        $this->assertGreaterThan(
            0,
            count($result['accounts']),
            'TrialBalanceService must see at least one factory-made account for a company with '
                .'real journal_entries activity; a zero-account result is exactly the vacuous-'
                .'invariant failure mode this test guards against (the root_id INNER JOIN '
                .'silently dropping every row).'
        );
    }

    /**
     * Proof, part 2 (the required regression test): a raw, engine-bypassing insert that leaves a
     * company's ledger unbalanced must make assertCompanyLedgerBalanced() FAIL. This is the
     * single most important test in this file — before the AccountFactory fix, this exact test
     * body would have passed silently (is_balanced was always true), which is precisely the
     * defect being closed here.
     */
    public function test_assert_company_ledger_balanced_detects_a_real_imbalance(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $account = Account::factory()->create(['company_id' => $company->id]);

        // Bypass PostingService entirely: a single one-sided raw insert, with no offsetting
        // credit anywhere in the company's ledger. Exactly the shape PostingService's own step 4
        // (UnbalancedDocumentException) exists to prevent the engine itself from ever writing —
        // this proves the INDEPENDENT post-hoc invariant also catches it if some other writer
        // (import, tinker, a future bypass) ever does.
        $this->insertRawJournalEntry($company, $branch, $account, debit: 100.000, credit: 0.0);

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageMatches('/does not balance/');

        $this->assertCompanyLedgerBalanced($company->id);
    }

    /**
     * Negative control for the proof above: a genuinely balanced pair of raw lines against the
     * same fixed factory must NOT trip the invariant. Guards against the fix over-correcting into
     * a check that fails unconditionally (the mirror-image defect of the vacuous one).
     */
    public function test_assert_company_ledger_balanced_passes_a_real_balanced_pair(): void
    {
        $company = Company::factory()->create();
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $this->insertRawJournalEntry($company, $branch, $debitAccount, debit: 55.000, credit: 0.0);
        $this->insertRawJournalEntry($company, $branch, $creditAccount, debit: 0.0, credit: 55.000);

        // assertCompanyLedgerBalanced() is void; not throwing IS the assertion. Register it
        // explicitly so PHPUnit does not report this test as risky for having no assertions.
        $this->assertCompanyLedgerBalanced($company->id);
        $this->addToAssertionCount(1);
    }

    /**
     * Raw DB::table('journal_entries')->insert(), deliberately NOT going through
     * App\Services\Accounting\PostingService or JournalEntry::create() — these tests exist to
     * prove the invariant catches a bypassing writer, so the fixture itself must bypass the
     * engine. Column list mirrors exactly what PostingService::post() itself writes (see
     * app/Services/Accounting/PostingService.php step 8) so it satisfies the same NOT NULL /
     * foreign-key constraints without needing to re-derive the schema independently.
     */
    private function insertRawJournalEntry(
        Company $company,
        Branch $branch,
        Account $account,
        float $debit,
        float $credit
    ): void {
        DB::table('journal_entries')->insert([
            'name' => $account->name,
            'transaction_id' => null,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'transaction_date' => now(),
            'description' => 'AccountingInvariantsTest raw fixture line (engine-bypassing)',
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
}
