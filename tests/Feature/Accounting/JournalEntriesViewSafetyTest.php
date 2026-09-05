<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\JournalEntryController;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\TestCase;

/**
 * Regression coverage for JournalEntryController::getJournalEntries() and its
 * three callers (JournalEntryController::index(), ::show(), and
 * InvoiceController::showDetails(), whose call reads
 * `app(JournalEntryController::class)->getJournalEntries($journalEntries)`).
 *
 * The bug this guards against: getJournalEntries() used to `return
 * redirect()->back()` whenever the Chart of Accounts was only partially
 * seeded (a root account missing) or when an entry's account pointed at a
 * root account name it didn't recognize. All three callers hand the return
 * value straight into a Blade view as a Collection-shaped variable
 * (->isEmpty()/->isNotEmpty()/->contains()/foreach), so a leaked
 * RedirectResponse there blows up the view instead of cleanly redirecting.
 *
 * Also covers item C: coa.index must render an honest "no COA yet" empty
 * state for a zero-COA company instead of five root panels that expand to
 * nothing.
 */
class JournalEntriesViewSafetyTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAccountingModule;

    /**
     * Creates a company owned directly by a fresh Role::COMPANY user (mirrors
     * tests/Feature/Security/AccountingRouteGateTest.php's pattern) with
     * every permission these routes might check. config/modules.php defaults
     * accounting DISABLED for every company regardless of age, so the
     * module is granted explicitly here — without it, module:accounting
     * 404s every route below before the Policy/permission checks these
     * tests actually mean to exercise ever run.
     *
     * @return array{0: User, 1: Company}
     */
    private function createCompanyOwner(): array
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $this->grantAccountingModule($company);

        $role = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);
        $user->assignRole($role);
        $role->givePermissionTo(['view coa', 'view account']);

        Company::forgetModuleCache();

        return [$user, $company];
    }

    /**
     * Builds four of the five root accounts (Assets, Liabilities, Equity,
     * Income — deliberately omitting Expenses) plus one leaf child under
     * Assets, reproducing a "partially-seeded COA".
     *
     * @return array{0: Account, 1: Account} [$assetsRoot, $leafUnderAssets]
     */
    private function partiallySeededCoa(Company $company): array
    {
        $assets = Account::factory()->create(['company_id' => $company->id, 'name' => 'Assets', 'is_group' => true]);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Liabilities', 'is_group' => true]);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Equity', 'is_group' => true]);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Income', 'is_group' => true]);
        // Expenses is deliberately absent.

        $leaf = Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'parent_id' => $assets->id,
            'root_id' => $assets->id,
        ]);

        return [$assets, $leaf];
    }

    /**
     * Builds all FIVE root accounts plus one leaf child under Assets — a
     * fully-seeded COA, the shape ::getJournalEntries() needs to actually
     * classify entries (rather than fail safe to an empty collection).
     *
     * @return array{0: Account, 1: Account} [$assetsRoot, $leafUnderAssets]
     */
    private function fullySeededCoa(Company $company): array
    {
        $assets = Account::factory()->create(['company_id' => $company->id, 'name' => 'Assets', 'is_group' => true]);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Liabilities', 'is_group' => true]);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Equity', 'is_group' => true]);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Income', 'is_group' => true]);
        Account::factory()->create(['company_id' => $company->id, 'name' => 'Expenses', 'is_group' => true]);

        $leaf = Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'parent_id' => $assets->id,
            'root_id' => $assets->id,
        ]);

        return [$assets, $leaf];
    }

    private function makeJournalEntry(Company $company, Branch $branch, Account $account, ?int $transactionId = null, ?int $taskId = null): JournalEntry
    {
        return JournalEntry::create([
            'name' => 'Test entry',
            'transaction_id' => $transactionId,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'transaction_date' => now(),
            'description' => 'Test entry',
            'debit' => 10,
            'credit' => 0,
            'balance' => 10,
            'task_id' => $taskId,
        ]);
    }

    // ------------------------------------------------------------------
    // A. All three callers must survive a partially-seeded COA (missing
    //    root account) with a real 200, never a leaked RedirectResponse.
    // ------------------------------------------------------------------

    public function test_journal_entries_index_survives_partially_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->partiallySeededCoa($company);

        // journal_entries.transaction_id carries a real FK to transactions,
        // so a row must exist.
        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 10,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));

        $response->assertOk();
        // Must render the existing "no entries" empty state, not crash on a
        // RedirectResponse handed to the view.
        $response->assertSee('No journal entries found.');
    }

    public function test_journal_entries_show_survives_partially_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->partiallySeededCoa($company);

        $this->makeJournalEntry($company, $branch, $leaf);

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.show', ['accountId' => $leaf->id]));

        $response->assertOk();
    }

    public function test_invoice_show_details_survives_partially_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->partiallySeededCoa($company);

        $agentType = \App\Models\AgentType::create(['name' => 'Test Type ' . uniqid()]);
        $agent = \App\Models\Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'type_id' => $agentType->id,
        ]);
        $client = \App\Models\Client::factory()->create(['agent_id' => $agent->id]);
        $invoice = Invoice::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
        ]);

        $this->makeJournalEntry($company, $branch, $leaf, null, null);

        $this->actingAs($user);

        $response = $this->get(route('invoice.details', [
            'companyId' => $company->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        // Must not blow up with "Undefined property: ResponseHeaderBag::..."
        // from a RedirectResponse leaking into the view.
        $response->assertOk();
    }

    // ------------------------------------------------------------------
    // C. coa.index with zero accounts must show an honest empty state.
    // ------------------------------------------------------------------

    public function test_coa_index_shows_empty_state_for_zero_coa_company(): void
    {
        [$user, $company] = $this->createCompanyOwner();

        $this->actingAs($user);

        $response = $this->get(route('coa.index'));

        $response->assertOk();
        $response->assertSee('No chart of accounts yet.');
    }

    public function test_coa_index_shows_panels_when_coa_exists(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $this->partiallySeededCoa($company);

        $this->actingAs($user);

        $response = $this->get(route('coa.index'));

        $response->assertOk();
        $response->assertDontSee('No chart of accounts yet.');
    }

    // ------------------------------------------------------------------
    // C (continued). coa.index with a partially-seeded COA must show the
    // per-root empty state ONLY for the missing root(s), not treat the
    // whole page as either "fully seeded" or "fully empty".
    // ------------------------------------------------------------------

    public function test_coa_index_shows_per_root_empty_state_for_partially_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        // partiallySeededCoa() builds Assets/Liabilities/Equity/Income and
        // deliberately omits Expenses.
        $this->partiallySeededCoa($company);

        $this->actingAs($user);

        $response = $this->get(route('coa.index'));

        $response->assertOk();
        // The four seeded roots render their normal panel, not an empty
        // state...
        $response->assertDontSee('No Assets accounts yet.');
        $response->assertDontSee('No Liabilities accounts yet.');
        $response->assertDontSee('No Equity accounts yet.');
        $response->assertDontSee('No Income accounts yet.');
        // ...but the one genuinely-missing root gets its own honest empty
        // state instead of a clickable header that expands to nothing.
        $response->assertSee('No Expenses accounts yet.');
    }

    // ------------------------------------------------------------------
    // D. journal-entries.index must actually RENDER entries for a
    // transaction that has them (the bug this W0.2 task exists to fix:
    // ->paginate(15) fed into getJournalEntries()'s collect() normalization
    // yielded pagination metadata, not entries, so the render loop
    // evaluated $journalEntry->account on an int and 500'd on every
    // transaction that actually had journal entries — the pre-existing
    // JournalEntriesViewSafetyTest suite only ever exercised the empty-COA
    // paths, which return before the loop, so it stayed green through that
    // regression).
    // ------------------------------------------------------------------

    public function test_journal_entries_index_renders_entries_for_a_transaction_with_a_fully_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->fullySeededCoa($company);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 10,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));

        $response->assertOk();
        // Must render the actual entry row, not the "No journal entries
        // found." empty state a leaked pagination-metadata Collection (or
        // a fail-safe empty collect()) would fall back to.
        $response->assertDontSee('No journal entries found.');
        $response->assertSee($leaf->name);
        $response->assertSee('10.00'); // the debit amount, number_format'd.
    }

    // ------------------------------------------------------------------
    // E. Both silent-suppression paths inside getJournalEntries() must log
    // a warning AND flash a visible banner instead of dropping rows with no
    // trace — FIX B.
    // ------------------------------------------------------------------

    public function test_journal_entries_index_logs_and_flashes_warning_for_partially_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->partiallySeededCoa($company);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 10,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Journal entries could not be classified: chart of accounts is missing one or more root accounts.',
                \Mockery::on(function (array $context) use ($company, $transaction, $leaf) {
                    return $context['company_id'] === $company->id
                        && $context['transaction_id'] === $transaction->id
                        && $context['account_id'] === $leaf->id
                        && str_contains($context['missing_roots'], 'Expenses');
                })
            );

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));

        $response->assertOk();
        $response->assertSee('No journal entries found.');
        // W0.3: the banner is now session()->now() rather than ->flash(),
        // so it never survives past the response that rendered it — assert
        // it against the rendered body (as an end user would actually see
        // it), not against the post-response session store.
        $response->assertSee('could not be classified');
        $response->assertSee('chart of accounts incomplete');
    }

    public function test_journal_entries_index_logs_and_flashes_warning_for_unrecognized_root_account(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $goodLeaf] = $this->fullySeededCoa($company);

        // A leaf account whose root_id points at something that is NOT one
        // of the five known root accounts -- e.g. a renamed/orphaned root.
        $orphanRoot = Account::factory()->create(['company_id' => $company->id, 'name' => 'Old Root', 'is_group' => true]);
        $badLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Orphaned Leaf',
            'parent_id' => $orphanRoot->id,
            'root_id' => $orphanRoot->id,
        ]);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 10,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        // One classifiable entry and one that will be skipped.
        $this->makeJournalEntry($company, $branch, $goodLeaf, $transaction->id);
        $this->makeJournalEntry($company, $branch, $badLeaf, $transaction->id);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Journal entry excluded from ledger: account root_id did not match any of the five known root accounts.',
                \Mockery::on(function (array $context) use ($company, $transaction, $badLeaf) {
                    return $context['company_id'] === $company->id
                        && $context['transaction_id'] === $transaction->id
                        && $context['account_id'] === $badLeaf->id;
                })
            );

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));

        $response->assertOk();
        // The good entry still renders...
        $response->assertSee($goodLeaf->name);
        // ...the orphaned one is excluded from the table...
        $response->assertDontSee($orphanRoot->name);
        $response->assertDontSee($badLeaf->name);
        // ...and the drop is visible instead of silent (see the comment on
        // the previous test for why this is a body assertion, not a
        // post-response session assertion, now that this is ->now()).
        $response->assertSee('could not be classified');
        // FIX #4 (W0.3): this cause is an orphaned/renamed root_id, NOT a
        // missing root account, so the message must say so distinctly from
        // the "chart of accounts incomplete" wording used above.
        $response->assertSee('orphaned or renamed root account');
        $response->assertDontSee('chart of accounts incomplete');
    }

    // ------------------------------------------------------------------
    // F. W0.3 fixes: paginated running balance must continue across pages,
    // pagination links must render, the warning banner must not leak into
    // the next unrelated request, and journal-entries.show must not 500 on
    // an orphaned (NULL transaction_id) entry.
    // ------------------------------------------------------------------

    /**
     * Bug: getJournalEntries() started the classified loop's running
     * balance at 0 for every page handed to it, instead of continuing from
     * where the previous page left off. A 20-entry transaction, 15 rows per
     * page, all under one Assets-rooted account with a fixed debit of 10
     * each (see makeJournalEntry()) produces running balances 10, 20, ...,
     * 150 on page 1 and should continue 160, 170, ..., 200 on page 2 — the
     * bug instead restarted page 2 at 10, 20.
     */
    public function test_journal_entries_index_running_balance_continues_across_pages(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->fullySeededCoa($company);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 200,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);
        }

        $this->actingAs($user);

        // Page 1: rows 1-15, running balance 10.00 .. 150.00. Must not
        // silently truncate to 15 of 20 with no way to reach the rest —
        // pagination links must be present.
        $page1 = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));
        $page1->assertOk();
        $page1->assertSee('150.00');
        $page1->assertDontSee('160.00');
        // Proof #2: pagination links render (Laravel's default paginator
        // view stamps this aria-label). Before the fix, index.blade.php
        // never called ->links(), so a 20-row transaction had no way to
        // reach rows 16-20 from the UI at all.
        $page1->assertSee('Pagination Navigation', false);

        // Page 2: rows 16-20. Before the fix these read 10.00, 20.00 (the
        // loop restarting at 0) instead of continuing the ledger.
        $page2 = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id, 'page' => 2]));
        $page2->assertOk();
        $page2->assertSee('160.00');
        $page2->assertSee('170.00');
        $page2->assertSee('200.00');
        $page2->assertDontSee('No journal entries found.');
    }

    /**
     * Bug: both suppression sites in getJournalEntries() used
     * session()->flash(), which survives into the request AFTER the one
     * that follows it (Laravel's flash aging: set now, visible this
     * request AND the next one) — so the "chart of accounts incomplete"
     * banner could appear on a completely unrelated subsequent page. Fixed
     * by switching to session()->now(), which is visible only for the
     * request that set it.
     */
    public function test_journal_entries_warning_banner_does_not_leak_into_next_request(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $goodLeaf] = $this->fullySeededCoa($company);

        $orphanRoot = Account::factory()->create(['company_id' => $company->id, 'name' => 'Old Root', 'is_group' => true]);
        $badLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Orphaned Leaf',
            'parent_id' => $orphanRoot->id,
            'root_id' => $orphanRoot->id,
        ]);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 10,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        $this->makeJournalEntry($company, $branch, $badLeaf, $transaction->id);

        $this->actingAs($user);

        // Request 1 triggers the banner. (Not asserting assertSessionHas()
        // here: session()->now() ages itself out at the END of THIS same
        // request's save(), before assertSessionHas() would ever inspect
        // it — assertSee() on the rendered body, which happens BEFORE
        // save(), is the only observable proof for a ->now() flash.)
        $first = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));
        $first->assertOk();
        $first->assertSee('could not be classified');

        // Request 2 is a totally unrelated page (a clean account with no
        // journal entries at all). It must NOT see the banner from request
        // 1 leaking through session flash aging.
        $second = $this->get(route('journal-entries.show', ['accountId' => $goodLeaf->id]));
        $second->assertOk();
        $second->assertDontSee('could not be classified');
    }

    /**
     * Bug: journal_entries/show.blade.php built
     * route('journal-entries.index', $entry->transaction_id) unconditionally.
     * P1 deliberately left journal_entries.transaction_id nullable, and
     * orphaned lines with a NULL transaction_id exist in prod — for those,
     * UrlGenerationException was thrown mid-render (a required route
     * parameter was null), 500-ing the whole ledger page for that account.
     */
    public function test_journal_entries_show_renders_ok_for_orphaned_null_transaction_id_entry(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->fullySeededCoa($company);

        // transaction_id deliberately NULL/omitted (orphan line).
        $this->makeJournalEntry($company, $branch, $leaf, null);

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.show', ['accountId' => $leaf->id]));

        $response->assertOk();
    }

    // ------------------------------------------------------------------
    // G. W0.4 FIX #1 (regression): the {transactionId} route param carries
    // no whereNumber() constraint, so "/journal-entries/abc" reaches
    // index() as a raw non-numeric string. sumRunningBalanceBeforeOffset()
    // type-hints `int $transactionId`; PHP rejects the raw string at the
    // call boundary with a TypeError before that method's `$offset <= 0`
    // guard ever runs -- so even page 1 500'd. Must render the same
    // empty-ledger 200 HEAD rendered for an unmatched id, not a 500.
    // ------------------------------------------------------------------

    public function test_journal_entries_index_returns_200_for_non_numeric_transaction_id(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $this->fullySeededCoa($company);

        $this->actingAs($user);

        $response = $this->get('/journal-entries/abc');

        $response->assertOk();
        $response->assertSee('No journal entries found.');
    }

    public function test_journal_entries_index_returns_200_for_non_numeric_transaction_id_page_two(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $this->fullySeededCoa($company);

        $this->actingAs($user);

        // Page 2 exercises the $offset > 0 branch of
        // sumRunningBalanceBeforeOffset() -- the TypeError fired on the raw
        // string regardless of $offset, so this must be fixed by the same
        // upstream cast, not by short-circuiting on $offset <= 0.
        $response = $this->get('/journal-entries/abc?page=2');

        $response->assertOk();
        $response->assertSee('No journal entries found.');
    }

    // ------------------------------------------------------------------
    // H. W0.4 FIX #2: getJournalEntries()'s classification loop dereferenced
    // $journalEntry->account->root_id with no null guard, even though the
    // sibling helper classifyEntryDelta() (used by
    // sumRunningBalanceBeforeOffset()) already had one. A journal entry
    // whose account belongs to another company (BelongsToCompany's global
    // scope excludes it once Auth::check() is true -- the same shape a
    // hard-deleted account leaves behind) yielded a null relation and
    // crashed the render loop. Must be treated exactly like an unrecognized
    // root_id: logged, counted, and folded into the existing banner.
    // ------------------------------------------------------------------

    public function test_journal_entries_index_handles_entry_with_orphaned_account(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $goodLeaf] = $this->fullySeededCoa($company);

        // An Account row that belongs to a DIFFERENT company. Once
        // Auth::check() is true, Account's BelongsToCompany global scope
        // excludes it from every query issued as $user -- including the
        // eager-loaded `account` relation on the journal entry below --
        // reproducing the same "account_id survives, the row it named does
        // not" shape a hard-deleted account leaves behind.
        $otherCompany = Company::factory()->create();
        $orphanedAccount = Account::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Account',
        ]);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 10,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        $this->makeJournalEntry($company, $branch, $goodLeaf, $transaction->id);
        $this->makeJournalEntry($company, $branch, $orphanedAccount, $transaction->id);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Journal entry excluded from ledger: account is missing (deleted or out of tenant scope).',
                \Mockery::on(function (array $context) use ($company, $transaction, $orphanedAccount) {
                    return $context['company_id'] === $company->id
                        && $context['transaction_id'] === $transaction->id
                        && $context['account_id'] === $orphanedAccount->id;
                })
            );

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));

        // Must not 500 with "Attempt to read property 'root_id' on null".
        $response->assertOk();
        // The good entry still renders...
        $response->assertSee($goodLeaf->name);
        // ...and the drop is visible via the same banner an unrecognized
        // root_id already used.
        $response->assertSee('could not be classified');
        $response->assertSee('orphaned or renamed root account');
    }

    /**
     * W0.4 LEAD (G13): the third caller, JournalEntryController::show(), in the
     * same orphaned-account scenario the two tests above cover for index() and
     * InvoiceController::showDetails().
     *
     * show() is structurally immune to the null-account dereference the other
     * two hit, and this test pins WHY rather than pretending otherwise: it
     * filters `->where('account_id', $accountId)` after an
     * `Account::findOrFail($accountId)` that runs under the same
     * BelongsToCompany global scope, so the orphaned account is a 404 at the
     * front door and can never reach the classification loop. Both halves are
     * asserted, because "show() cannot 500 here" is only meaningful alongside
     * "and this is the status it returns instead".
     */
    /**
     * W0.4 LEAD: the $startingUnclassifiedCount seam shipped untested.
     *
     * sumRunningBalanceBeforeOffset() returns ['balance' => float,
     * 'unclassified' => int] and index() feeds that count into
     * getJournalEntries()'s third parameter, so an unclassifiable row on an
     * EARLIER page still surfaces in the banner on the page the user is
     * actually looking at. Nothing guarded that: reverting
     * sumRunningBalanceBeforeOffset() to a bare float, or dropping
     * getJournalEntries()'s third parameter, left the whole suite green
     * while prior-page drops went silent again -- and a silently-dropped row
     * inside a double-entry ledger is exactly the wrong-but-plausible
     * failure the banner exists to prevent.
     *
     * Fixture: 16 entries, one of them (on page 1) pointing at an
     * out-of-tenant account. Page 2 holds a single, perfectly classifiable
     * entry, so the banner it renders can ONLY have come from the seeded
     * prior-page count.
     */
    public function test_journal_entries_index_page_two_banner_counts_prior_page_unclassified_entry(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->fullySeededCoa($company);

        $otherCompany = Company::factory()->create();
        $orphanedAccount = Account::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Account',
        ]);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 160,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);

        // Entries 1-14: good. Entry 15: orphaned (still page 1, since
        // perPage is 15). Entry 16: good, and the only row on page 2.
        for ($i = 0; $i < 14; $i++) {
            $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);
        }
        $this->makeJournalEntry($company, $branch, $orphanedAccount, $transaction->id);
        $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);

        $this->actingAs($user);

        $page2 = $this->get(route('journal-entries.index', [
            'transactionId' => $transaction->id,
            'page' => 2,
        ]));

        $page2->assertOk();
        // Page 2's own single entry classifies fine, so this banner is
        // entirely the seeded prior-page count.
        $page2->assertSee('1 entry could not be classified');
        $page2->assertSee('orphaned or renamed root account');

        // And the running balance still continues correctly across the page
        // boundary with the orphan contributing 0: 14 good rows on page 1 at
        // 10.00 each = 140.00, so page 2's single row reads 150.00.
        $page2->assertSee('150.00');
    }

    public function test_journal_entries_show_handles_orphaned_account(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $goodLeaf] = $this->fullySeededCoa($company);

        $otherCompany = Company::factory()->create();
        $orphanedAccount = Account::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Account',
        ]);

        $this->makeJournalEntry($company, $branch, $goodLeaf);
        $this->makeJournalEntry($company, $branch, $orphanedAccount);

        $this->actingAs($user);

        // The in-scope account's ledger still renders while an out-of-tenant
        // entry sits in the same company's journal_entries table.
        $this->get(route('journal-entries.show', ['accountId' => $goodLeaf->id]))->assertOk();

        // ...and the orphaned account itself is refused at findOrFail(), not
        // 500'd inside the classification loop.
        $this->get(route('journal-entries.show', ['accountId' => $orphanedAccount->id]))->assertNotFound();
    }

    public function test_invoice_show_details_handles_entry_with_orphaned_account(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $goodLeaf] = $this->fullySeededCoa($company);

        $otherCompany = Company::factory()->create();
        $orphanedAccount = Account::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Account',
        ]);

        $agentType = \App\Models\AgentType::create(['name' => 'Test Type ' . uniqid()]);
        $agent = \App\Models\Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'type_id' => $agentType->id,
        ]);
        $client = \App\Models\Client::factory()->create(['agent_id' => $agent->id]);
        $invoice = Invoice::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
        ]);

        // InvoiceController::showDetails() pulls journal entries by
        // invoice_id (not scoped to a single account_id like ::show()),
        // so it can legitimately encounter a mix of accounts, including an
        // orphaned one.
        JournalEntry::create([
            'name' => 'Test entry',
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'account_id' => $goodLeaf->id,
            'branch_id' => $branch->id,
            'transaction_date' => now(),
            'description' => 'Test entry',
            'debit' => 10,
            'credit' => 0,
            'balance' => 10,
        ]);
        JournalEntry::create([
            'name' => 'Test entry',
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'account_id' => $orphanedAccount->id,
            'branch_id' => $branch->id,
            'transaction_date' => now(),
            'description' => 'Test entry',
            'debit' => 10,
            'credit' => 0,
            'balance' => 10,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('invoice.details', [
            'companyId' => $company->id,
            'invoiceNumber' => $invoice->invoice_number,
        ]));

        // Must not 500 with "Attempt to read property 'root_id' on null".
        $response->assertOk();
    }

    // ------------------------------------------------------------------
    // I. W0.4 FIX #3: the fail-safe paths inside getJournalEntries() (a
    // partially-seeded COA, and the case where every entry handed to a
    // page failed classification) called $paginator->setCollection(empty)
    // but left the ORIGINAL paginate() total()/lastPage() in place -- a
    // 20-entry transaction on a broken COA rendered "1 2" pagination links
    // over a table with nothing in it. Must return an honest,
    // zero-total/one-page paginator instead.
    // ------------------------------------------------------------------

    public function test_get_journal_entries_returns_honest_empty_paginator_for_partially_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->partiallySeededCoa($company);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 200,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        for ($i = 0; $i < 20; $i++) {
            $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);
        }

        $this->actingAs($user);

        $paginator = JournalEntry::where('transaction_id', $transaction->id)->orderBy('id')->paginate(15);
        // Sanity: the ORIGINAL query already sees 20 rows / 2 pages --
        // proof this isn't just an empty-input no-op.
        $this->assertSame(20, $paginator->total());

        $result = app(JournalEntryController::class)->getJournalEntries($paginator);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->total());
        $this->assertSame(1, $result->lastPage());
        $this->assertFalse($result->hasPages());
    }

    public function test_get_journal_entries_returns_honest_empty_paginator_when_every_entry_is_unclassified(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $goodLeaf] = $this->fullySeededCoa($company);

        $orphanRoot = Account::factory()->create(['company_id' => $company->id, 'name' => 'Old Root', 'is_group' => true]);
        $badLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Orphaned Leaf',
            'parent_id' => $orphanRoot->id,
            'root_id' => $orphanRoot->id,
        ]);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 200,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        // Every entry on this page references the orphaned root -- none
        // are classifiable.
        for ($i = 0; $i < 20; $i++) {
            $this->makeJournalEntry($company, $branch, $badLeaf, $transaction->id);
        }

        $this->actingAs($user);

        $paginator = JournalEntry::where('transaction_id', $transaction->id)->orderBy('id')->paginate(15);
        $this->assertSame(20, $paginator->total());

        $result = app(JournalEntryController::class)->getJournalEntries($paginator);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(0, $result->total());
        $this->assertSame(1, $result->lastPage());
    }

    public function test_journal_entries_index_does_not_render_stale_pagination_link_for_partially_seeded_coa(): void
    {
        [$user, $company] = $this->createCompanyOwner();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        [$assetsRoot, $leaf] = $this->partiallySeededCoa($company);

        $transaction = \App\Models\Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'transaction_type' => 'credit',
            'amount' => 200,
            'description' => 'Test transaction',
            'reference_type' => 'Invoice',
        ]);
        for ($i = 0; $i < 20; $i++) {
            $this->makeJournalEntry($company, $branch, $leaf, $transaction->id);
        }

        $this->actingAs($user);

        $response = $this->get(route('journal-entries.index', ['transactionId' => $transaction->id]));

        $response->assertOk();
        $response->assertSee('No journal entries found.');
        $response->assertDontSee('page=2');
    }
}
