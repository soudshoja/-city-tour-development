<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\LedgerSource;
use App\Services\Accounting\NamedAccountGroupResolver;
use App\Services\Accounting\TaskPayablePositionResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 3, finding **R3-1** — the completeness argument depended on a data invariant that
 * nothing enforces.
 *
 * Round 2 said `payableAccountIds()` was complete "by construction". It was complete by
 * construction **given exactly one account named `Accounts Payable` per company** — and
 * `accounts.name` has no uniqueness constraint anywhere: `CoaController` validates it as
 * `required|string|max:255`, so an operator can create or rename a second one in two clicks.
 * `TaskPayablePositionResolver::apSubtreeIds()` anchored with `->value('id')` — SINGULAR, and with
 * no ordering, so which of two same-named groups it picked was not even deterministic.
 *
 * The verifier did it through real HTTP routes: a second `Accounts Payable` under Liabilities, a
 * supplier leaf under that, 700.000 credited to the leaf — **not in `apSubtreeIds()`, not in
 * `payableAccountIds()`, screen 0.000, ledger 700.000.**
 *
 * ── The fix, and why it is a shared resolver rather than a local `pluck()` ──────────────────────
 * This PR was itself carrying THREE different handlings of the same lookup: `apSubtreeIds()` used
 * `->value()`, round 2's own F6 fix used `->pluck()` (plural, four files away), and
 * `AccountingController::createPayableDetail()` used `->first()`. Making `apSubtreeIds()` plural
 * on its own would have left two of the three and invited a fourth. Every one of them now goes
 * through {@see NamedAccountGroupResolver}, which is the ONLY place in `app/Services/Accounting`
 * allowed to anchor on one of these names — enforced by a two-sided ratchet in
 * {@see \Tests\Feature\Accounting\ArchitectureTest::test_no_accounting_service_anchors_on_a_control_account_name()}.
 *
 * The AR side is covered too: the same names, the same screens, the same absence of a constraint.
 *
 * ── The completeness argument, restated with the invariant removed ─────────────────────────────
 *   1. Any line that is a supplier payable lands on an account under **one of** the company's
 *      accounts named `Accounts Payable` — plural, because there may be more than one and the
 *      resolver now seeds its BFS frontier with all of them.
 *   2. An account with no posted journal line contributes exactly 0.000.
 *   ∴ (every AP subtree ∩ moved) reaches every KWD of accounts payable, with no uniqueness
 *   assumption left in the chain.
 */
