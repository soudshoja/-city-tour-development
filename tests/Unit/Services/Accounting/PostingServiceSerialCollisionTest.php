<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\SerialCollisionExhaustedException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccountingTestCase;

/**
 * W3-prereq lane B (doc 17 §3.3/§4): PostingService::post()'s bounded retry on a reference-number
 * collision (`transactions_company_doctype_refnum_unique`, migration 2026_08_24_120008), and the
 * SerialCollisionExhaustedException thrown once that retry is exhausted.
 *
 * STRATEGY — how a real 1062 on THIS index is forced deterministically, without hardcoding
 * SequenceService's mask shape: a genuine "warm-up" document is posted first through the real
 * pipeline, and its OWN reference_number is used as the ground truth for what the next serials
 * will look like (bumpReferenceNumber() increments the trailing zero-padded digit group the same
 * way SequenceService's {SEQ} token does). "Poison" rows — plain `transactions` headers written
 * directly via forceFill(), the same mechanism PostingService::createTransactionHeader() itself
 * uses, carrying doc_type so they actually participate in the compound unique index (a legacy row
 * with doc_type NULL never would — see the migration's own NULL-safety analysis) — are then
 * inserted at exactly the serials the next real post() call is about to reserve, forcing the
 * header INSERT to collide for real.
 */
