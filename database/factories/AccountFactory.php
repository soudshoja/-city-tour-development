<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 *
 * Minimal, engine-agnostic account factory. Did not exist prior to the P1
 * PostingService build (Accounting Gap/11-technical-implementation-plan.md
 * §P1) — created here only because tests/Feature/Accounting and
 * tests/Unit/Services/Accounting need Account rows and none existed.
 *
 * Deliberately does NOT emulate AccountService's nine placement rules
 * (root/parent derivation, AccountCodeGenerator, depth <= 6, etc.) — that
 * refactor is explicitly out of scope for P1 (mission scope rule 2). Tests
 * that need a specific tree shape (root/parent/leaf, cross-tenant, disabled,
 * group-with-children) should compose the states below or override fields
 * directly via ->state([...]).
 *
 * FIX-ROUND NOTE (.planning/P1-VERIFICATION-FINDINGS.json findings[]: "The C1
 * 'global invariant' is vacuous — assertCompanyLedgerBalanced can never fail
 * for this suite"): TrialBalanceService::getAccountBalances() INNER JOINs
 * accounts AS root ON root.id = a.root_id (app/Services/TrialBalanceService.php:93),
 * so a factory-made account with a NULL root_id was invisible to every trial
 * balance / C1 invariant check regardless of what actually got posted to it —
 * totals.debit and totals.credit were always 0, so is_balanced was always
 * true, unconditionally, for every test. configure()'s afterCreating hook
 * below closes that gap by backfilling root_id to the account's OWN id (a
 * safe self-reference: root.id = a.root_id resolves to the same row,
 * satisfying the INNER JOIN with no risk of an accidental cross-tenant root —
 * deliberately NOT Account::factory(), which would create a SECOND row under
 * a second, unrelated Company::factory() default) whenever root_id is still
 * NULL after creation. Any caller that supplies a real, non-null root_id
 * explicitly (a genuine root/parent chain, e.g. PostingServiceBalanceTest's
 * group()+child fixtures) is left untouched; a caller that explicitly passes
 * `'root_id' => null` gets the same self-reference backfill as the default,
 * since the two are indistinguishable once persisted — no existing test in
 * this codebase relies on a factory-made Account's root_id staying NULL (the
 * few pre-existing tests that need a literal NULL root_id, e.g.
 * tests/Feature/AgentTest.php, construct the row via Account::create()
 * directly, never via this factory, and are unaffected by this change). See
 * tests/Unit/Services/Accounting/AccountingInvariantsTest.php for the
 * regression proof that assertCompanyLedgerBalanced can now actually fail.
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return $this
     */
    public function configure()
    {
        return $this->afterCreating(function (Account $account) {
            if ($account->root_id === null) {
                $account->forceFill(['root_id' => $account->id])->saveQuietly();
            }
        });
    }

    /**
     * Default state: a standalone LEAF account (is_group = false, no parent)
     * belonging to its own fresh Company. This is the shape PostingService's
     * per-line leaf rule (children()->doesntExist() AND is_group === false)
     * expects to be able to post against.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serial_number' => null,
            'account_type' => $this->faker->randomElement(['asset', 'liability', 'income', 'expense', 'equity']),
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'name' => $this->faker->unique()->words(3, true),
            'level' => 1,
            'actual_balance' => 0,
            'opening_balance' => 0,
            'opening_balance_date' => null,
            'budget_balance' => 0,
            'variance' => 0,
            'parent_id' => null,
            'root_id' => null,
            'company_id' => Company::factory(),
            'branch_id' => null,
            'agent_id' => null,
            'client_id' => null,
            'supplier_id' => null,
            'supplier_company_id' => null,
            'reference_id' => null,
            'account_type_id' => null,
            'code' => (string) $this->faker->unique()->numberBetween(10000, 9999999),
            'currency' => 'KWD',
            'is_group' => false,
            'disabled' => false,
            'balance_must_be' => null,
        ];
    }

    /**
     * A non-leaf (group / parent) account. NOTE: setting is_group = true
     * alone does not make an account non-postable under PostingService's
     * leaf rule, which is `children()->doesntExist() AND is_group === false`
     * (an OR of two independent signals per file 11 pipeline step 3d) — to
     * actually trip NonLeafAccountException in a test, either use this state
     * together with a real child account, or rely on children() alone.
     */
    public function group(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_group' => true,
        ]);
    }

    /** A disabled ("frozen") account — used to exercise FrozenAccountException. */
    public function disabled(): self
    {
        return $this->state(fn (array $attributes) => [
            'disabled' => true,
        ]);
    }
}
