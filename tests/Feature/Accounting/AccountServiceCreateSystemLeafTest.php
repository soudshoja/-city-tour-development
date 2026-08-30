<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountService;
use Database\Seeders\CoaSeeder;
use Tests\Support\AccountingTestCase;

/**
 * KEY: engine+infra. Residual 17 (W2.1 lead report §5) — three independent fixes to
 * AccountService::createSystemLeaf():
 *   1. currency now reads config('accounting.engine.base_currency') instead of a hardcoded
 *      'KWD' literal.
 *   2. The field list this method assigns is now the EXACT SAME set create() assigns (name,
 *      parent_id, root_id, account_type, report_type, level, company_id, is_group, disabled,
 *      serial_number, branch_id, agent_id, client_id, supplier_id, supplier_company_id,
 *      reference_id, currency, balance_must_be, actual_balance, opening_balance,
 *      opening_balance_date, budget_balance, variance, created_by, updated_by) — each one
 *      holding create()'s own null/0 fallback for an $attrs key this method never receives.
 *   3. ancestorChainMatches() no longer reads Eloquent's lazy-loaded `$account->parent` relation
 *      (silently scoped by App\Traits\BelongsToCompany's global scope to whichever company an
 *      authenticated session belongs to) — it now walks parent_id explicitly through
 *      withoutGlobalScopes() queries, so $companyId (the method's own explicit parameter) is the
 *      only company the chain walk can ever resolve against, regardless of who is authenticated.
 *
 * NOTE on the comparison baseline for fix #2: Database\Seeders\CoaSeeder does NOT go through
 * AccountService at all — it writes via a raw `Account::updateOrCreate()` loop (verified by
 * reading; confirmed empirically: a freshly CoaSeeder-seeded leaf has is_group=1 (the accounts
 * table's own DB column default — TRUE — since CoaSeeder never sets it) and currency=NULL (never
 * set either), NEITHER of which any AccountService-created leaf should carry (is_group=false is
 * create()'s own rule 8; currency is now the configured base currency). A literal column
 * comparison against a raw CoaSeeder row would therefore fail on exactly the fields this fix
 * improves, for reasons that have nothing to do with createSystemLeaf() — CoaSeeder's raw
 * is_group default is itself a separate, already-documented, deliberately deferred defect
 * (PostingService's own class docblock, P1 FIX ROUND HIGH finding: "~42,401 accounts flagged
 * is_group vs 25 genuine leaf violations" — the engine never trusts is_group for exactly this
 * reason). The correct, apples-to-apples baseline is AccountService::create() itself — the method
 * createSystemLeaf() is explicitly documented as a "narrow, explicit companion to create()" that
 * shares every rule except #1 and #7 — so this test proves the two methods produce
 * COLUMN-IDENTICAL accounts for the same shape, not that createSystemLeaf() reproduces a raw
 * seeder bypass's own known-stale defaults.
 */
class AccountServiceCreateSystemLeafTest extends AccountingTestCase
{
    /**
     * The exact chain App\Console\Commands\EnsureSystemLeaves uses for its KNET/uPayment leaves —
     * reused here rather than invented, so this test exercises a real, already-relied-upon chain
     * shape.
     */
    private const KNOWN_CHAIN = ['Payment Gateway Charges', 'Direct Expenses (Cost of Sales)', 'Expenses'];

