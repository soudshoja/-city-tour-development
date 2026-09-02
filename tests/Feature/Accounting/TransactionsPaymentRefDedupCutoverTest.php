<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\DuplicatePaymentReferenceException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * Coverage for the transactions-cutover split:
 *   - migration 2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php
 *     (rewritten to do ONLY the `payments` half, guarded for idempotency)
 *   - migration 2026_08_24_000002_add_post_cutover_dedup_key_to_transactions_table.php
 *     (new: the `transactions` half, via a nullable STORED generated column scoped to
 *     post-cutover rows, also guarded for idempotency)
 *
 * See both migrations' own docblocks, and DuplicatePaymentReferenceException's docblock, for the
 * full reasoning (2,007 real CT-shaped-history violations on the old raw two-column index, 98%
 * a legitimate notification-row-vs-ledger-row design collision in the MyFatoorah top-up flow,
 * not double money; 2 genuine double-posts already Suspense-balanced). This file proves the
 * three load-bearing behaviours directly:
 *
 *   1. two POST-CUTOVER transactions sharing (payment_id, reference_type) collide, surfaced by
 *      PostingService as the typed DuplicatePaymentReferenceException, never a raw
 *      QueryException.
 *   2. a PRE-CUTOVER-dated row and a POST-CUTOVER row sharing the same pair coexist WITHOUT
 *      colliding (the pre-cutover row's generated dedup key is NULL).
 *
 * Rerun-safety of the two migrations themselves (including the exact partial-failure shape that
 * motivated the split) is covered separately in
 * tests/Feature/Accounting/TransactionsCutoverMigrationRerunTest.php, NOT here — that test calls
 * raw migration up()/down() (real DDL, which implicit-commits in MySQL/MariaDB) and therefore
 * deliberately does NOT extend AccountingTestCase/RefreshDatabase, whose per-test transaction
 * wrapper a mid-test implicit commit would silently break. See that file's own class docblock,
 * which follows the same real-migration-DDL testing pattern already established by
 * tests/Unit/Modules/DotwAI/WidenZipCodeMigrationTest.php.
 *
 * CUTOVER_TS below is a plain PHP mirror of the literal SQL constant baked into migration
 * 2026_08_24_000002's generated-column expression — kept in sync by hand (there is no shared
 * source of truth between PHP and the generated-column SQL by design; see that migration's own
 * docblock on why CUTOVER_TS is a literal, not a config value).
 */
class TransactionsPaymentRefDedupCutoverTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    private const CUTOVER_TS = '2026-09-01 00:00:00';

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
     * A real Payment row — transactions.payment_id has a genuine FK to `payments` (ON DELETE SET
     * NULL), so an arbitrary int would 1452 on insert. Mirrors
     * PostingServiceRepostPaymentIdTest::makePayment() exactly.
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
            narration: 'TransactionsPaymentRefDedupCutoverTest fixture document',
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

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 1. Collision: two post-cutover rows, same (payment_id, reference_type).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_two_post_cutover_transactions_same_payment_and_reference_type_collide_as_typed_exception(): void
    {
        $tenant = $this->makeEnabledTenant();
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $payment = $this->makePayment($tenant);

        $firstDraft = $this->draft($company, $branch, $debitAccount, $creditAccount, 'test:cutover:collide:first:'.uniqid(), $payment->id);
        $secondDraft = $this->draft($company, $branch, $debitAccount, $creditAccount, 'test:cutover:collide:second:'.uniqid(), $payment->id);

        Carbon::setTestNow(Carbon::parse('2026-09-02 00:00:00'));

        try {
            $posted = app(PostingService::class)->post($firstDraft);

            $this->assertSame(
                $payment->id.':Receipt',
                DB::table('transactions')->where('id', $posted->transaction->id)->value('payment_ref_dedup_key'),
                'the first post-cutover row must compute a real, non-null dedup key'
            );

            $caught = null;

            try {
                app(PostingService::class)->post($secondDraft);
                $this->fail('Expected DuplicatePaymentReferenceException to be thrown, no exception was thrown.');
            } catch (\Throwable $e) {
                $caught = $e;
            }

            // Must be the TYPED exception PostingService's own catch block raises, never the raw
            // QueryException the header INSERT's 1062 would otherwise let escape uncaught.
            $this->assertInstanceOf(
                DuplicatePaymentReferenceException::class,
                $caught,
                'a genuine collision on the payment/reference-type dedup index must surface as '
                    .'DuplicatePaymentReferenceException, not a raw QueryException. Got: '
                    .get_class($caught)
            );
            $this->assertSame($payment->id, $caught->paymentId);
            $this->assertSame('Receipt', $caught->referenceType);
        } finally {
            Carbon::setTestNow();
        }

        // Only the first row was ever committed -- the second header INSERT's failure rolled
        // back inside its own DB::transaction(), never partially landing.
        $this->assertSame(
            1,
            Transaction::where('payment_id', $payment->id)->where('reference_type', 'Receipt')->count()
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 2. Pre-cutover / post-cutover coexistence for the SAME (payment_id, reference_type).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_pre_cutover_and_post_cutover_rows_with_same_payment_and_reference_type_coexist(): void
    {
        $tenant = $this->makeEnabledTenant();
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $payment = $this->makePayment($tenant);

        // Row 1: real wall-clock "now" has crossed CUTOVER_TS (2026-09-01 00:00:00), so it can no
        // longer be relied on to produce a naturally pre-cutover row -- freeze Carbon::setTestNow()
        // to a fixed pre-cutover instant instead, exactly as this test's own comment (and the
        // post-cutover row below, and test_two_post_cutover_transactions_...'s own freeze) already
        // does. Reset in a finally so a failed post() still restores real time for the rest of the
        // suite, matching the freeze/finally convention used throughout this file.
        $preCutoverDraft = $this->draft($company, $branch, $debitAccount, $creditAccount, 'test:cutover:coexist:pre:'.uniqid(), $payment->id);

        Carbon::setTestNow(Carbon::parse('2026-08-31 23:00:00'));

        try {
            $preCutoverPosted = app(PostingService::class)->post($preCutoverDraft);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertNull(
            DB::table('transactions')->where('id', $preCutoverPosted->transaction->id)->value('payment_ref_dedup_key'),
            'a pre-cutover row must compute a NULL dedup key'
        );

        // Row 2: same payment_id + reference_type ('Receipt'), but posted after freezing time to
        // a fixed post-cutover instant -- and with a DIFFERENT idempotency key, so this is
        // genuinely a second document, not a retry of row 1.
        $postCutoverDraft = $this->draft($company, $branch, $debitAccount, $creditAccount, 'test:cutover:coexist:post:'.uniqid(), $payment->id);

        Carbon::setTestNow(Carbon::parse('2026-09-02 00:00:00'));

        try {
            $postCutoverPosted = app(PostingService::class)->post($postCutoverDraft);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(
            $payment->id.':Receipt',
            DB::table('transactions')->where('id', $postCutoverPosted->transaction->id)->value('payment_ref_dedup_key'),
            'the post-cutover row must compute a real, non-null dedup key'
        );

        // Both rows genuinely coexist -- no exception was thrown above, and both are physically
        // present under the same (payment_id, reference_type) pair.
        $this->assertSame(
            2,
            Transaction::where('payment_id', $payment->id)->where('reference_type', 'Receipt')->count(),
            'a pre-cutover row and a post-cutover row sharing (payment_id, reference_type) must coexist'
        );
        $this->assertNotSame($preCutoverPosted->transaction->id, $postCutoverPosted->transaction->id);
    }
}