class NameAnchorMultiplicityR3Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    private int $companyId;

    private int $branchId;

    private int $bankAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->companyId, 'user_id' => $branchOwner->id]);
        $this->branchId = (int) $branch->id;

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        Client::factory()->create(['company_id' => $this->companyId]);

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->companyId]);

        $this->bankAccountId = (int) $this->accountByCode('1201')->id;
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    private function mintUnder(int $parentId, string $name, string $code): int
    {
        $parent = Account::withoutGlobalScopes()->findOrFail($parentId);

        return (int) Account::create([
            'company_id' => $this->companyId,
            'parent_id' => $parent->id,
            'root_id' => $parent->root_id ?? $parent->id,
            'name' => $name,
            'code' => $code,
            'level' => (int) $parent->level + 1,
            'account_type' => null,
            'report_type' => $parent->report_type,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ])->id;
    }

    private function companyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::where('id', $this->companyId)->update(['user_id' => $user->id]);

        return $user;
    }

    /** Credit an account through the real manual-JV HTTP route, as the verifier did. */
    private function creditViaJv(int $accountId, float $amount): void
    {
        $this->actingAs($this->companyUser())->post(route('receivable-details.receivable-store'), [
            'transaction_date' => now()->subDay()->format('Y-m-d'),
            'account_id' => $accountId,
            'branch_id' => $this->branchId,
            'bank_account' => $this->bankAccountId,
            'description' => 'R3-1 multiplicity attack',
            'name' => 'counterparty',
            'amount' => $amount,
            'type' => 'receivable',
        ])->assertRedirect();
    }

    private function screenPayableBalance(): float
    {
        $response = $this->actingAs($this->companyUser())
            ->get(route('reports.unpaid-report', ['account_id' => 'all']));
        $response->assertOk();

        return round((float) $response->viewData('payableBalance'), 3);
    }

    private function engineNetCreditOver(array $accountIds): float
    {
        if ($accountIds === []) {
            return 0.0;
        }

        return round((float) DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->whereIn('je.account_id', $accountIds)
            ->where('je.company_id', $this->companyId)
            ->whereNull('je.deleted_at')
            ->whereNotNull('t.doc_type')->whereNotNull('t.posting_date')
            ->selectRaw('COALESCE(SUM(je.credit),0) - COALESCE(SUM(je.debit),0) as net')
            ->value('net'), 3);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * THE ATTACK, verbatim in shape: a SECOND account named `Accounts Payable`, a supplier leaf
     * under it, and real money on that leaf.
     */
    public function test_a_second_accounts_payable_group_does_not_hide_a_subtree(): void
    {
        $liabilities = $this->accountByCode('2000');
        $secondApGroup = $this->mintUnder((int) $liabilities->id, 'Accounts Payable', '2900');
        $leaf = $this->mintUnder($secondApGroup, 'Payee Leaf Under The Second Group', '2901');

        $this->creditViaJv($leaf, 700.0);

        $this->assertEqualsWithDelta(
            700.0,
            $this->engineNetCreditOver([$leaf]),
            0.0005,
            'the fixture must really have put 700.000 on that leaf'
        );

        $subtree = app(TaskPayablePositionResolver::class)->apSubtreeIds($this->companyId);

        $this->assertContains(
            $leaf,
            $subtree,
            'R3-1: a leaf under a SECOND account named Accounts Payable must be in the AP subtree. '
            .'`accounts.name` has no uniqueness constraint, so the singular ->value("id") anchor '
            .'silently dropped a whole subtree.'
        );
        $this->assertContains(
            $secondApGroup,
            $subtree,
            'and so must the second group itself, since the BFS is seeded from every match'
        );

        $this->assertContains(
            $leaf,
            app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class)),
            'and therefore in payableAccountIds()'
        );

        $this->assertEqualsWithDelta(
            $this->engineNetCreditOver($subtree),
            $this->screenPayableBalance(),
            0.0005,
            'the unpaid-AP screen must reconcile to the whole AP truth, across BOTH groups'
        );
    }

    /**
     * The same shape on the RECEIVABLE side. Nothing in this PR reads an AR subtree today, so this
     * is the resolver's own guarantee rather than a screen assertion — the point is that the shared
     * helper is name-plural for both control names, so an AR reader added later inherits it.
     */
    public function test_a_second_accounts_receivable_group_is_resolved_too(): void
    {
        $assets = $this->accountByCode('1000');
        $secondArGroup = $this->mintUnder((int) $assets->id, 'Accounts Receivable', '1960');
        $leaf = $this->mintUnder($secondArGroup, 'Client Leaf Under The Second Group', '1961');

        $resolver = app(NamedAccountGroupResolver::class);

        $this->assertCount(
            2,
            $resolver->groupIds($this->companyId, 'Accounts Receivable'),
            'both accounts named Accounts Receivable must be returned'
        );

        $subtree = $resolver->subtreeIds($this->companyId, 'Accounts Receivable');

        $this->assertContains($secondArGroup, $subtree);
        $this->assertContains($leaf, $subtree);
    }

    /**
     * The BOUND survives multiplicity: a leaf under the second group with NO movement is still not
     * admitted. Widening the anchor must not widen the set beyond what the ledger touched.
     */
    public function test_an_unmoved_leaf_under_the_second_group_is_still_not_admitted(): void
    {
        $liabilities = $this->accountByCode('2000');
        $secondApGroup = $this->mintUnder((int) $liabilities->id, 'Accounts Payable', '2900');
        $moved = $this->mintUnder($secondApGroup, 'Payee Leaf That Moves', '2901');
        $unmoved = $this->mintUnder($secondApGroup, 'Payee Leaf That Never Moves', '2902');

        $this->creditViaJv($moved, 700.0);

        $ids = app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class));

        $this->assertContains($moved, $ids);
        $this->assertNotContains(
            $unmoved,
            $ids,
            'the movement bound must survive the multiplicity fix — widening the ANCHOR must not '
            .'widen the SET beyond what the ledger touched'
        );
    }

    /**
     * One lookup, one handling. The resolver is the single answer, and `groupIds()` is plural by
     * contract even in the ordinary one-group case.
     */
    public function test_the_resolver_is_plural_by_contract_even_with_one_group(): void
    {
        $resolver = app(NamedAccountGroupResolver::class);

        $this->assertCount(1, $resolver->groupIds($this->companyId, 'Accounts Payable'));
        $this->assertSame([], $resolver->groupIds($this->companyId, 'No Such Account Name'));
        $this->assertSame([], $resolver->subtreeIds($this->companyId, 'No Such Account Name'));
    }

    /**
     * COMPANY 2's REAL SHAPE, reproduced — and the reason R3-1 is a live condition rather than a
     * hypothesis.
     *
     * A read-only audit of the real charts (2026-09-16) found company 2 carrying two `Accounts
     * Payable` and two `Accounts Receivable`, identically on dev and live:
     *
     *   id 463  code 20002  level 3  root_id NULL  210 accounts  115 journal rows  14,205.62 Cr
     *   id 1305 code 2100   level 2  root_id 427    14 accounts    0 journal rows  empty
     *
     * The strays are not operator error — `2100`/`1350` are OUR canonical codes, seeded alongside a
     * pre-existing legacy tree where the money actually lives. Two details of that shape are traps,
     * and this fixture carries both deliberately:
     *
     *   - the MONEY-BEARING side is at **level 3**, not level 2; and
     *   - it has a **NULL `root_id`**.
     *
     * Anything in the walk that assumed level 2, or joined through `root_id`, would miss precisely
     * the tree that holds the balance. {@see NamedAccountGroupResolver} keys on `parent_id` alone
     * and filters on neither, which is what this case pins.
     *
     * Consolidating the two trees is NOT attempted anywhere in this lane: it is a chart migration
     * and an owner decision for the COA design phase. This test only proves the report layer reads
     * both.
     */
    public function test_company_twos_real_shape_legacy_money_tree_plus_empty_canonical_tree(): void
    {
        $liabilities = $this->accountByCode('2000');

        // The LEGACY, money-bearing tree: level 3, root_id NULL — company 2's 20002 shape.
        $legacyGroup = (int) Account::create([
            'company_id' => $this->companyId,
            'parent_id' => $liabilities->id,
            'root_id' => null,
            'name' => 'Accounts Payable',
            'code' => '20002',
            'level' => 3,
            'account_type' => null,
            'report_type' => $liabilities->report_type,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ])->id;

        $legacyLeaf = (int) Account::create([
            'company_id' => $this->companyId,
            'parent_id' => $legacyGroup,
            'root_id' => null,
            'name' => 'Legacy Supplier Leaf',
            'code' => '20003',
            'level' => 4,
            'account_type' => null,
            'report_type' => $liabilities->report_type,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ])->id;

        // The CANONICAL tree CoaSeeder already seeded (2100, level 2, real root_id) is present and
        // empty — exactly company 2's condition.
        $canonicalGroup = (int) $this->accountByCode('2100')->id;
        $this->assertNotSame($canonicalGroup, $legacyGroup);
        $this->assertNotNull($this->accountByCode('2100')->root_id, 'the canonical group has a real root_id');

        $this->creditViaJv($legacyLeaf, 14205.62);

        $resolver = app(NamedAccountGroupResolver::class);

        $this->assertCount(2, $resolver->groupIds($this->companyId, 'Accounts Payable'));

        $subtree = $resolver->subtreeIds($this->companyId, 'Accounts Payable');
        $this->assertContains($legacyGroup, $subtree, 'the level-3, NULL-root_id legacy group must be in the walk');
        $this->assertContains($legacyLeaf, $subtree, 'and so must its leaf — the one holding the money');
        $this->assertContains($canonicalGroup, $subtree, 'the empty canonical group is in the walk too');

        $this->assertSame(
            $legacyGroup,
            $resolver->primaryGroupId($this->companyId, 'Accounts Payable'),
            'a single-account caller must land on the tree that CARRIES THE MONEY, not on whichever '
            .'row the optimizer returned first — that accident is the live fragility R3-1 names'
        );

        $this->assertContains(
            $legacyLeaf,
            app(LedgerSource::class)->payableAccountIds($this->companyId, app(AccountResolver::class)),
            'and the payables set must include it'
        );

        $this->assertEqualsWithDelta(
            14205.62,
            $this->screenPayableBalance(),
            0.0005,
            'the unpaid-AP screen must show the legacy tree\'s money — this is the 14,205.62 company 2 '
            .'would silently lose the day MariaDB returns the two rows the other way round'
        );
    }

    // ── CT-A7 ROUND 4, finding R4-4 ─────────────────────────────────────────────────────────────

    /**
     * R4-4. Round 3's docblock said "the one whose subtree carries movement wins"; the code looped
     * in ID ORDER and returned the first group with ANY movement, so when BOTH carried movement the
     * LOWEST ID won. A screen could pin a tree holding 50.000 while the other held 14,205.620.
     *
     * The fixture is the flip the verifier demonstrated: the LOWER-id tree carries the SMALLER
     * amount, so an id-order pick lands on the wrong one.
     */
    public function test_the_tree_with_the_most_money_wins_not_the_lowest_id(): void
    {
        $liabilities = $this->accountByCode('2000');

        $small = $this->mintUnder((int) $liabilities->id, 'Accounts Payable', '2900');
        $smallLeaf = $this->mintUnder($small, 'Small Payee Leaf', '2901');

        $big = $this->mintUnder((int) $liabilities->id, 'Accounts Payable', '2910');
        $bigLeaf = $this->mintUnder($big, 'Big Payee Leaf', '2911');

        $this->assertLessThan($big, $small, 'the SMALL tree must have the LOWER id for this to bite');

        $this->creditViaJv($smallLeaf, 50.0);
        $this->creditViaJv($bigLeaf, 14205.62);

        $this->assertSame(
            $big,
            app(NamedAccountGroupResolver::class)->primaryGroupId($this->companyId, 'Accounts Payable'),
            'R4-4: when more than one tree carries movement, the one with the LARGEST |net| must win. '
            .'Ranking by id pins whichever happens to be older, which is the 50.000-over-14,205.620 '
            .'inversion the verifier demonstrated.'
        );
    }

    /**
     * R4-4, the reversal property. A reversal is a NEW ROW with the opposite sign, never a delete,
     * so an `exists()` movement test counted a fully-reversed tree as carrying money forever and one
     * stray entry from years ago could pin a tree permanently. Netting cancels it arithmetically.
     */
    public function test_a_fully_reversed_tree_does_not_outrank_a_live_one(): void
    {
        $liabilities = $this->accountByCode('2000');

        $reversed = $this->mintUnder((int) $liabilities->id, 'Accounts Payable', '2900');
        $reversedLeaf = $this->mintUnder($reversed, 'Reversed Payee Leaf', '2901');

        $live = $this->mintUnder((int) $liabilities->id, 'Accounts Payable', '2910');
        $liveLeaf = $this->mintUnder($live, 'Live Payee Leaf', '2911');

        $this->assertLessThan($live, $reversed, 'the reversed tree must be the OLDER one');

        // Posted, then fully reversed: both documents stay on the ledger and net to zero.
        $this->creditViaJv($reversedLeaf, 900.0);
        $this->debitThroughJv($reversedLeaf, 900.0);

        $this->creditViaJv($liveLeaf, 120.0);

        $this->assertEqualsWithDelta(
            0.0,
            $this->engineNetCreditOver([$reversedLeaf]),
            0.0005,
            'the reversed tree must really net to zero'
        );

        $this->assertSame(
            $live,
            app(NamedAccountGroupResolver::class)->primaryGroupId($this->companyId, 'Accounts Payable'),
            'R4-4: a tree that nets to zero must not outrank a live one just because it is older and '
            .'once had a row — which is exactly what the exists() test did'
        );
    }

    /**
     * R4-4, the WARNING. A duplicate chart is a chart problem for the owner to rule on, not
     * something the code should compensate for silently forever.
     */
    public function test_duplicate_control_names_are_logged_at_warning(): void
    {
        $liabilities = $this->accountByCode('2000');
        $second = $this->mintUnder((int) $liabilities->id, 'Accounts Payable', '2900');
        $leaf = $this->mintUnder($second, 'Payee Leaf', '2901');
        $this->creditViaJv($leaf, 30.0);

        $logged = [];
        Log::listen(function ($message) use (&$logged) {
            $logged[] = ['level' => $message->level, 'context' => $message->context];
        });

        app(NamedAccountGroupResolver::class)->primaryGroupId($this->companyId, 'Accounts Payable');

        $warnings = array_filter(
            $logged,
            fn (array $m) => $m['level'] === 'warning'
                && ($m['context']['event'] ?? null) === 'accounting.duplicate_control_account_name'
        );

        $this->assertNotEmpty($warnings, 'the duplicate must surface to the owner, not be absorbed');

        $context = array_values($warnings)[0]['context'];
        $this->assertSame($this->companyId, $context['company_id']);
        $this->assertContains($second, $context['account_ids'], 'and the ids must be named');
    }

    /** A single group must NOT warn — otherwise the signal is noise on every healthy company. */
    public function test_a_single_group_does_not_warn(): void
    {
        $logged = [];
        Log::listen(function ($message) use (&$logged) {
            $logged[] = $message->context['event'] ?? null;
        });

        app(NamedAccountGroupResolver::class)->primaryGroupId($this->companyId, 'Accounts Payable');

        $this->assertNotContains('accounting.duplicate_control_account_name', $logged);
    }

    /** Debit an account through the manual JV "payable" screen — used to reverse a credit. */
    private function debitThroughJv(int $accountId, float $amount): void
    {
        $this->actingAs($this->companyUser())->post(route('payable-details.payable-store'), [
            'transaction_date' => now()->format('Y-m-d'),
            'account_id' => $accountId,
            'branch_id' => $this->branchId,
            'bank_account' => $this->bankAccountId,
            'description' => 'R4-4 reversal',
            'amount' => $amount,
            'type' => 'payable',
        ])->assertRedirect();
    }
}
