<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\PostingEngineDisabledException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W0 kill-switch gate suite (mission brief: "WIRE THE KILL SWITCH" — today
 * config('accounting.engine.enabled') and companies.posting_engine_enabled are read by ZERO
 * executable code, so there is no rollback lever before P2 wires a feeder to PostingService).
 *
 * PostingService::post() is the gate's only enforcement point, but it is NOT the only write in
 * the class: PostingService::reverse() owns two writes of its own (the
 * `->update(['reversal_of_transaction_id' => ...])` and `->update(['posting_status' =>
 * 'reversed'])` calls) that never go through post(). Both land inside reverse()'s own
 * DB::transaction() closure, so a gate refusal from the internal
 * `$result = $this->post($reversalDraft, $userId);` call rolls them back too — see
 * PostingEngineDisabledException's docblock for the full chain (incl. repost()) and for the W0.4
 * mutation measurements behind the next paragraph.
 *
 * WHAT THESE TWO TESTS DO AND DO NOT CATCH — measured, not assumed. Reordering the
 * `posting_status` write within reverse()'s closure does NOT break them (that mutation leaves the
 * suite at 7 passed), because DB::transaction() rolls back by closure membership, not by order.
 * What test_engine_disabled_refuses_reverse_of_previously_posted_transaction() below actually
 * catches is that write being hoisted OUT of the closure to run BEFORE it opens, in autocommit:
 * that mutation turns exactly that one test red on the `posting_status` CAVEAT assertion.
 * test_engine_disabled_refuses_repost_of_previously_posted_transaction() stays GREEN under the
 * same mutation, because repost()'s own outer DB::transaction() catches it — so the repost() test
 * cannot, on its own, tell "reverse()'s closure protects these writes" apart from "repost()
 * happened to catch them anyway". Do not delete the reverse() test as redundant with it. Neither
 * test catches the write being hoisted to run AFTER reverse()'s closure returns; that arrangement
 * is gate-safe but crash-unsafe, and is recorded as a known gap in
 * PostingEngineDisabledException's docblock.
 */
class PostingEngineGateTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        // Defensive reset so config mutated by this file can never leak into another test in the
        // suite (Laravel's config() array is process-global for the duration of the test run) —
        // same pattern as AccountObserverGateTest.
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /** assertDatabaseCount() takes no where-clause filter, so scope the count here instead. */
    private function assertNoTransactionsOrJournalEntriesFor(int $companyId): void
    {
        $this->assertSame(
            0,
            DB::table('transactions')->where('company_id', $companyId)->count(),
            "Expected zero transactions rows for company #{$companyId} after a refused post()."
        );
        $this->assertSame(
            0,
            DB::table('journal_entries')->where('company_id', $companyId)->count(),
            "Expected zero journal_entries rows for company #{$companyId} after a refused post()."
        );
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function balancedDraft(Company $company, Branch $branch, Account $debitAccount, Account $creditAccount, float $amount = 25.000): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingEngineGateTest fixture document',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            idempotencyKey: 'test:gate:'.uniqid(),
        );
    }

    public function test_post_refuses_when_engine_globally_disabled(): void
    {
        config(['accounting.engine.enabled' => false]);

        $company = tap(Company::factory()->create(), fn (Company $c) => $c->forceFill(['posting_engine_enabled' => true])->save());
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount);

        $this->expectException(PostingEngineDisabledException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            // Belt-and-braces proof nothing leaked before the throw, scoped to this company.
            $this->assertNoTransactionsOrJournalEntriesFor($company->id);
        }
    }

    public function test_post_refuses_when_company_flag_disabled_even_if_engine_globally_enabled(): void
    {
        config(['accounting.engine.enabled' => true]);

        // Migration default: posting_engine_enabled = false.
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount);

        $this->expectException(PostingEngineDisabledException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertNoTransactionsOrJournalEntriesFor($company->id);
        }
    }

    public function test_post_refuses_when_company_cannot_be_resolved(): void
    {
        config(['accounting.engine.enabled' => true]);

        // A company id that resolves to nothing at all — DocumentDraft only requires an int, and
        // real feeders (queue jobs, webhooks) could hand this a stale/deleted id.
        // Company::find() returning null must refuse, never "skip the check". The gate runs
        // BEFORE any account/branch resolution, so this draft never needs real rows to exist —
        // and a nonexistent id (rather than deleting a real company, which the accounts FK would
        // reject anyway) is the cleanest way to prove that.
        $nonexistentCompanyId = (int) (DB::table('companies')->max('id') ?? 0) + 1_000_000;

        $draft = new DocumentDraft(
            companyId: $nonexistentCompanyId,
            branchId: 0,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingEngineGateTest: unresolvable company',
            lines: [
                new LineDraft(purposeCode: '', accountId: 1, side: 'debit', amount: 25.000, currency: 'KWD', originalAmount: 25.000, exchangeRate: 1.0, transactionType: 'TEST_DEBIT'),
                new LineDraft(purposeCode: '', accountId: 2, side: 'credit', amount: 25.000, currency: 'KWD', originalAmount: 25.000, exchangeRate: 1.0, transactionType: 'TEST_CREDIT'),
            ],
            idempotencyKey: 'test:gate:'.uniqid(),
        );

        $this->expectException(PostingEngineDisabledException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertNoTransactionsOrJournalEntriesFor($nonexistentCompanyId);
        }
    }

    /**
     * Positive control — without this, an inverted gate condition (`if (config(...)) { throw }`)
     * would pass the three negative tests above trivially by always throwing.
     *
     * Enables the company THROUGH `php artisan accounting:engine --enable` rather than
     * forceFill(), because the command (backed by ordinary mass-assignment via
     * Company::update()) is now the operator-facing thing under test — forceFill() bypasses
     * $fillable entirely and would not have caught the FACT 1 fake-lever bug this suite exists
     * to guard against.
     */
    public function test_post_succeeds_when_both_flags_are_enabled(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->assertTrue((bool) DB::table('companies')->where('id', $company->id)->value('posting_engine_enabled'));

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 25.000);

        $posted = app(PostingService::class)->post($draft);

        $this->assertTrue($posted->transaction->exists);
        $this->assertSame(1, DB::table('transactions')->where('company_id', $company->id)->count());
        $this->assertSame(2, DB::table('journal_entries')->where('company_id', $company->id)->count());
    }

    /**
     * FACT 1 regression guard (mission brief): before Company::$fillable/$casts carried
     * `posting_engine_enabled`, mass-assignment via $company->update([...]) silently no-op'd —
     * the DB column stayed at its previous value while the call reported success. This test
     * would FAIL on the pre-fix Company.php: $after would equal the pre-update value (true),
     * not false, and the subsequent post() call would succeed instead of refusing.
     */
    public function test_mass_assignment_update_actually_persists_the_flag(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $company->forceFill(['posting_engine_enabled' => true])->save();

        // The lever under test: an emergency rollback issued as ordinary mass-assignment, not
        // forceFill(). On the pre-fix model this silently no-ops and $company->posting_engine_enabled
        // reads back as unchanged.
        $company->update(['posting_engine_enabled' => false]);

        $raw = DB::table('companies')->where('id', $company->id)->value('posting_engine_enabled');
        $this->assertSame(0, (int) $raw, 'Mass-assignment update() must actually persist posting_engine_enabled=false to the DB column.');

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount);

        $this->expectException(PostingEngineDisabledException::class);

        try {
            app(PostingService::class)->post($draft);
        } finally {
            $this->assertNoTransactionsOrJournalEntriesFor($company->id);
        }
    }

    /**
     * Regression guard for the real invariant (see PostingEngineDisabledException's docblock):
     * reverse() owns two writes of its own that never go through post() — a
     * `Transaction::withoutGlobalScopes()->whereKey($result->transaction->id)->update([
     * 'reversal_of_transaction_id' => $posted->id])` on the NEW reversal transaction, and a
     * `->whereKey($posted->id)->update(['posting_status' => 'reversed'])` on the ORIGINAL — both
     * inside reverse()'s own DB::transaction() closure, which also wraps the internal
     * `$result = $this->post($reversalDraft, $userId);` call. What makes a refused reverse()
     * leave zero trace is that BOTH writes stay inside that one closure, not the order they run
     * in: W0.4 measured a reorder of the `posting_status` write within the closure and the suite
     * stayed at 7 passed. What this test DOES catch is that write being moved to run BEFORE the
     * closure opens, in autocommit or its own transaction — measured, that mutation turns exactly
     * this test red on the `posting_status` CAVEAT assertion below
     * ("-'posted' +'reversed'", "Tests: 1 failed, 6 passed (56 assertions)"). Every test above
     * this one calls post() directly, so none of them would notice it. What this test does NOT
     * catch is the same write moved to run AFTER the closure returns; that stays green because a
     * refusal propagates out of DB::transaction() before the hoisted write ever runs — see
     * PostingEngineDisabledException's docblock, which records it as a crash-safety gap rather
     * than a gate-safety one. (The `reversal_of_transaction_id` write specifically cannot be
     * moved above the internal post() call at all — its WHERE clause needs
     * `$result->transaction->id`, and `$result` does not exist until post() returns, so that
     * mutation fails with "Undefined variable $result" rather than producing a runnable build.)
     */
    public function test_engine_disabled_refuses_reverse_of_previously_posted_transaction(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount);

        // Post with the engine ENABLED first — reverse() needs a real posted transaction to act on.
        $posted = app(PostingService::class)->post($draft);
        $transactionId = $posted->transaction->id;

        $this->assertSame('posted', DB::table('transactions')->where('id', $transactionId)->value('posting_status'));
        $this->assertNull(DB::table('transactions')->where('id', $transactionId)->value('reversal_of_transaction_id'));

        $transactionsBefore = DB::table('transactions')->where('company_id', $company->id)->count();
        $journalEntriesBefore = DB::table('journal_entries')->where('company_id', $company->id)->count();

        // Disable the engine AFTER posting, BEFORE reversing.
        config(['accounting.engine.enabled' => false]);

        $this->expectException(PostingEngineDisabledException::class);

        try {
            app(PostingService::class)->reverse($posted->transaction, now(), null);
        } finally {
            $this->assertSame(
                $transactionsBefore,
                DB::table('transactions')->where('company_id', $company->id)->count(),
                'reverse() must not write any new transactions row when the engine is disabled.'
            );
            $this->assertSame(
                $journalEntriesBefore,
                DB::table('journal_entries')->where('company_id', $company->id)->count(),
                'reverse() must not write any new journal_entries rows when the engine is disabled.'
            );
            $this->assertSame(
                'posted',
                DB::table('transactions')->where('id', $transactionId)->value('posting_status'),
                "CAVEAT: the ORIGINAL transaction's posting_status must stay 'posted' — reverse()'s own "
                    .'update() must roll back along with the refused internal post() call.'
            );
            $this->assertNull(
                DB::table('transactions')->where('id', $transactionId)->value('reversal_of_transaction_id'),
                "CAVEAT: the ORIGINAL transaction's reversal_of_transaction_id must stay NULL — reverse()'s "
                    .'own update() must roll back along with the refused internal post() call.'
            );
        }
    }

    /**
     * Exercises the same two reverse()-owned writes as the test above, but through repost() —
     * NOT the same strength as that test, despite looking like it. repost() wraps its internal
     * `$this->reverse($old, $date, $userId, false);` call in its own outer DB::transaction()
     * closure, so even a mutation that hoisted reverse()'s two update() calls OUT of reverse()'s
     * own closure (the exact bug the reverse() test above is built to catch) would still land
     * inside repost()'s outer closure here and still roll back. This test therefore cannot tell
     * "reverse()'s own closure protects these writes" apart from "repost()'s outer transaction
     * happened to catch them anyway" — it proves a refused repost() leaves the ORIGINAL
     * transaction's posting_status/reversal_of_transaction_id untouched and writes nothing new,
     * but it does not, on its own, prove reverse() is safe when called directly. The reverse()
     * test above is what proves that.
     */
    public function test_engine_disabled_refuses_repost_of_previously_posted_transaction(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount);

        $posted = app(PostingService::class)->post($draft);
        $transactionId = $posted->transaction->id;

        $this->assertSame('posted', DB::table('transactions')->where('id', $transactionId)->value('posting_status'));
        $this->assertNull(DB::table('transactions')->where('id', $transactionId)->value('reversal_of_transaction_id'));

        $transactionsBefore = DB::table('transactions')->where('company_id', $company->id)->count();
        $journalEntriesBefore = DB::table('journal_entries')->where('company_id', $company->id)->count();

        $newDraft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 40.000);

        // Disable the engine AFTER posting, BEFORE reposting.
        config(['accounting.engine.enabled' => false]);

        $this->expectException(PostingEngineDisabledException::class);

        try {
            app(PostingService::class)->repost($posted->transaction, $newDraft, now(), null);
        } finally {
            $this->assertSame(
                $transactionsBefore,
                DB::table('transactions')->where('company_id', $company->id)->count(),
                'repost() must not write any new transactions row when the engine is disabled.'
            );
            $this->assertSame(
                $journalEntriesBefore,
                DB::table('journal_entries')->where('company_id', $company->id)->count(),
                'repost() must not write any new journal_entries rows when the engine is disabled.'
            );
            $this->assertSame(
                'posted',
                DB::table('transactions')->where('id', $transactionId)->value('posting_status'),
                "CAVEAT: the ORIGINAL transaction's posting_status must stay 'posted' — repost()'s internal "
                    .'reverse()->update() must roll back along with the refused internal post() call.'
            );
            $this->assertNull(
                DB::table('transactions')->where('id', $transactionId)->value('reversal_of_transaction_id'),
                "CAVEAT: the ORIGINAL transaction's reversal_of_transaction_id must stay NULL — repost()'s "
                    .'internal reverse()->update() must roll back along with the refused internal post() call.'
            );
        }
    }
}