class PostingServiceSerialCollisionTest extends AccountingTestCase
{
    private function makeEnabledCompany(): Company
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $company->forceFill(['posting_engine_enabled' => true])->save();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function balancedDraft(Company $company, Branch $branch, Account $debitAccount, Account $creditAccount, string $label): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'INV',
            subType: null,
            docDate: now(),
            narration: 'PostingServiceSerialCollisionTest fixture ('.$label.')',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 10.000,
                    currency: 'KWD',
                    originalAmount: 10.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 10.000,
                    currency: 'KWD',
                    originalAmount: 10.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            idempotencyKey: 'test:serial-collision:'.$label.':'.uniqid(),
        );
    }

    /**
     * Increments the trailing zero-padded digit group of a real, engine-produced
     * reference_number by $by — mirrors SequenceService's own {SEQ} token behaviour (same width,
     * left-padded with zeros) without depending on the exact surrounding mask shape (branch
     * segment, separators, …), so this test keeps working if config/accounting.php's mask ever
     * changes.
     */
    private function bumpReferenceNumber(string $referenceNumber, int $by): string
    {
        return (string) preg_replace_callback(
            '/(\d+)$/',
            static function (array $m) use ($by): string {
                $width = strlen($m[1]);
                $value = ((int) $m[1]) + $by;

                return str_pad((string) $value, $width, '0', STR_PAD_LEFT);
            },
            $referenceNumber
        );
    }

    /**
     * A plain `transactions` header row written directly (forceFill — the same mechanism
     * PostingService::createTransactionHeader() itself uses, since doc_type predates
     * Transaction::$fillable), occupying a specific reference_number ahead of time so the next
     * real post() call's header INSERT collides with it on
     * transactions_company_doctype_refnum_unique. No journal_entries rows are written for it, so
     * it never participates in (and cannot break) AccountingTestCase's per-transaction ledger-
     * balance invariant, which groups strictly by journal_entries.transaction_id.
     */
    private function insertPoisonTransaction(Company $company, Branch $branch, string $referenceNumber, \DateTimeInterface $docDate): Transaction
    {
        $transaction = new Transaction;
        $transaction->forceFill([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'INV',
            'amount' => 1.000,
            'description' => 'PostingServiceSerialCollisionTest poison row (pre-existing reference_number)',
            'invoice_id' => null,
            'payment_reference' => null,
            'payment_id' => null,
            'reference_type' => 'Invoice',
            'reference_number' => $referenceNumber,
            'transaction_date' => $docDate,
            'doc_type' => 'INV',
            'sub_type' => null,
            'doc_year' => (int) $docDate->format('Y'),
            'posting_status' => 'posted',
            'total_debit' => 1.000,
            'total_credit' => 1.000,
            'reversal_of_transaction_id' => null,
            'idempotency_key' => null,
            'created_by' => null,
            'posted_by' => null,
            'posted_at' => now(),
        ]);
        $transaction->save();

        return $transaction;
    }

    /**
     * $capturedWarnings is populated by reference as 'accounting.serial_collision' warnings are
     * logged, in call order — via Log::listen() (a real event listener on the default channel's
     * dispatcher), NOT Log::spy(). Log::spy()'s shouldHaveReceived('warning')->once()->withArgs(...)
     * was tried first and proved unreliable here: verified empirically that its numeric constraint
     * counts ALL recorded calls to the method, not just the ones the argument-matcher closure
     * accepts — two real calls carrying different $context values both counted against a single
     * ->once() expectation scoped to only one of them. A plain event listener sidesteps that
     * entirely; the captured contexts are asserted below with ordinary PHPUnit array assertions.
     *
     * @param  array<int, array<string, mixed>>  $capturedWarnings
     */
    private function listenForSerialCollisionWarnings(array &$capturedWarnings): void
    {
        Log::listen(function ($event) use (&$capturedWarnings): void {
            if ($event->level === 'warning' && $event->message === 'accounting.serial_collision') {
                $capturedWarnings[] = $event->context;
            }
        });
    }

    public function test_collision_on_attempts_one_and_two_then_success_posts_the_third_number_and_logs_two_warnings(): void
    {
        $capturedWarnings = [];
        $this->listenForSerialCollisionWarnings($capturedWarnings);

        $company = $this->makeEnabledCompany();
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $docDate = now();

        // Warm-up: a real post() to learn this environment's actual reference_number shape and
        // advance serial_schemas.last_serial to N, so the NEXT post() call's first attempt
        // reserves serial N+1.
        $warmup = app(PostingService::class)->post(
            $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 'warmup')
        );
        $warmupNumber = $warmup->documentNumber;
        $this->assertNotNull($warmupNumber);

        $poison1 = $this->bumpReferenceNumber($warmupNumber, 1);
        $poison2 = $this->bumpReferenceNumber($warmupNumber, 2);
        $expectedThirdNumber = $this->bumpReferenceNumber($warmupNumber, 3);

        $this->insertPoisonTransaction($company, $branch, $poison1, $docDate);
        $this->insertPoisonTransaction($company, $branch, $poison2, $docDate);

        $transactionsBefore = DB::table('transactions')->where('company_id', $company->id)->count();

        $posted = app(PostingService::class)->post(
            $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 'real')
        );

        $this->assertSame(
            $expectedThirdNumber,
            $posted->documentNumber,
            'After two collisions, post() must succeed with the THIRD reserved serial, not the first or second.'
        );
        $this->assertSame($expectedThirdNumber, $posted->transaction->reference_number);
        $this->assertSame(
            $transactionsBefore + 1,
            DB::table('transactions')->where('company_id', $company->id)->count(),
            'Exactly one new transactions row — no half-written header survives a collided attempt.'
        );
        $this->assertSame(2, count($posted->lines));

        $this->assertCount(
            2,
            $capturedWarnings,
            'Exactly two accounting.serial_collision warnings — one per collided attempt, none for the successful third.'
        );
        $this->assertSame(1, $capturedWarnings[0]['attempt'] ?? null);
        $this->assertSame($poison1, $capturedWarnings[0]['attempted_number'] ?? null);
        $this->assertSame($company->id, $capturedWarnings[0]['company_id'] ?? null);
        $this->assertSame('INV', $capturedWarnings[0]['doc_type'] ?? null);
        $this->assertSame(2, $capturedWarnings[1]['attempt'] ?? null);
        $this->assertSame($poison2, $capturedWarnings[1]['attempted_number'] ?? null);
    }

    public function test_collision_on_all_five_bounded_attempts_throws_typed_exception_and_posts_nothing(): void
    {
        $capturedWarnings = [];
        $this->listenForSerialCollisionWarnings($capturedWarnings);

        $company = $this->makeEnabledCompany();
        $branch = $this->makeBranch($company);
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);
        $docDate = now();

        $warmup = app(PostingService::class)->post(
            $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 'warmup')
        );
        $warmupNumber = $warmup->documentNumber;

        $poisonNumbers = [];
        for ($i = 1; $i <= 5; $i++) {
            $poisonNumbers[$i] = $this->bumpReferenceNumber($warmupNumber, $i);
            $this->insertPoisonTransaction($company, $branch, $poisonNumbers[$i], $docDate);
        }

        $transactionsBefore = DB::table('transactions')->where('company_id', $company->id)->count();
        $journalEntriesBefore = DB::table('journal_entries')->where('company_id', $company->id)->count();
        $lastSerialBefore = DB::table('serial_schemas')
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('doc_type', 'INV')
            ->where('doc_year', (int) $docDate->format('Y'))
            ->value('last_serial');

        $draft = $this->balancedDraft($company, $branch, $debitAccount, $creditAccount, 'exhausted');

        try {
            app(PostingService::class)->post($draft);
            $this->fail('Expected SerialCollisionExhaustedException to be thrown.');
        } catch (SerialCollisionExhaustedException $e) {
            $this->assertSame($company->id, $e->companyId);
            $this->assertSame($branch->id, $e->branchId);
            $this->assertSame('INV', $e->docType);
            $this->assertSame((int) $docDate->format('Y'), $e->docYear);
            $this->assertSame($poisonNumbers[5], $e->lastAttemptedNumber);
            $this->assertSame(5, $e->attempts);
        }

        $this->assertSame(
            $transactionsBefore,
            DB::table('transactions')->where('company_id', $company->id)->count(),
            'Exhausting every bounded attempt must post nothing — no half-written header row.'
        );
        $this->assertSame(
            $journalEntriesBefore,
            DB::table('journal_entries')->where('company_id', $company->id)->count(),
            'Exhausting every bounded attempt must write no journal_entries lines either.'
        );

        $lastSerialAfter = DB::table('serial_schemas')
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('doc_type', 'INV')
            ->where('doc_year', (int) $docDate->format('Y'))
            ->value('last_serial');
        $this->assertSame(
            $lastSerialBefore,
            $lastSerialAfter,
            'The whole DB::transaction() rolls back on exhaustion — every attempt\'s serial_schemas '
                .'reservation from the failed call must unwind with it, burning nothing.'
        );

        // Four collisions retried (attempts 1-4, each logged before looping back); the fifth
        // collision hits the bounded ceiling and throws instead of logging-and-retrying.
        $this->assertCount(
            4,
            $capturedWarnings,
            'Exactly four accounting.serial_collision warnings — the fifth (final) collision throws instead of logging.'
        );
        foreach ([1, 2, 3, 4] as $index => $attempt) {
            $this->assertSame($attempt, $capturedWarnings[$index]['attempt'] ?? null);
            $this->assertSame($poisonNumbers[$attempt], $capturedWarnings[$index]['attempted_number'] ?? null);
        }
    }
}
