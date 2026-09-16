<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
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
     * Regression coverage for W1.3's residual #1 fix (EnsureSystemLeaves::
     * fixDuplicateGatewayFeeCode() collision check): the invariant this class exercises must be
     * ABLE to see a duplicate account code, or a bare renumber/import that hands two accounts the
     * same code slips past the whole suite silently — exactly what happened before this test
     * existed.
     */
    public function test_assert_no_duplicate_account_codes_detects_a_new_duplicate(): void
    {
        $company = Company::factory()->create();
        Account::factory()->create(['company_id' => $company->id, 'code' => '9999', 'name' => 'First Nine-Nine']);
        Account::factory()->create(['company_id' => $company->id, 'code' => '9999', 'name' => 'Second Nine-Nine']);

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageMatches('/sharing code "9999"/');

        $this->assertNoDuplicateAccountCodes($company->id);
    }

    /**
     * Negative control, part 1: a company with no duplicate codes at all must not trip the
     * invariant.
     */
    public function test_assert_no_duplicate_account_codes_passes_with_unique_codes(): void
    {
        $company = Company::factory()->create();
        Account::factory()->create(['company_id' => $company->id, 'code' => '1111']);
        Account::factory()->create(['company_id' => $company->id, 'code' => '2222']);

        $this->assertNoDuplicateAccountCodes($company->id);
        $this->addToAssertionCount(1);
    }

    /**
     * ── RE-DERIVED BY CT-A10 ────────────────────────────────────────────────────────────────────
     * This test used to be called `..._tolerates_the_one_known_coaseeder_pair` and asserted that a
     * freshly seeded company passes the invariant **despite** CoaSeeder shipping a known duplicate
     * ('2130': Suppliers (Hotels) / Suppliers (Ferry)) that the invariant explicitly excused.
     *
     * **Neither half of that is true any more, and neither should be.** CT-A4b fixed the seeder at
     * source (Ferry moved to '2131') and removed the named exception from
     * {@see AccountingInvariants::assertNoDuplicateAccountCodes()}, whose failure message now reads
     * *"every duplicate code is now a defect"*. So the test was asserting a tolerance that the code
     * no longer has and that the owner's own ruling says it must not have.
     *
     * It failed **safely**, at its own precondition (*"CoaSeeder must still ship the known 2130
     * duplicate for this test to prove anything"*) rather than passing vacuously — the original
     * author's guard doing exactly its job. The expectation was re-derived rather than the number
     * bumped.
     *
     * **What it is re-derived onto.** Its purpose was to prove the invariant is NARROW — that it
     * excuses precisely one thing and is not accidentally silencing duplicates in general. The
     * invariant has exactly one exclusion left, and it is a real one that can still occur:
     * `whereNull('deleted_at')`. So this now walks that boundary on a REAL seeded chart (~100
     * accounts), not a two-row fixture:
     *
     *   1. a freshly seeded chart passes — CT-A4b's fix, re-proved at the invariant layer;
     *   2. introduce a live duplicate on that chart and the invariant FIRES — it is not silenced by
     *      chart size or by the seeder's own codes;
     *   3. soft-delete one side and it PASSES again — the one exclusion, honoured.
     *
     * **What this test is not.** It is a control over the invariant, which is a TEST helper. It has
     * never run against the production chart and does not now. The 290 duplicate codes CT-A4 found
     * on the real City Travelers chart are untouched by it and remain an owner decision
     * (`accounting:coa-duplicates --renumber`, still unrun).
     */
    public function test_assert_no_duplicate_account_codes_is_narrow_on_a_real_seeded_chart(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $chartSize = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $this->assertGreaterThan(
            50,
            $chartSize,
            'Precondition: this must run against a REAL seeded chart, not a handful of rows.'
        );

        // 1. The seeded chart is clean. CT-A4b's fix, seen from the invariant rather than the seeder.
        $this->assertNoDuplicateAccountCodes($company->id);

        // 2. A live duplicate on that same chart MUST fire. Deliberately reusing a code the seeder
        //    already issued, which is the shape a mis-minted supplier leaf really takes.
        $existing = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)->whereNotNull('code')->orderBy('id')->firstOrFail();

        $clash = Account::factory()->create([
            'company_id' => $company->id,
            'code' => $existing->code,
        ]);

        $fired = false;
        try {
            $this->assertNoDuplicateAccountCodes($company->id);
        } catch (AssertionFailedError $e) {
            $fired = true;
            $this->assertStringContainsString('accounts sharing code', $e->getMessage());
            $this->assertStringContainsString((string) $existing->code, $e->getMessage());
        }

        $this->assertTrue($fired, 'the invariant must catch a duplicate introduced onto a full seeded chart');

        // 3. Mark one side deleted: the invariant's ONE remaining exclusion, and it must be honoured.
        //    A code freed by a delete is not a duplicate — that is what `whereNull('deleted_at')` is
        //    for, and it is the only thing this invariant still excuses.
        //
        //    CT-A10 finding: `deleted_at` is written DIRECTLY here, not via `$clash->delete()`,
        //    because `App\Models\Account` does NOT use the `SoftDeletes` trait even though the
        //    `accounts` table has carried a `deleted_at` column since
        //    `2026_08_24_120002_add_engine_columns_to_accounts_table`. `->delete()` on that model is
        //    a HARD delete, which would remove the row and prove nothing about the exclusion. The
        //    invariant filters on the COLUMN, so the column is what this step sets — which is also
        //    the only way the state arises today (a data repair, or the day the trait is adopted).
        DB::table('accounts')->where('id', $clash->id)->update(['deleted_at' => now()]);
        $this->assertNotNull(
            DB::table('accounts')->where('id', $clash->id)->value('deleted_at'),
            'Precondition: the clash row must be marked deleted, not removed.'
        );

        $this->assertNoDuplicateAccountCodes($company->id);
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