    public function test_create_system_leaf_is_column_identical_to_create_for_the_same_shape(): void
    {
        $companyA = Company::factory()->create();
        CoaSeeder::run($companyA->id);
        $this->trackCompanyForInvariants($companyA->id);

        $companyB = Company::factory()->create();
        CoaSeeder::run($companyB->id);
        $this->trackCompanyForInvariants($companyB->id);

        $poolA = Account::withoutGlobalScopes()
            ->where('company_id', $companyA->id)
            ->where('name', 'Payment Gateway Charges')
            ->firstOrFail();

        $viaCreate = app(AccountService::class)->create([
            'company_id' => $companyA->id,
            'parent_id' => $poolA->id,
            'name' => 'Residual 17 Column Identity Probe',
        ]);

        $viaSystemLeaf = app(AccountService::class)->createSystemLeaf(
            $companyB->id,
            self::KNOWN_CHAIN,
            'Residual 17 Column Identity Probe',
            '5199'
        );

        // Every field that carries no per-request input from either method's caller must now
        // agree exactly — this is fix #2 under test.
        $fieldsExpectedIdentical = [
            'is_group', 'disabled', 'serial_number', 'branch_id', 'agent_id', 'client_id',
            'supplier_id', 'supplier_company_id', 'reference_id', 'currency', 'balance_must_be',
            'actual_balance', 'opening_balance', 'opening_balance_date', 'budget_balance', 'variance',
        ];

        foreach ($fieldsExpectedIdentical as $field) {
            $this->assertSame(
                $viaCreate->getAttribute($field),
                $viaSystemLeaf->getAttribute($field),
                "Column '{$field}' must match between create() and createSystemLeaf() for the same shape (residual 17)."
            );
        }

        // Derived-from-the-resolved-root fields must also agree, since both leaves sit under the
        // SAME pool ('Payment Gateway Charges') whose own root is 'Expenses' in both companies.
        $this->assertSame($viaCreate->account_type, $viaSystemLeaf->account_type);
        $this->assertSame($viaCreate->report_type, $viaSystemLeaf->report_type);
        $this->assertSame($viaCreate->level, $viaSystemLeaf->level);
        $this->assertSame(
            'Expenses',
            Account::withoutGlobalScopes()->find($viaCreate->root_id)?->name,
            'Sanity check: create()\'s own resolved root must be "Expenses".'
        );
        $this->assertSame(
            'Expenses',
            Account::withoutGlobalScopes()->find($viaSystemLeaf->root_id)?->name,
            'createSystemLeaf() must resolve the SAME root as create() for the same chain.'
        );

        // Fix #1, explicitly: currency is the configured base currency, not a hardcoded literal.
        $this->assertSame(
            (string) config('accounting.engine.base_currency', 'KWD'),
            $viaSystemLeaf->currency
        );
    }

    /**
     * Fix #3: the ancestor chain walk must resolve $companyId's own tree regardless of which
     * company the CURRENTLY AUTHENTICATED session belongs to. Before this fix,
     * ancestorChainMatches()'s `$account->parent` lazy-load was silently scoped by
     * BelongsToCompany's global 'company' scope to Auth::user()'s OWN company — invisible from a
     * console context (EnsureSystemLeaves has no Auth, which is why this was latent) but a real
     * false-negative the moment an authenticated request-context caller (e.g. a superadmin acting
     * on a company other than their own session) reaches this method.
     */
    public function test_ancestor_chain_walk_resolves_the_target_company_not_the_authenticated_users_company(): void
    {
        $targetCompany = Company::factory()->create();
        CoaSeeder::run($targetCompany->id);
        $this->trackCompanyForInvariants($targetCompany->id);

        // A second, UNRELATED company, whose chart also happens to seed accounts named identically
        // to every link in self::KNOWN_CHAIN (CoaSeeder is deterministic) — the exact shape that
        // would let a mis-scoped chain walk silently "succeed" against the WRONG company's tree
        // instead of merely failing loudly, if BelongsToCompany's scope ever let that happen.
        $authenticatedUser = User::factory()->create(['role_id' => Role::COMPANY]);
        // Role::COMPANY resolves getCompanyId() via $user->company (app/Helper/helper.php,
        // User::company() -> hasOne(Company::class)) — point it at a SECOND company, deliberately
        // NOT $targetCompany.
        $authenticatedUsersOwnCompany = Company::factory()->create(['user_id' => $authenticatedUser->id]);
        CoaSeeder::run($authenticatedUsersOwnCompany->id);

        $this->actingAs($authenticatedUser);

        $account = app(AccountService::class)->createSystemLeaf(
            $targetCompany->id,
            self::KNOWN_CHAIN,
            'Residual 17 Cross-Company Auth Probe',
            '5198'
        );

        $this->assertSame(
            $targetCompany->id,
            $account->company_id,
            'createSystemLeaf() must create the leaf under $companyId (its own explicit parameter), '
                .'never silently under the authenticated session\'s own company.'
        );

        $parent = Account::withoutGlobalScopes()->find($account->parent_id);
        $this->assertNotNull($parent);
        $this->assertSame('Payment Gateway Charges', $parent->name);
        $this->assertSame($targetCompany->id, $parent->company_id);

        $root = Account::withoutGlobalScopes()->find($account->root_id);
        $this->assertSame('Expenses', $root?->name);
        $this->assertSame($targetCompany->id, $root?->company_id);
    }
}
