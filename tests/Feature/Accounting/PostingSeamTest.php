<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\MissingIdempotencyKeyException;
use App\Exceptions\Accounting\UnbalancedDocumentException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\AccountingTestCase;

/**
 * R3 route-to-legacy seam suite (user decision, 2026-08-26): {@see PostingSeam} is the ONLY
 * caller-facing entry point a feeder is allowed to use — it never calls {@see
 * \App\Services\Accounting\PostingService} directly.
 *
 * WHY THESE TESTS PROVE "engine called" / "engine NOT called" WITHOUT MOCKING PostingService:
 * PostingService is `final` (by design — see its own class docblock: "exactly one code path may
 * write journal_entries"), so it cannot be subclassed by Mockery/PHPUnit's mock generators at all
 * — that is a hard PHP language constraint, not a test-tooling gap, and this suite does not work
 * around it by weakening PostingService's `final` marker (out of scope for this task; PostingSeam
 * is additive plumbing in front of it). Every test below instead proves which path ran through
 * REAL, OBSERVABLE effects: whether `transactions`/`journal_entries` rows actually exist for the
 * company (only PostingService's own writes can create them) and whether the legacy closure's own
 * side effect (a captured-by-reference flag) fired — exactly the technique
 * `PostingEngineGateTest` (this same suite's sibling, covering PostingService's own gate) already
 * uses for the identical proof requirement. This is a stronger guarantee than a method-call spy
 * would give: it cannot pass on a broken engine that "looks called" but silently writes nothing.
 *
 * Test 6 (the race) needs one additional real mechanism, documented on that test itself: a
 * `Company::retrieved()` listener that makes the SECOND of two sequential `Company::find()` reads
 * (PostingSeam::isEnabledFor()'s own check, then PostingService::post()'s independent internal
 * gate) observe a flipped `posting_engine_enabled` — reproducing "the flag changed between the
 * seam's check and the engine's own check" without touching either production class.
 */
class PostingSeamTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        // Same defensive reset as PostingEngineGateTest — config() is process-global for the
        // duration of the test run.
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /** @return LineDraft[] */
    private function twoLines(Account $debitAccount, Account $creditAccount, float $debitAmount, float $creditAmount): array
    {
        return [
            new LineDraft(
                purposeCode: '',
                accountId: $debitAccount->id,
                side: 'debit',
                amount: $debitAmount,
                currency: 'KWD',
                originalAmount: $debitAmount,
                exchangeRate: 1.0,
                transactionType: 'TEST_DEBIT',
            ),
            new LineDraft(
                purposeCode: '',
                accountId: $creditAccount->id,
                side: 'credit',
                amount: $creditAmount,
                currency: 'KWD',
                originalAmount: $creditAmount,
                exchangeRate: 1.0,
                transactionType: 'TEST_CREDIT',
            ),
        ];
    }

    private function balancedDraft(
        Company $company,
        Branch $branch,
        Account $debitAccount,
        Account $creditAccount,
        float $amount = 25.000,
        ?string $idempotencyKey = null,
    ): DocumentDraft {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingSeamTest fixture document',
            lines: $this->twoLines($debitAccount, $creditAccount, $amount, $amount),
            idempotencyKey: $idempotencyKey ?? 'test:seam:'.uniqid(),
        );
    }

    /** Unbalanced on purpose (25.000 debit vs 20.000 credit) — trips PostingService step 4. */
    private function unbalancedDraft(Company $company, Branch $branch, Account $debitAccount, Account $creditAccount): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingSeamTest deliberately unbalanced fixture',
            lines: $this->twoLines($debitAccount, $creditAccount, 25.000, 20.000),
            idempotencyKey: 'test:seam:unbalanced:'.uniqid(),
        );
    }

    private function assertNoTransactionsOrJournalEntriesFor(int $companyId): void
    {
        $this->assertSame(
            0,
            DB::table('transactions')->where('company_id', $companyId)->count(),
            "Expected zero transactions rows for company #{$companyId}."
        );
        $this->assertSame(
            0,
            DB::table('journal_entries')->where('company_id', $companyId)->count(),
            "Expected zero journal_entries rows for company #{$companyId}."
        );
    }

    /**
     * (1) Both flags OFF: legacy closure runs; the engine is never reached; accounting.legacy_path
     * is logged with the right context.
     */
    public function test_both_flags_off_routes_to_legacy_and_never_touches_the_engine(): void
    {
        config(['accounting.engine.enabled' => false]);

        // Migration default: posting_engine_enabled = false — left untouched.
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, 'test:seam:off:'.uniqid());

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.off');

        $this->assertTrue($legacyCalled, 'The legacy closure must run when the engine is off.');
        $this->assertSame('legacy-ran', $result, "The seam must pass the legacy closure's return value through unchanged.");
        $this->assertNoTransactionsOrJournalEntriesFor($company->id);

        Log::shouldHaveReceived('info')->once()->with(
            'accounting.legacy_path',
            Mockery::on(fn (array $context) => $context['feeder'] === 'test.feeder.off'
                && $context['company_id'] === $company->id
                && $context['idempotency_key'] === $draft->idempotencyKey)
        );
    }

    /**
     * (2a) Global ON but the company flag OFF: still legacy — both flags must be true.
     */
    public function test_global_enabled_company_disabled_routes_to_legacy(): void
    {
        config(['accounting.engine.enabled' => true]);

        // Migration default: posting_engine_enabled = false — left untouched.
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, 'test:seam:mixeda:'.uniqid());

        $this->assertFalse(app(PostingSeam::class)->isEnabledFor($company->id));

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.mixed-a');

        $this->assertTrue($legacyCalled);
        $this->assertSame('legacy-ran', $result);
        $this->assertNoTransactionsOrJournalEntriesFor($company->id);
        Log::shouldHaveReceived('info')->once()->with('accounting.legacy_path', Mockery::type('array'));
    }

    /**
     * (2b) Global OFF but the company flag ON: still legacy — both flags must be true. Enables
     * the company through the real `accounting:engine --enable` operator command (mass-assignment
     * path), same convention as PostingEngineGateTest.
     */
    public function test_global_disabled_company_enabled_routes_to_legacy(): void
    {
        config(['accounting.engine.enabled' => false]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->assertTrue((bool) DB::table('companies')->where('id', $company->id)->value('posting_engine_enabled'));

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, 'test:seam:mixedb:'.uniqid());

        $this->assertFalse(app(PostingSeam::class)->isEnabledFor($company->id));

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.mixed-b');

        $this->assertTrue($legacyCalled);
        $this->assertSame('legacy-ran', $result);
        $this->assertNoTransactionsOrJournalEntriesFor($company->id);
        Log::shouldHaveReceived('info')->once()->with('accounting.legacy_path', Mockery::type('array'));
    }

    /**
     * (3) Both flags ON: PostingService::post() actually runs (proven by real transactions/
     * journal_entries rows), a PostedDocument comes back, and the legacy closure never runs.
     */
    public function test_both_flags_on_routes_to_the_engine_and_never_touches_legacy(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, 'test:seam:on:'.uniqid());

        $this->assertTrue(app(PostingSeam::class)->isEnabledFor($company->id));

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.on');

        $this->assertFalse($legacyCalled, 'The legacy closure must NOT run when both flags are on.');
        $this->assertInstanceOf(PostedDocument::class, $result);
        $this->assertSame($draft->idempotencyKey, $result->transaction->idempotency_key);
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(2, DB::table('journal_entries')->where('company_id', $company->id)->count());
    }

    /**
     * (4) Both flags ON but the draft carries no idempotency key: MissingIdempotencyKeyException,
     * thrown BEFORE PostingService::post() is ever called — nothing written, legacy not run.
     */
    public function test_both_flags_on_with_no_idempotency_key_throws_before_writing_anything(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        // Built directly (not via balancedDraft()'s helper, whose `??` gives every draft a
        // default key) so idempotencyKey is a genuine null — the exact branch under test.
        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingSeamTest missing-idempotency-key fixture',
            lines: $this->twoLines($debitAccount, $creditAccount, 25.000, 25.000),
            idempotencyKey: null,
        );

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $this->expectException(MissingIdempotencyKeyException::class);

        try {
            app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.missing-key');
        } finally {
            $this->assertFalse($legacyCalled, 'A missing idempotency key on the engine path must never fall back to legacy.');
            $this->assertNoTransactionsOrJournalEntriesFor($company->id);
        }
    }

    /**
     * (5) Both flags ON, the engine throws a genuine PostingException (UnbalancedDocumentException):
     * Log::critical, RETHROWN, legacy is NOT run as a fallback — an engine correctness failure
     * must stay loud, never silently degrade to double-posting via legacy.
     */
    public function test_engine_correctness_failure_is_logged_critical_and_rethrown_without_legacy_fallback(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->unbalancedDraft($company, $branch, $debitAccount, $creditAccount);

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $this->expectException(UnbalancedDocumentException::class);

        try {
            app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.unbalanced');
        } finally {
            $this->assertFalse($legacyCalled, 'An engine PostingException must never fall back to the legacy closure.');
            $this->assertNoTransactionsOrJournalEntriesFor($company->id);

            Log::shouldHaveReceived('critical')->once()->with(
                'accounting.engine_failure',
                Mockery::on(fn (array $context) => $context['feeder'] === 'test.feeder.unbalanced'
                    && $context['company_id'] === $company->id
                    && $context['idempotency_key'] === $draft->idempotencyKey
                    && $context['exception_class'] === UnbalancedDocumentException::class)
            );
        }
    }

    /**
     * (6) The race: both flags read ON by PostingSeam::isEnabledFor(), but by the time
     * PostingService::post() performs its OWN independent gate check a moment later, it observes
     * the company flag as OFF — the exact "flag flipped between check and post" window the R3
     * brief calls out explicitly. Must route to legacy with a WARNING (not a critical/rethrow),
     * since this is a timing accident, not an engine correctness failure.
     *
     * MECHANISM (see class docblock for why PostingService cannot be mocked): a `Company::
     * retrieved()` listener counts sequential reads of THIS company row and, on the SECOND read
     * only, flips `posting_engine_enabled` to false on the in-memory model — without persisting
     * anything to the DB. The first read is PostingSeam::isEnabledFor()'s own check (sees ON, real
     * DB state); the second is PostingService::post()'s independent internal
     * `Company::find($draft->companyId)` call — this is the only other Company::find() in the
     * call path, so "second read" unambiguously means "the engine's own gate". Laravel boots a
     * fresh Application (and therefore a fresh Model event dispatcher) for every test method, so
     * this listener cannot leak into any other test.
     */
    public function test_race_disabled_between_seam_check_and_engine_gate_routes_to_legacy_with_warning(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, 'test:seam:race:'.uniqid());

        $reads = 0;
        Company::retrieved(function (Company $retrieved) use (&$reads, $company) {
            if ((int) $retrieved->id !== $company->id) {
                return;
            }

            $reads++;

            if ($reads === 2) {
                // In-memory only — no ->save(), so the DB row (and every other reader of it)
                // still shows posting_engine_enabled = true throughout this test.
                $retrieved->posting_engine_enabled = false;
            }
        });

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.race');

        $this->assertSame(2, $reads, 'Expected exactly two Company reads: isEnabledFor(), then PostingService::post()\'s own gate.');
        $this->assertTrue($legacyCalled, 'The race must fall back to the legacy closure.');
        $this->assertSame('legacy-ran', $result);
        $this->assertNoTransactionsOrJournalEntriesFor($company->id);

        // The DB row itself was never touched — proof the "flip" was purely the in-memory second
        // read, not a real, persisted disable.
        $this->assertTrue((bool) DB::table('companies')->where('id', $company->id)->value('posting_engine_enabled'));

        Log::shouldHaveReceived('warning')->once()->with(
            'accounting.engine_disabled_race',
            Mockery::on(fn (array $context) => $context['feeder'] === 'test.feeder.race'
                && $context['company_id'] === $company->id
                && $context['idempotency_key'] === $draft->idempotencyKey)
        );
        Log::shouldNotHaveReceived('critical');
    }

    /**
     * (7) A real (non-simulated) flip via the operator command between two calls: the first call
     * posts through the engine; `accounting:engine --disable` runs; the second call — same seam
     * instance, same company — routes to legacy. Proves the seam re-checks the flag on every call
     * rather than caching a stale "enabled" decision.
     */
    public function test_disabling_via_artisan_between_two_calls_routes_the_second_call_to_legacy(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $seam = app(PostingSeam::class);

        $draft1 = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, 'test:seam:flip1:'.uniqid());
        $legacy1Called = false;
        $result1 = $seam->post($draft1, function () use (&$legacy1Called) {
            $legacy1Called = true;

            return 'legacy-1';
        }, 'test.feeder.flip');

        $this->assertFalse($legacy1Called, 'First call (engine ON) must not run the legacy closure.');
        $this->assertInstanceOf(PostedDocument::class, $result1);
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());

        Artisan::call('accounting:engine', ['company' => $company->id, '--disable' => true]);
        $this->assertFalse((bool) DB::table('companies')->where('id', $company->id)->value('posting_engine_enabled'));

        $draft2 = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 30.000, 'test:seam:flip2:'.uniqid());
        $legacy2Called = false;
        $result2 = $seam->post($draft2, function () use (&$legacy2Called) {
            $legacy2Called = true;

            return 'legacy-2';
        }, 'test.feeder.flip');

        $this->assertTrue($legacy2Called, 'Second call (engine OFF after --disable) must run the legacy closure.');
        $this->assertSame('legacy-2', $result2);

        // Still exactly one transaction row — the second call never reached the engine.
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(2, DB::table('journal_entries')->where('company_id', $company->id)->count());
    }

    /**
     * (8) W1.1 fix, S1: the OFF path must recognise that the ENGINE already posted this exact
     * `(company_id, idempotency_key)` pair before a kill-switch flip (company flag ON -> a
     * document posts through the engine -> company flag flipped OFF -> a retry with the SAME
     * idempotency key must NOT run legacy, which has no idempotency protection of its own and
     * would double-post the same real-world event). Must log
     * `accounting.legacy_skip_already_posted` at WARNING and return `null` — never the legacy
     * closure's return value.
     */
    public function test_off_path_skips_legacy_when_the_engine_already_posted_this_idempotency_key(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $sharedKey = 'test:seam:s1:'.uniqid();
        $seam = app(PostingSeam::class);

        // First call: engine ON — posts for real.
        $draftOn = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, $sharedKey);
        $onResult = $seam->post($draftOn, fn () => 'legacy-should-not-run', 'test.feeder.s1');
        $this->assertInstanceOf(PostedDocument::class, $onResult);
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());

        // Kill-switch flip: company's own flag goes OFF; global flag stays ON.
        Artisan::call('accounting:engine', ['company' => $company->id, '--disable' => true]);
        $this->assertFalse($seam->isEnabledFor($company->id));

        // Second call: SAME idempotency key, now routes to the OFF branch.
        $draftOff = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000, $sharedKey);

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = $seam->post($draftOff, $legacy, 'test.feeder.s1');

        $this->assertFalse($legacyCalled, 'S1: the OFF path must never run legacy for a key the engine already posted.');
        $this->assertNull($result, 'S1: the OFF path must return null instead of the legacy closure\'s return value in this case.');
        $this->assertSame(
            1,
            DB::table('transactions')->where('company_id', $company->id)->count(),
            'No second (legacy) transaction may be written for a key the engine already posted.'
        );

        Log::shouldHaveReceived('warning')->once()->with(
            'accounting.legacy_skip_already_posted',
            Mockery::on(fn (array $context) => $context['feeder'] === 'test.feeder.s1'
                && $context['company_id'] === $company->id
                && $context['idempotency_key'] === $sharedKey
                && $context['transaction_id'] === $onResult->transaction->id)
        );
    }

    /**
     * (9) W1.1 fix, C4: a caller-supplied `companyId <= 0` (an unresolvable company) while the
     * GLOBAL flag reads ON must not look identical, in the logs, to an ordinary flag-disabled
     * decision. Logged separately, at ERROR, as `accounting.company_unresolvable`. Routing itself
     * is unchanged: legacy still runs, matching HEAD's own tolerance for this case.
     */
    public function test_unresolvable_company_id_logs_an_error_but_still_routes_to_legacy(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        // companyId = 0 is deliberately unresolvable — reproduces W1's own chat feeder defect
        // ((int) $taskBranch?->company_id casting a null branch's company id to 0).
        $draft = new DocumentDraft(
            companyId: 0,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingSeamTest C4 fixture',
            lines: $this->twoLines($debitAccount, $creditAccount, 25.000, 25.000),
            idempotencyKey: 'test:seam:c4:'.uniqid(),
        );

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $result = app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.c4');

        $this->assertTrue($legacyCalled, 'C4: an unresolvable company must still fall back to legacy — routing is unchanged.');
        $this->assertSame('legacy-ran', $result);

        Log::shouldHaveReceived('error')->once()->with(
            'accounting.company_unresolvable',
            Mockery::on(fn (array $context) => $context['feeder'] === 'test.feeder.c4'
                && $context['idempotency_key'] === $draft->idempotencyKey)
        );
        Log::shouldHaveReceived('info')->once()->with('accounting.legacy_path', Mockery::type('array'));
    }

    /**
     * (10) W1.1 fix, S3: `MissingIdempotencyKeyException` must be logged to
     * `accounting.engine_failure` at CRITICAL — the same channel/shape every other engine-path
     * failure uses — before being thrown, not silently skip that channel.
     */
    public function test_missing_idempotency_key_logs_engine_failure_before_throwing(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingSeamTest S3 fixture',
            lines: $this->twoLines($debitAccount, $creditAccount, 25.000, 25.000),
            idempotencyKey: null,
        );

        Log::spy();

        $legacyCalled = false;
        $legacy = function () use (&$legacyCalled) {
            $legacyCalled = true;

            return 'legacy-ran';
        };

        $this->expectException(MissingIdempotencyKeyException::class);

        try {
            app(PostingSeam::class)->post($draft, $legacy, 'test.feeder.s3');
        } finally {
            $this->assertFalse($legacyCalled, 'A missing idempotency key on the engine path must never fall back to legacy.');
            $this->assertNoTransactionsOrJournalEntriesFor($company->id);

            Log::shouldHaveReceived('critical')->once()->with(
                'accounting.engine_failure',
                Mockery::on(fn (array $context) => $context['feeder'] === 'test.feeder.s3'
                    && $context['company_id'] === $company->id
                    && $context['idempotency_key'] === null
                    && $context['exception_class'] === MissingIdempotencyKeyException::class)
            );
        }
    }
}
