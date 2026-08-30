<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Http\Controllers\AgentController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingSeam;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\AccountingTestCase;

/**
 * KEY: salary. Cuts AgentController::update()'s salary-posting block onto {@see PostingSeam}
 * (R3 route-to-legacy decision). Read AgentController::update() in full before touching this
 * file — the legacy closure inside it must stay byte-for-byte HEAD parity.
 *
 * HEAD DEFECT preserved on purpose (doc 11 R2, "one-sided salary" — see the W1 table row
 * `AgentController:332 (one-sided salary)`): the legacy write is a single debit leg with no
 * offsetting credit anywhere, and `journal_entries.balance` is always written as
 * `$salaryExpenseAccount->balance ?? 0` even though `accounts` has no `balance` column (only
 * actual_balance/opening_balance/budget_balance/balance_must_be) and Account has no `balance`
 * accessor either — so it is always literally `0`. test_off_path_matches_head_parity_including_
 * the_balance_0_defect pins this EXACTLY as it is, not as it should be: the strangler contract
 * (R3) is OFF-path byte-for-byte parity with HEAD, not a place to sneak a fix in.
 *
 * W1.2 fix (this round): every ON-path test now uses REAL `CoaSeeder::run()` +
 * `(new SystemAccountsSeeder())->run()` instead of a hand-inserted `system_accounts` row — the
 * W1.1 lead report is explicit that hand-inserted rows hid two HIGH regressions (R1, R2) because
 * no test in this file ever varied `name`/`branch_id` or exercised a cross-company branch move.
 * `CoaSeeder` also supplies the real 'Agent Salaries' leaf the OFF-path legacy closure looks up
 * by name, so the OFF-path tests below no longer hand-create that Account either — they read it
 * back from the real chart instead.
 *
 * This class extends AccountingTestCase (C1 trial-balance invariant on tearDown) but only calls
 * trackCompanyForInvariants() from the ON-path tests that actually post a balanced document. The
 * legacy/OFF-path company is deliberately NOT tracked: the one-sided entry it produces is a
 * known, preserved HEAD defect that would trip assertLedgerBalanced() by design (real
 * transaction_id, debit != credit), same reason CheckMyFatoorahPaymentsLedgerBalanceTest (the
 * other named one-sided W1 feeder) extends plain Tests\TestCase instead.
 */
class AgentControllerSalaryPostingTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        // W1.1 fix (salary feeder round): several tests below freeze time via
        // Carbon::setTestNow() to make the new per-change idempotency key
        // deterministic — Carbon's test-now is process-global, same class of
        // leak-across-tests risk as the config() reset above.
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Creates a company with a REAL chart of accounts (CoaSeeder), one branch, and one salaried
     * agent. SystemAccountsSeeder is NOT run here — callers that need the ON path resolvable
     * call it themselves (after every company under test exists, since it maps every company in
     * one pass) — see the class docblock.
     *
     * @return array{0: Company, 1: Branch, 2: Agent}
     */
    private function makeCompanyBranchAgent(int $initialSalary = 0, ?string $name = null): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);
        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'salary' => $initialSalary,
        ]);

        if ($name !== null) {
            $agent->update(['name' => $name]);
        }

        return [$company, $branch, $agent];
    }

    /**
     * A second, unrelated company + branch (own CoaSeeder chart) for the cross-company
     * branch-move tests — R1's exact reproduction shape (lead report: "a cross-company branch
     * move posts into the OLD tenant and evaluates the OLD company's kill-switch").
     *
     * @return array{0: Company, 1: Branch}
     */
    private function makeExtraCompanyBranch(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        return [$company, $branch];
    }

    private function agentSalariesLeaf(Company $company): Account
    {
        return Account::where('company_id', $company->id)
            ->where('name', 'Agent Salaries')
            ->firstOrFail();
    }

    private function resolvedAccountId(Company $company, string $purposeCode): ?int
    {
        return DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', $purposeCode)
            ->value('account_id');
    }

    private function updateRequest(Agent $agent, float $salary): Request
    {
        return Request::create('/agents/'.$agent->id, 'PUT', [
            'name' => $agent->name,
            'email' => $agent->email,
            'password' => 'Secret123!',
            'salary' => $salary,
        ]);
    }

    private function crossCompanyUpdateRequest(Agent $agent, Branch $newBranch, string $newName, float $newSalary): Request
    {
        return Request::create('/agents/'.$agent->id, 'PUT', [
            'name' => $newName,
            'email' => $agent->email,
            'password' => 'Secret123!',
            'branch_id' => $newBranch->id,
            'salary' => $newSalary,
        ]);
    }

    /**
     * W1.1 fix (salary feeder round, S1): the key is now scoped to the (old, new) amount
     * pair plus a to-the-second timestamp, not the calendar month — see
     * AgentController::update()'s own S1 comment for why. Callers MUST freeze time with
     * Carbon::setTestNow() before invoking the controller and pass that same instant here.
     */
    private function expectedIdempotencyKey(Agent $agent, float $oldSalary, float $newSalary, \DateTimeInterface $at): string
    {
        return sprintf(
            'agent:salary:%d:%s->%s:%s',
            $agent->id,
            number_format($oldSalary, 3, '.', ''),
            number_format($newSalary, 3, '.', ''),
            $at->format('Y-m-d H:i:s')
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // OFF path — HEAD parity, byte-for-byte, including the balance=0 defect.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Prove red by revert: reverting AgentController::update() to call
     * JournalEntry::create()/Transaction::create() directly (no seam) makes this pass
     * identically — this test pins CURRENT (pre- and post-seam-cutover) OFF-path behaviour,
     * not a new one.
     */
    public function test_off_path_matches_head_parity_including_the_balance_0_defect(): void
    {
        config(['accounting.engine.enabled' => false]);
        // Migration default: posting_engine_enabled = false — left untouched.

        [$company, $branch, $agent] = $this->makeCompanyBranchAgent(0);
        $salaryExpenseAccount = $this->agentSalariesLeaf($company);

        $request = $this->updateRequest($agent, 1500.000);

        app(AgentController::class)->update($request, $agent->id);

        $transaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('entity_type', 'agent')
            ->where('entity_id', $agent->id)
            ->first();

        $this->assertNotNull($transaction, 'Legacy path must still write the transactions header exactly as HEAD does.');
        $this->assertSame('debit', $transaction->transaction_type);
        $this->assertSame('Payment', $transaction->reference_type);
        $this->assertEquals(1500.000, (float) $transaction->amount);

        $line = DB::table('journal_entries')
            ->where('transaction_id', $transaction->id)
            ->first();

        $this->assertNotNull($line, 'Legacy path must still write the one-sided journal_entries line exactly as HEAD does.');
        $this->assertSame($salaryExpenseAccount->id, $line->account_id);
        $this->assertEquals(1500.000, (float) $line->debit);
        $this->assertEquals(0, (float) $line->credit);
        $this->assertEquals(
            0,
            (float) $line->balance,
            'HEAD DEFECT pinned on purpose: accounts has no balance column, so $salaryExpenseAccount->balance ?? 0 has always written literal 0. OFF path must stay byte-for-byte HEAD parity, not be quietly corrected.'
        );

        // Only ONE line exists — the legacy path is one-sided by construction (the W1 defect),
        // not merely "credit missing in this test fixture".
        $this->assertSame(
            1,
            DB::table('journal_entries')->where('transaction_id', $transaction->id)->count(),
            'The legacy salary entry must remain one-sided (doc 11 R2) — this is the defect the engine path fixes, not something to preserve as balanced.'
        );
    }

    /**
     * A same-salary update (no change) or a non-positive salary must never post anything, engine
     * on or off — matches HEAD's own `$request->salary != $oldSalary && $request->salary > 0`
     * guard, which the seam cutover must not weaken.
     */
    public function test_unchanged_salary_posts_nothing_on_either_path(): void
    {
        config(['accounting.engine.enabled' => true]);
        [$company, $branch, $agent] = $this->makeCompanyBranchAgent(1500);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $request = $this->updateRequest($agent, 1500.000); // same as $oldSalary

        app(AgentController::class)->update($request, $agent->id);

        $this->assertSame(0, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(0, DB::table('journal_entries')->where('company_id', $company->id)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // ON path — balanced document via purpose codes, correct leaves, idempotent.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Prove red by revert: reverting to the old direct JournalEntry::create() call makes this
     * fail (no system_accounts-resolved leaves are ever touched, and the entry stays
     * one-sided).
     */
    public function test_on_path_posts_a_balanced_document_via_the_correct_purpose_codes(): void
    {
        config(['accounting.engine.enabled' => true]);
        [$company, $branch, $agent] = $this->makeCompanyBranchAgent(0);
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $salaryExpenseLeafId = $this->resolvedAccountId($company, 'SALARY_EXPENSE');
        $agentPayableLeafId = $this->resolvedAccountId($company, 'SALARY_PAYABLE');
        $this->assertNotNull($salaryExpenseLeafId, 'SystemAccountsSeeder must resolve SALARY_EXPENSE (Agent Salaries) on a freshly-seeded chart.');
        $this->assertNotNull($agentPayableLeafId, 'SystemAccountsSeeder must resolve SALARY_PAYABLE (Salaries & Wages Payable) on a freshly-seeded chart.');

        // W1.3 (USER DECISION 2026-08-27); code amended W2.1 residual 6 (2240 -> 2201, 2240
        // collides with this same controller's own auto-numbered agent-profit leaves): pin the
        // EXACT leaf, not just "resolved to something" — reverting AgentController::update()'s
        // credit leg back to PAYABLE_CONTROL must fail this test (SystemAccountsSeeder still maps
        // SALARY_PAYABLE to 2201, but no journal_entries row would carry that account_id — the
        // credit would land on 2110 "Creditors" instead).
        $creditAccount = Account::find($agentPayableLeafId);
        $this->assertSame('2201', $creditAccount->code, 'Revert-to-2110 guard: the credited leaf must be code 2201, not the old PAYABLE_CONTROL leaf (2110 Creditors).');
        $this->assertSame('Salaries & Wages Payable', $creditAccount->name);
        $this->assertSame('Accrued Expenses', $creditAccount->parent->name);
        $this->assertSame('Liabilities', $creditAccount->root->name);

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
        $request = $this->updateRequest($agent, 2000.000);

        app(AgentController::class)->update($request, $agent->id);

        $idempotencyKey = $this->expectedIdempotencyKey($agent, 0.0, 2000.000, Carbon::now());

        $transaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($transaction, 'The engine path must post one transaction keyed on the new per-change agent:salary:{id}:{old}->{new}:{timestamp} idempotency key (S1 fix).');
        $this->assertSame('JV', $transaction->doc_type);

        $debitLine = DB::table('journal_entries')
            ->where('transaction_id', $transaction->id)
            ->where('account_id', $salaryExpenseLeafId)
            ->first();
        $creditLine = DB::table('journal_entries')
            ->where('transaction_id', $transaction->id)
            ->where('account_id', $agentPayableLeafId)
            ->first();

        $this->assertNotNull($debitLine, 'SALARY_EXPENSE must resolve to the leaf mapped in system_accounts.');
        $this->assertNotNull($creditLine, 'SALARY_PAYABLE must resolve to the leaf mapped in system_accounts — the feeder has no cash/bank leg to credit.');
        $this->assertEquals(2000.000, (float) $debitLine->debit);
        $this->assertEquals(0, (float) $debitLine->credit);
        $this->assertEquals(2000.000, (float) $creditLine->credit);
        $this->assertEquals(0, (float) $creditLine->debit);

        // S5: the debit leg must carry the same legacy 'expense' report-vocabulary category
        // the OFF-path single row writes (see test_off_path_matches_head_parity... and
        // test_on_and_off_paths_write_the_same_legacy_type_label_for_the_debit_leg below), so
        // screens filtering journal_entries.type see both paths alike.
        $this->assertSame('expense', $debitLine->type);

        // S2: posting succeeded, so the new salary must actually be persisted (proves the
        // seam-first/commit-together restructure didn't accidentally stop writing it on the
        // success path).
        $this->assertEquals(2000.000, (float) $agent->refresh()->salary);

        // Balanced — exactly the fix the one-sided legacy entry (test above) lacks.
        $this->assertEqualsWithDelta(
            0.0,
            (float) $debitLine->debit - (float) $creditLine->credit,
            0.0005
        );

        // No leaked write onto whatever a same-named legacy 'Agent Salaries' account would be —
        // proves the ON path never touches the Account::where('name', ...) legacy lookup
        // directly (it resolves the SAME leaf, but only via system_accounts/AccountResolver).
        $this->assertSame(
            2,
            DB::table('journal_entries')->where('transaction_id', $transaction->id)->count(),
            'The engine-posted document must be exactly two lines — one debit, one credit.'
        );
    }

    /**
     * Idempotency: presenting the SAME draft (same idempotencyKey, same amounts/lines) to the
     * seam a second time — the shape of a retried request — must not create a second
     * transaction or duplicate journal_entries rows. Proven directly at the seam/draft level
     * (the same technique PostingServiceBalanceTest::
     * test_post_returns_existing_document_on_idempotency_key_collision uses for the engine
     * itself), since AgentController::update() re-reads $oldSalary from the now-mutated agent
     * row on a genuine second HTTP call and therefore would not itself replay an identical
     * request — the property under test is that the feeder's key construction plus the engine
     * correctly dedupe a retried post, not the controller's unrelated $oldSalary guard (already
     * covered by test_unchanged_salary_posts_nothing_on_either_path above).
     *
     * This also doubles as the S1 fix's "exact duplicate submit within one second still
     * dedupes" proof: $retryDraft below reuses the SAME (old, new, second-granularity
     * timestamp) key the first call produced, on purpose — S1 only changes the key's
     * granularity from "per calendar month" to "per distinct change", it does not remove
     * de-duplication of a genuine same-instant resubmit of the same change.
     */
    public function test_on_path_is_idempotent_on_a_retried_identical_draft(): void
    {
        config(['accounting.engine.enabled' => true]);
        [$company, $branch, $agent] = $this->makeCompanyBranchAgent(0);
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $salaryExpenseLeafId = $this->resolvedAccountId($company, 'SALARY_EXPENSE');
        $agentPayableLeafId = $this->resolvedAccountId($company, 'SALARY_PAYABLE');

        Carbon::setTestNow(Carbon::parse('2026-08-12 09:30:00'));
        $request = $this->updateRequest($agent, 3000.000);

        // First call — through the real controller, exactly like the ON-path test above.
        app(AgentController::class)->update($request, $agent->id);

        $idempotencyKey = $this->expectedIdempotencyKey($agent, 0.0, 3000.000, Carbon::now());

        $transactionCountAfterFirst = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->count();
        $lineCountAfterFirst = DB::table('journal_entries')->where('company_id', $company->id)->count();

        $this->assertSame(1, $transactionCountAfterFirst);

        // Second, independently-constructed but IDENTICAL draft — same key, same amounts, same
        // purpose codes — presented straight to the seam, simulating a retried request.
        $retryDraft = new DocumentDraft(
            companyId: $company->id,
            branchId: $agent->branch_id,
            docType: 'JV',
            subType: 'AGENT_SALARY',
            docDate: now(),
            narration: 'Monthly salary adjustment for agent: '.$agent->name,
            lines: [
                new LineDraft(
                    purposeCode: 'SALARY_EXPENSE',
                    accountId: null,
                    side: 'debit',
                    amount: 3000.000,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: 3000.000,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_SALARY_EXPENSE',
                    description: 'Monthly salary adjustment for agent: '.$agent->name,
                ),
                new LineDraft(
                    purposeCode: 'SALARY_PAYABLE',
                    accountId: null,
                    side: 'credit',
                    amount: 3000.000,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: 3000.000,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_SALARY_PAYABLE',
                    description: 'Salary payable to agent: '.$agent->name,
                ),
            ],
            idempotencyKey: $idempotencyKey,
        );

        $legacyCalledOnRetry = false;
        $result = app(PostingSeam::class)->post(
            $retryDraft,
            function () use (&$legacyCalledOnRetry) {
                $legacyCalledOnRetry = true;
            },
            'agent.salary'
        );

        $this->assertFalse($legacyCalledOnRetry, 'Both flags are ON — the retried post must go through the engine, never the legacy closure.');
        $this->assertSame(
            $transactionCountAfterFirst,
            DB::table('transactions')->where('company_id', $company->id)->where('idempotency_key', $idempotencyKey)->count(),
            'A retried post with the same idempotency key must not create a second transaction.'
        );
        $this->assertSame(
            $lineCountAfterFirst,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            'A retried post with the same idempotency key must not duplicate journal_entries rows.'
        );
        $this->assertNotNull($result, 'The seam must still return the (pre-existing) PostedDocument on the dedupe path.');
        $this->assertNotNull($salaryExpenseLeafId);
        $this->assertNotNull($agentPayableLeafId);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Engine failure — loud, never silently downgraded to legacy.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * W1.2 fix (this round, task item "d"): REAL seeders first — the chart fully resolves both
     * SALARY_EXPENSE and SALARY_PAYABLE, exactly like production — and only THEN is the one
     * registry row under test deleted, reproducing "SystemAccountsSeeder hasn't been re-run
     * since this company was created / a chart edit removed a mapped leaf" deterministically,
     * without a hand-inserted partial system_accounts fixture masking the rest of the chart.
     *
     * Prove red by revert: if PostingSeam's catch(PostingException) branch were changed to
     * `return $legacy();` instead of rethrowing (the exact mutation PostingSeamTest's own M2
     * pins), this test goes red — Log::critical would never fire and the legacy one-sided entry
     * would land instead. Reverting AgentController::update() to the pre-S2 ordering (agent
     * update before the seam call, outside any transaction, with only the generic catch) makes
     * the `$agent->refresh()->salary` and specific-message assertions below fail while the
     * pre-existing Log::critical/row-count assertions still pass.
     */
    public function test_on_path_unmapped_purpose_after_real_seeders_rolls_back_and_reports_specific_error(): void
    {
        config(['accounting.engine.enabled' => true]);
        [$company, $branch, $agent] = $this->makeCompanyBranchAgent(0);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->assertNotNull($this->resolvedAccountId($company, 'SALARY_EXPENSE'), 'Sanity: real seeders must map SALARY_EXPENSE before we delete it on purpose.');

        // The ONE deliberate gap this test needs, carved out of an otherwise fully-mapped, real
        // chart — not a hand-built partial fixture.
        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SALARY_EXPENSE')
            ->delete();

        $transactionCountBefore = DB::table('transactions')->where('company_id', $company->id)->count();
        $lineCountBefore = DB::table('journal_entries')->where('company_id', $company->id)->count();

        Log::spy();

        Carbon::setTestNow(Carbon::parse('2026-08-15 08:00:00'));
        $request = $this->updateRequest($agent, 4000.000);
        $expectedIdempotencyKey = $this->expectedIdempotencyKey($agent, 0.0, 4000.000, Carbon::now());

        // AgentController::update() has a dedicated `catch (PostingException $e)` ahead of its
        // generic `catch (Exception $error)` (W1.1 fix, S2) — what this test proves is that
        // PostingSeam logged the failure LOUD (Log::critical, per R3), that neither the engine
        // nor the legacy closure left any partial or duplicate write behind, AND that the
        // salary field itself was rolled back with it, with a specific, referenceable error
        // surfaced to the user instead of the generic string every unrelated failure in this
        // method produces.
        $response = app(AgentController::class)->update($request, $agent->id);

        Log::shouldHaveReceived('critical')->once()->with(
            'accounting.engine_failure',
            Mockery::on(function (array $context) use ($company) {
                return $context['feeder'] === 'agent.salary'
                    && $context['company_id'] === $company->id
                    && $context['exception_class'] === UnmappedPurposeException::class;
            })
        );

        $this->assertSame(
            $transactionCountBefore,
            DB::table('transactions')->where('company_id', $company->id)->count(),
            'A genuine engine correctness failure must not leave a partial transactions row.'
        );
        $this->assertSame(
            $lineCountBefore,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            'A genuine engine correctness failure must NEVER fall back to the legacy one-sided write (R3: no silent double-post path).'
        );
        $this->assertNotNull($response, 'The outer controller catch still returns a redirect response — no uncaught 500.');

        // S2 (ordering): the rejected post must roll the salary field back WITH it — the field
        // must never be left changed with no journal entry to show for it.
        $this->assertEquals(
            0.0,
            (float) $agent->refresh()->salary,
            'S2 fix: a rejected engine post must roll the salary change back inside the same transaction, not leave it committed with the posting lost.'
        );

        // S2 (swallow): a specific, referenceable message — not the generic "Failed to update
        // agent" string every unrelated failure in this method already produces.
        $this->assertSame(
            sprintf(
                'Salary update failed: the accounting engine rejected this entry (%s). Reference: %s. No changes were saved.',
                'UnmappedPurposeException',
                $expectedIdempotencyKey
            ),
            session('error'),
            'S2 fix: the user-facing message must name the accounting failure class and the idempotency key, not a generic string.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // S1 — per-change idempotency key (not per calendar month).
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The OLD key ('agent:salary:{id}:{Y-m}') made a SECOND real salary change within the
     * same calendar month collide with the first under PostingSeam's own idempotency
     * handling and silently post nothing — a genuine regression against legacy (which always
     * posts every change). Prove red by revert: reverting the key back to the month-scoped
     * format makes the second `assertCount(2, ...)` below fail with 1, and the SUM debit
     * assertion fail by missing the second change's amount entirely.
     */
    public function test_two_distinct_salary_changes_in_the_same_calendar_month_each_post_their_own_document(): void
    {
        config(['accounting.engine.enabled' => true]);
        [$company, $branch, $agent] = $this->makeCompanyBranchAgent(500.000);
        $this->trackCompanyForInvariants($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $salaryExpenseLeafId = $this->resolvedAccountId($company, 'SALARY_EXPENSE');
        $agentPayableLeafId = $this->resolvedAccountId($company, 'SALARY_PAYABLE');

        // First change: 500 -> 900, on the 3rd of the month.
        Carbon::setTestNow(Carbon::parse('2026-08-03 09:00:00'));
        app(AgentController::class)->update($this->updateRequest($agent, 900.000), $agent->id);
        $agent->refresh();

        // Second, genuinely different change: 900 -> 1300, on the 21st of the SAME month.
        Carbon::setTestNow(Carbon::parse('2026-08-21 16:45:00'));
        app(AgentController::class)->update($this->updateRequest($agent, 1300.000), $agent->id);
        $agent->refresh();

        $transactions = DB::table('transactions')->where('company_id', $company->id)->get();

        $this->assertCount(
            2,
            $transactions,
            'S1 fix: two genuinely different salary changes within one calendar month must both post their own engine document — the old month-scoped key silently dropped the second one.'
        );

        $totalDebit = (float) DB::table('journal_entries')
            ->whereIn('transaction_id', $transactions->pluck('id'))
            ->where('account_id', $salaryExpenseLeafId)
            ->sum('debit');
        $totalCredit = (float) DB::table('journal_entries')
            ->whereIn('transaction_id', $transactions->pluck('id'))
            ->where('account_id', $agentPayableLeafId)
            ->sum('credit');

        $this->assertEquals(900.000 + 1300.000, $totalDebit, 'SUM debit across both engine documents must equal both salary changes, mirroring legacy behaviour (which always posts both).');
        $this->assertEquals(900.000 + 1300.000, $totalCredit);

        $this->assertEquals(1300.000, (float) $agent->salary, 'The final persisted salary must be the second change — both posts succeeded, so both persisted.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // S5 — ON path must carry the same legacy 'expense' type label as the OFF path.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * AccountingController::filterLedgers() and BankPaymentController filter
     * journal_entries.type on the legacy report vocabulary ('expense', 'receivable', …) — a
     * screen built against the OFF-path legacy row must see the same category once a company
     * flips to the ON path. Prove red by revert: dropping `ledgerType: 'expense'` from the
     * SALARY_EXPENSE LineDraft in AgentController::update() makes the ON assertion below fail
     * (the debit line's type reverts to the audit label 'AGENT_SALARY_EXPENSE') while the OFF
     * assertion, a genuinely different code path, keeps passing.
     */
    public function test_on_and_off_paths_write_the_same_legacy_type_label_for_the_debit_leg(): void
    {
        // OFF path.
        config(['accounting.engine.enabled' => false]);
        [$offCompany, , $offAgent] = $this->makeCompanyBranchAgent(0);

        app(AgentController::class)->update($this->updateRequest($offAgent, 1200.000), $offAgent->id);

        $offTransaction = DB::table('transactions')
            ->where('company_id', $offCompany->id)
            ->where('entity_type', 'agent')
            ->where('entity_id', $offAgent->id)
            ->first();
        $offLine = DB::table('journal_entries')->where('transaction_id', $offTransaction->id)->first();

        $this->assertSame('expense', $offLine->type);

        // ON path.
        config(['accounting.engine.enabled' => true]);
        [$onCompany, , $onAgent] = $this->makeCompanyBranchAgent(0);
        $this->trackCompanyForInvariants($onCompany->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $onCompany->id, '--enable' => true]);

        $salaryExpenseLeafId = $this->resolvedAccountId($onCompany, 'SALARY_EXPENSE');

        app(AgentController::class)->update($this->updateRequest($onAgent, 1200.000), $onAgent->id);

        $onDebitLine = DB::table('journal_entries')->where('account_id', $salaryExpenseLeafId)->first();

        $this->assertNotNull($onDebitLine);
        $this->assertSame('expense', $onDebitLine->type);
        $this->assertSame($offLine->type, $onDebitLine->type, 'ON and OFF paths must write the identical legacy type label for the debit leg (S5).');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W1.2 — R1: HEAD statement order restored (agent read AFTER $agent->update()).
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * R1 regression (W1.1 lead report): $agent->update() was moved AFTER the salary block, so
     * the legacy closure read the PRE-update name/branch_id/company. Reproduces the lead's own
     * probe shape exactly: a request that changes name + branch_id (to a branch in a DIFFERENT
     * company) + salary, flags OFF throughout. Prove red by revert: moving
     * `$agent->update($request->all())` back to after the salary block makes the transaction's
     * company_id/branch_id/description below revert to the OLD (pre-move) values — see this
     * file's own manual revert-and-rerun proof in the round's report.
     */
    public function test_off_path_legacy_write_uses_the_updated_name_and_the_new_post_move_company(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$oldCompany, $oldBranch, $agent] = $this->makeCompanyBranchAgent(500.000, name: 'OLD NAME');
        [$newCompany, $newBranch] = $this->makeExtraCompanyBranch();

        $request = $this->crossCompanyUpdateRequest($agent, $newBranch, 'NEW NAME', 900.000);

        app(AgentController::class)->update($request, $agent->id);

        $agent->refresh();
        $this->assertSame('NEW NAME', $agent->name, 'Sanity: the agent row itself must carry the new name.');
        $this->assertSame($newBranch->id, $agent->branch_id, 'Sanity: the agent row itself must carry the new branch.');

        $transaction = DB::table('transactions')
            ->where('entity_type', 'agent')
            ->where('entity_id', $agent->id)
            ->first();

        $this->assertNotNull($transaction, 'Legacy path must still write a transaction — the R1 fix must not stop it from posting at all.');
        $this->assertSame(
            $newCompany->id,
            $transaction->company_id,
            'R1: legacy must post into the NEW (post-move) tenant, not the OLD one — this is the exact regression the W1.1 lead reproduced.'
        );
        $this->assertSame($newBranch->id, $transaction->branch_id);
        $this->assertStringContainsString(
            'NEW NAME',
            $transaction->description,
            'R1: the legacy closure must read the UPDATED agent name, not the pre-update one.'
        );

        $this->assertSame(
            0,
            DB::table('transactions')->where('company_id', $oldCompany->id)->count(),
            'Nothing should ever land in the OLD (pre-move) tenant.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W1.2 — R1 (ON path): the NEW company's flag/branch govern the post, not the OLD one's.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Same cross-company move as the OFF-path test above, engine globally ON, but the two
     * companies' PER-COMPANY flags disagree — exactly the shape the task brief calls out: "set
     * old ON / new OFF -> legacy runs under NEW company". Proves the kill-switch is evaluated
     * against the company the agent belongs to AFTER $agent->update(), not before.
     */
    public function test_on_path_cross_company_move_old_flag_on_new_flag_off_runs_legacy_under_new_company(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$oldCompany, $oldBranch, $agent] = $this->makeCompanyBranchAgent(500.000, name: 'OLD NAME');
        [$newCompany, $newBranch] = $this->makeExtraCompanyBranch();

        Artisan::call('accounting:engine', ['company' => $oldCompany->id, '--enable' => true]);
        Artisan::call('accounting:engine', ['company' => $newCompany->id, '--disable' => true]);

        $request = $this->crossCompanyUpdateRequest($agent, $newBranch, 'NEW NAME', 900.000);
        app(AgentController::class)->update($request, $agent->id);

        $transaction = DB::table('transactions')
            ->where('entity_type', 'agent')
            ->where('entity_id', $agent->id)
            ->first();

        $this->assertNotNull($transaction, 'Legacy path must post — the NEW company (the one that now owns the agent) has its flag OFF.');
        $this->assertNull($transaction->idempotency_key, 'A legacy transaction never carries an idempotency_key — proves the ENGINE did not run, only legacy did.');
        $this->assertSame($newCompany->id, $transaction->company_id, 'R1: the kill-switch must be evaluated against the NEW (post-move) company, regardless of the OLD company being ON.');
        $this->assertSame($newBranch->id, $transaction->branch_id);

        $this->assertSame(
            0,
            DB::table('transactions')->where('company_id', $oldCompany->id)->count(),
            'The OLD company being ON must be irrelevant once the agent has moved — nothing should post under it.'
        );
    }

    /**
     * The inverse combination: "old OFF / new ON -> engine". The NEW company must have a real,
     * fully-seeded chart for the engine path to resolve both purpose codes.
     */
    public function test_on_path_cross_company_move_old_flag_off_new_flag_on_posts_via_the_engine_under_new_company(): void
    {
        config(['accounting.engine.enabled' => true]);

        [$oldCompany, $oldBranch, $agent] = $this->makeCompanyBranchAgent(500.000, name: 'OLD NAME');
        [$newCompany, $newBranch] = $this->makeExtraCompanyBranch();
        $this->trackCompanyForInvariants($newCompany->id);

        (new SystemAccountsSeeder())->run(); // maps every existing company, including both above.

        Artisan::call('accounting:engine', ['company' => $oldCompany->id, '--disable' => true]);
        Artisan::call('accounting:engine', ['company' => $newCompany->id, '--enable' => true]);

        $salaryExpenseLeafId = $this->resolvedAccountId($newCompany, 'SALARY_EXPENSE');
        $agentPayableLeafId = $this->resolvedAccountId($newCompany, 'SALARY_PAYABLE');
        $this->assertNotNull($salaryExpenseLeafId);
        $this->assertNotNull($agentPayableLeafId);

        Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00'));
        $request = $this->crossCompanyUpdateRequest($agent, $newBranch, 'NEW NAME', 900.000);
        app(AgentController::class)->update($request, $agent->id);

        $expectedIdempotencyKey = $this->expectedIdempotencyKey($agent, 500.0, 900.000, Carbon::now());

        $transaction = DB::table('transactions')
            ->where('company_id', $newCompany->id)
            ->where('idempotency_key', $expectedIdempotencyKey)
            ->first();

        $this->assertNotNull($transaction, 'R1: the engine path must post under the NEW (post-move) company once ITS flag is ON, regardless of the OLD company being OFF.');
        $this->assertSame($newBranch->id, $transaction->branch_id);

        $lines = DB::table('journal_entries')->where('transaction_id', $transaction->id)->get();
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta(0.0, (float) $lines->sum('debit') - (float) $lines->sum('credit'), 0.0005);

        $this->assertSame(
            0,
            DB::table('transactions')->where('company_id', $oldCompany->id)->count(),
            'The OLD company being OFF (and no longer owning the agent) must produce nothing.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // W1.2 — R2: HEAD statement order restored (both writes commit BEFORE the salary post).
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * R2 regression (W1.1 lead report): $user->update() was moved AFTER the salary block, so a
     * failing user write (duplicate email — update() does no validation and users.email is
     * unique) let a balanced engine document (or the legacy row) commit behind a generic
     * "Failed to update agent" flash. Both flag states are asserted: the fix must commit
     * NOTHING — no transaction, no journal entry, no salary change, no name change — before
     * $user->update() even has a chance to throw. Prove red by revert: moving $user->update()
     * (and $agent->update()) back to after the salary/posting block makes the tx/je-count
     * assertions below fail (a document is committed) while session('error') still happens to
     * read the generic string either way.
     */
    public function test_duplicate_email_user_update_commits_nothing_engine_off(): void
    {
        $this->assertDuplicateEmailUserUpdateCommitsNothing(false);
    }

    public function test_duplicate_email_user_update_commits_nothing_engine_on(): void
    {
        $this->assertDuplicateEmailUserUpdateCommitsNothing(true);
    }

    private function assertDuplicateEmailUserUpdateCommitsNothing(bool $engineOn): void
    {
        config(['accounting.engine.enabled' => $engineOn]);

        $otherUser = User::factory()->create(['email' => 'already-taken@example.com']);

        [$company, $branch, $agent] = $this->makeCompanyBranchAgent(500.000, name: 'ORIGINAL NAME');

        if ($engineOn) {
            (new SystemAccountsSeeder())->run();
            Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        }

        $transactionCountBefore = DB::table('transactions')->count();
        $journalCountBefore = DB::table('journal_entries')->count();

        $request = Request::create('/agents/'.$agent->id, 'PUT', [
            'name' => 'SHOULD NOT STICK',
            'email' => $otherUser->email, // duplicate -> QueryException on $user->update() (no validation, users.email is unique)
            'password' => 'Secret123!',
            'salary' => 900.000, // salary-changed, so this exercises the transactional salary block too
        ]);

        $response = app(AgentController::class)->update($request, $agent->id);

        $this->assertNotNull($response, 'The outer controller catch still returns a redirect response — no uncaught 500.');
        $this->assertSame(
            'Failed to update agent',
            session('error'),
            "R2/HEAD parity: a failing user write must fall into the GENERIC catch, never the PostingException-specific one — the engine/legacy post must not have run at all."
        );

        $this->assertSame(
            $transactionCountBefore,
            DB::table('transactions')->count(),
            'R2: nothing must be committed — the failing $user->update() must run (and roll everything back) BEFORE any posting happens, engine or legacy.'
        );
        $this->assertSame(
            $journalCountBefore,
            DB::table('journal_entries')->count(),
            'R2: no journal_entries row of any kind may exist behind the failed user update.'
        );

        $agent->refresh();
        $this->assertSame('ORIGINAL NAME', $agent->name, 'R2: the agent update must be rolled back together with the failed user update — same transaction.');
        $this->assertEquals(500.000, (float) $agent->salary, 'R2: the salary must be rolled back too — the whole request must leave state exactly as it was.');
    }
}
