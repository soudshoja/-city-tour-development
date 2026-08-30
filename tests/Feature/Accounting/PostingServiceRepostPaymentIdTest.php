<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\DuplicatePaymentReferenceException;
use App\Exceptions\Accounting\SupersededIdempotencyKeyException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * W2 build (D3 — orchestrator design call, R3 route-to-legacy cutover): transactions.payment_id
 * is carried ONLY by the ORIGINAL receipt document; reverse()/repost() headers leave payment_id
 * NULL and link to that original via reversal_of_transaction_id instead. Pins:
 *
 *   - post() writes transactions.payment_id from DocumentDraft::$paymentId on the ORIGINAL
 *     document that first sets it.
 *   - reverse()'s reversal-draft header carries payment_id = NULL (this was already correct
 *     before this fix round — reverse() never set DocumentDraft::$paymentId at all).
 *   - repost()'s REPLACEMENT draft's header ALSO carries payment_id = NULL, even when the
 *     caller's $new draft explicitly reuses the SAME payment_id $old carried — the natural
 *     caller shape ("same real-world payment, corrected"), and the exact shape that 1062'd on
 *     transactions_payment_id_reference_type_unique before this fix (reverse() never clears
 *     payment_id off $old, so $old keeps occupying that slot after being reversed).
 *   - A GENUINE collision on that index (two different documents, two different idempotency
 *     keys, the same payment_id + reference_type pair) surfaces as the new, typed
 *     DuplicatePaymentReferenceException — never a raw QueryException — because that shape is
 *     NOT a safe-to-recover idempotency race the way a same-key retry is.
 */
class PostingServiceRepostPaymentIdTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    /**
     * Set only by test_identical_key_and_payment_id_retry_returns_existing_document_not_an_exception()
     * — see that test's own FK NOTE and this class's tearDown() override for why the cleanup MUST
     * run after parent::tearDown()'s rollback, not inside the test method itself.
     */
    private ?int $residual2RaceWinnerId = null;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();

        // Residual 2 test cleanup, deliberately AFTER parent::tearDown() (RefreshDatabase's
        // rollback of the DEFAULT connection's transaction): the winner row was committed on a
        // genuinely separate connection, so RefreshDatabase's rollback cannot remove it — but
        // that same rollback is also what RELEASES the row lock this test's own post() call took
        // on it (via findByIdempotencyKey(forUpdate: true) / the soft-deleted-inclusive fallback's
        // lockForUpdate()). Deleting it any earlier, from inside the test method, deadlocked this
        // exact way the first time this test was written: SQLSTATE HY000 1205 "Lock wait timeout
        // exceeded" on the DELETE itself, waiting on a lock the test's own still-open transaction
        // was holding.
        if ($this->residual2RaceWinnerId !== null) {
            DB::connection('residual2_race')
                ->table('transactions')
                ->where('id', $this->residual2RaceWinnerId)
                ->delete();
            DB::purge('residual2_race');
            $this->residual2RaceWinnerId = null;
        }
    }

    /** @return array{company: Company, branch: Branch} */
    private function makeEnabledTenant(): array
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $this->trackCompanyForInvariants($tenant['company']->id);
        Artisan::call('accounting:engine', ['company' => $tenant['company']->id, '--enable' => true]);

        return $tenant;
    }

    /**
     * A real Payment row — transactions.payment_id has a genuine FK to `payments`
     * (ON DELETE SET NULL), so an arbitrary int (unlike accountId in the sibling gate tests,
     * which has its own FK but is always factory-backed there too) would 1452 on insert.
     */
    private function makePayment(array $tenant): Payment
    {
        return Payment::factory()->create([
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
        ]);
    }

    private function draft(
        Company $company,
        Branch $branch,
        Account $debitAccount,
        Account $creditAccount,
        string $idempotencyKey,
        ?int $paymentId,
        float $amount = 25.000
    ): DocumentDraft {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'RV',
            subType: null,
            docDate: now(),
            narration: 'PostingServiceRepostPaymentIdTest fixture document',
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
            idempotencyKey: $idempotencyKey,
            sourceType: 'Receipt',
            paymentId: $paymentId,
        );
    }

    public function test_post_reverse_repost_with_payment_id_never_collides_and_keeps_payment_id_on_original_only(): void
    {
        $tenant = $this->makeEnabledTenant();
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $payment = $this->makePayment($tenant);

        $originalDraft = $this->draft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            idempotencyKey: 'test:d3:original:'.uniqid(),
            paymentId: $payment->id,
        );

        $posted = app(PostingService::class)->post($originalDraft);
        $originalId = $posted->transaction->id;

        $this->assertSame(
            $payment->id,
            (int) DB::table('transactions')->where('id', $originalId)->value('payment_id')
        );
        $this->assertSame('Receipt', DB::table('transactions')->where('id', $originalId)->value('reference_type'));

        // The natural caller shape: $new reuses $old's OWN payment_id — "same real-world payment,
        // just corrected". Pre-D3-fix this 1062'd on transactions_payment_id_reference_type_unique
        // because reverse() never clears payment_id off $old.
        $newDraft = $this->draft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            idempotencyKey: 'test:d3:replacement:'.uniqid(),
            paymentId: $payment->id,
            amount: 40.000,
        );

        $reposted = app(PostingService::class)->repost($posted->transaction, $newDraft, now(), null);

        // No 1062 — repost() returned a real PostedDocument for the replacement.
        $this->assertTrue($reposted->transaction->exists);
        $replacementId = $reposted->transaction->id;

        // The ORIGINAL keeps its own payment_id, untouched by reverse()/repost().
        $this->assertSame(
            $payment->id,
            (int) DB::table('transactions')->where('id', $originalId)->value('payment_id'),
            'The original document must keep its own payment_id after being reversed.'
        );
        $this->assertSame('reversed', DB::table('transactions')->where('id', $originalId)->value('posting_status'));

        // The reversal document (created inside repost()'s internal reverse() call) is chain-
        // linked to the original and carries payment_id = NULL.
        $reversalId = DB::table('transactions')->where('reversal_of_transaction_id', $originalId)->value('id');
        $this->assertNotNull($reversalId, 'Expected a reversal document linked to the original via reversal_of_transaction_id.');
        $this->assertNull(
            DB::table('transactions')->where('id', $reversalId)->value('payment_id'),
            'A reversal header must never carry payment_id.'
        );

        // The REPLACEMENT document — the D3 fix under test — also has payment_id = NULL, even
        // though the caller's $new draft explicitly set the same payment_id as $old.
        $this->assertNull(
            DB::table('transactions')->where('id', $replacementId)->value('payment_id'),
            "D3: repost()'s replacement document must never carry payment_id, even when the "
                .'caller reused the same payment_id as $old.'
        );

        // Chain linked end to end: original -> reversal (reversal_of_transaction_id) exists and
        // is distinct from the replacement, which posted cleanly alongside it.
        $this->assertNotSame($reversalId, $replacementId);
    }

    public function test_a_genuine_collision_on_the_payment_reference_type_index_throws_a_typed_exception_not_a_raw_query_exception(): void
    {
        $tenant = $this->makeEnabledTenant();
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $payment = $this->makePayment($tenant);

        $firstDraft = $this->draft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            idempotencyKey: 'test:d3:first:'.uniqid(),
            paymentId: $payment->id,
        );
        app(PostingService::class)->post($firstDraft);

        // A SECOND, unrelated document for the SAME payment_id + reference_type ('Receipt', for
        // an 'RV' docType — resolveReferenceType()) but a DIFFERENT idempotency key: a feeder
        // bug, not a retry of the first. isIdempotencyKeyRaceViolation() correctly refuses to
        // misidentify this (the driver error names the OTHER index), so this must surface as the
        // new typed exception, not a raw QueryException and not a silently-returned wrong
        // document.
        $secondDraft = $this->draft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            idempotencyKey: 'test:d3:second:'.uniqid(),
            paymentId: $payment->id,
        );

        $this->expectException(DuplicatePaymentReferenceException::class);

        app(PostingService::class)->post($secondDraft);
    }

    /**
     * Residual 2 (header-INSERT catch, mis-typed in W2): before this fix, EVERY 1062 naming
     * transactions_payment_id_reference_type_unique was treated as an unconditional "different
     * document" and threw DuplicatePaymentReferenceException — including the identical-key retry
     * shape this test pins, where the message's own claim ("a different document already
     * occupies this payment_id/reference_type pair") is FALSE: it is this draft's own prior
     * attempt.
     *
     * Simulated as a genuine concurrent race, not a sequential double-post (a sequential second
     * call to post() with the SAME idempotencyKey never reaches the header INSERT at all — step
     * 1's own plain SELECT already returns the existing document first, which would make this
     * test pass even on the unfixed code and prove nothing). A second, independent PDO connection
     * to the exact SAME physical test database (cloned from the already-validated mysql_testing
     * config — never a hardcoded database name) inserts and COMMITS the "winner" row — same
     * idempotency key, same payment_id, same reference_type as this test's own draft — AFTER the
     * tenant/account/payment fixture setup above has already forced this test's own connection to
     * take its first plain read (establishing its REPEATABLE READ snapshot before the winner
     * commits). This test's own post() call below is therefore guaranteed to MISS the winner at
     * step 1 (same mechanism the class's own P1 FIX ROUND 3 docblock proves step by step) while
     * still colliding with it on the header INSERT — exactly the shape the fixed catch block must
     * resolve by probing for a same-key document before deciding it is a duplicate.
     *
     * FK NOTE (discovered running this test for real, not guessed): $payment was created on the
     * DEFAULT connection inside RefreshDatabase's still-open, uncommitted test transaction, so
     * InnoDB holds an implicit exclusive lock on that `payments` row until this test ends. The
     * second connection's INSERT into `transactions` carries that SAME payment_id, and
     * transactions.payment_id has a real FK to `payments` — enforcing it requires a SHARED lock on
     * that exact row, which the default connection's exclusive lock blocks until
     * innodb_lock_wait_timeout expires (~50s, reproduced: this test failed with SQLSTATE HY000
     * 1205 "Lock wait timeout exceeded" before this fix). `SET FOREIGN_KEY_CHECKS=0` on the race
     * connection's OWN session sidesteps exactly that check — session-scoped only, never touches
     * the default connection's enforcement — since the row is deleted in tearDown() regardless of
     * whether the FK would have held (a SECOND lock-wait deadlock, this time on the cleanup DELETE
     * itself racing the lock this test's own post() call takes on the winner row, is exactly why
     * that cleanup lives in tearDown() — AFTER RefreshDatabase's rollback releases it — and not in
     * a `finally` block here; see $residual2RaceWinnerId's own docblock).
     */
    public function test_identical_key_and_payment_id_retry_returns_existing_document_not_an_exception(): void
    {
        $tenant = $this->makeEnabledTenant();
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $payment = $this->makePayment($tenant);

        $idempotencyKey = 'test:residual2:retry:'.uniqid();

        // A second, independent connection to the SAME physical database as 'mysql_testing' —
        // cloned from its own resolved (already safety-guarded) config, so this stays correct
        // under whichever per-agent isolated test database this run actually targets.
        config(['database.connections.residual2_race' => config('database.connections.mysql_testing')]);
        DB::purge('residual2_race');

        // See the FK NOTE above: session-scoped only, so the default connection's own FK
        // enforcement (and every other test's) is completely unaffected.
        DB::connection('residual2_race')->statement('SET FOREIGN_KEY_CHECKS=0');

        $winnerId = DB::connection('residual2_race')->table('transactions')->insertGetId([
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'transaction_type' => 'RV',
            'amount' => 25.000,
            'description' => 'residual 2 test: simulated concurrent winner for the SAME draft',
            'reference_type' => 'Receipt',
            'reference_number' => 'RACE-A-'.uniqid(),
            'payment_id' => $payment->id,
            'idempotency_key' => $idempotencyKey,
            'transaction_date' => now(),
            'doc_type' => 'RV',
            'doc_year' => (int) now()->format('Y'),
            'posting_status' => 'posted',
            'total_debit' => 25.000,
            'total_credit' => 25.000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cleanup deferred to tearDown(), AFTER parent::tearDown()'s rollback — see this
        // property's own docblock and tearDown()'s comment for why deleting it any earlier
        // deadlocks against a lock this test's own post() call (below) takes on this exact row.
        $this->residual2RaceWinnerId = $winnerId;

        $retryDraft = $this->draft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            idempotencyKey: $idempotencyKey,
            paymentId: $payment->id,
        );

        $result = app(PostingService::class)->post($retryDraft);

        $this->assertSame(
            $winnerId,
            (int) $result->transaction->id,
            'A retry colliding on the SAME idempotency key and the SAME payment_id/reference_type '
                .'pair (the same draft, resubmitted) must return the EXISTING document, not throw '
                .'DuplicatePaymentReferenceException about "a different document" — residual 2.'
        );

        // Queried via the SEPARATE connection, deliberately NOT the default connection's
        // `DB::table()` — the default connection's own REPEATABLE READ snapshot was fixed before
        // the winner row was ever committed (that is the entire mechanism under test), so a plain
        // read on it would undercount by construction, not because of anything this assertion is
        // actually trying to verify.
        $this->assertSame(
            1,
            DB::connection('residual2_race')->table('transactions')
                ->where('company_id', $company->id)
                ->where('idempotency_key', $idempotencyKey)
                ->count(),
            'Exactly one transaction row may exist for this idempotency key after the retry.'
        );
    }

    /**
     * Residual 2 — scenario (c): a genuine collision on transactions_payment_id_reference_type_
     * unique whose same-key probe finds a SOFT-DELETED row (not a live one) must still surface
     * SupersededIdempotencyKeyException — the exact same outcome the pre-existing idempotency-key
     * race already produces for this shape — rather than DuplicatePaymentReferenceException,
     * merely because the 1062 this time happens to name the OTHER unique index. Soft-deleting the
     * original does not free either index (both are deleted_at-blind), so the retry's header
     * INSERT still collides physically; only the exception it produces is under test here.
     */
    public function test_soft_deleted_document_under_the_same_key_is_superseded_not_a_duplicate(): void
    {
        $tenant = $this->makeEnabledTenant();
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $payment = $this->makePayment($tenant);

        $idempotencyKey = 'test:residual2:superseded:'.uniqid();

        $originalDraft = $this->draft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            idempotencyKey: $idempotencyKey,
            paymentId: $payment->id,
        );

        $posted = app(PostingService::class)->post($originalDraft);
        $deadId = $posted->transaction->id;

        Transaction::withoutGlobalScopes()->whereKey($deadId)->update(['deleted_at' => now()]);

        $retryDraft = $this->draft(
            $company,
            $branch,
            $debitAccount,
            $creditAccount,
            idempotencyKey: $idempotencyKey,
            paymentId: $payment->id,
            amount: 40.000,
        );

        $this->expectException(SupersededIdempotencyKeyException::class);

        app(PostingService::class)->post($retryDraft);
    }

    /**
     * Residual 18 decision (KEEP, not remove — see withRepostIdempotencyKey()'s own docblock for
     * the full reasoning): the `paymentId: null` line inside that private method is unexercised
     * by repost() itself today, because repost() always calls withoutPaymentId() first whenever
     * $new->paymentId is non-null — by the time withRepostIdempotencyKey() runs, the input is
     * already null. This test exercises the METHOD directly, independent of repost()'s current
     * call ordering, with an input DocumentDraft whose paymentId is deliberately still non-null —
     * proving the line's own effect (it is not dead code in the sense of being unreachable; it is
     * a real safety net one call-site simplification away from mattering).
     */
    public function test_with_repost_idempotency_key_forces_payment_id_null_even_when_the_input_draft_still_carries_one(): void
    {
        $postingDate = \Illuminate\Support\Carbon::create(2026, 2, 10);

        $draftWithPaymentId = new DocumentDraft(
            companyId: 1,
            branchId: 1,
            docType: 'RV',
            subType: null,
            docDate: now(),
            narration: 'residual 18 reflection pin',
            lines: [],
            idempotencyKey: 'test:residual18:original',
            sourceType: 'Receipt',
            paymentId: 999999,
            postingDate: $postingDate,
        );

        $method = new \ReflectionMethod(PostingService::class, 'withRepostIdempotencyKey');
        $method->setAccessible(true);

        /** @var DocumentDraft $result */
        $result = $method->invoke(app(PostingService::class), $draftWithPaymentId, ':repost:1');

        $this->assertNull(
            $result->paymentId,
            'withRepostIdempotencyKey() must force paymentId to null even when the INPUT draft '
                .'still carries one — residual 18, exercised independently of repost()\'s current '
                .'withoutPaymentId()-first call ordering.'
        );
        $this->assertSame('test:residual18:original:repost:1', $result->idempotencyKey);
        $this->assertSame(999999, $draftWithPaymentId->paymentId, 'The input draft itself must be untouched (immutable value object).');

        // P2.5.B addition: postingDate must be carried field-for-field like every other field this
        // reconstruction copies — see withRepostIdempotencyKey()'s own docblock note on this.
        $this->assertSame(
            $postingDate,
            $result->postingDate,
            'withRepostIdempotencyKey() must carry $draft->postingDate over, not silently drop it.'
        );
    }
}
