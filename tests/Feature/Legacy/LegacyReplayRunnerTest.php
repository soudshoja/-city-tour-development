<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Onboarding\Replay\LegacyReplayAborted;
use App\Services\Onboarding\Replay\LegacyReplayRunner;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- {@see LegacyReplayRunner}.
 *
 * PLAN.md §LP3 acceptance, made executable: "a full replay in one unattended
 * run; a SECOND full run posts ZERO new journal entries; a kill-mid-run resume
 * posts exactly the remainder -- both automated tests, not eyeballed."
 */
class LegacyReplayRunnerTest extends LegacyReplayTestCase
{
    /** Stage one balanced two-line document. */
    private function balancedDocument(string $subType, string $docDate, string $amount, array $headerOverrides = []): int
    {
        $docId = $this->stageHeader(array_merge([
            'SubType' => $subType,
            'DocType' => $subType,
            'DocDt' => $docDate,
            'DocNo' => $subType.'/CO/25/'.$amount.'/'.uniqid(),
        ], $headerOverrides));

        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => $amount, 'DC' => 'D', 'DocDt' => $docDate]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => $amount, 'DC' => 'C', 'DocDt' => $docDate]);

        return $docId;
    }

    /** §8.4 / PLAN §LP3: chronological by (DocDt, DocID). */
    public function test_documents_are_replayed_chronologically_by_doc_date_then_doc_id(): void
    {
        $december = $this->balancedDocument('INV', '2025-12-01', '3.000', ['DocID' => 10]);
        $januaryLater = $this->balancedDocument('INV', '2025-01-05', '2.000', ['DocID' => 900]);
        $januaryEarlier = $this->balancedDocument('INV', '2025-01-05', '1.000', ['DocID' => 800]);

        $this->assertSame(3, $this->replay()['posted']);

        $order = Transaction::withoutGlobalScopes()->orderBy('id')->pluck('idempotency_key')->all();

        $this->assertSame([
            'legacy:INV:'.$januaryEarlier,
            'legacy:INV:'.$januaryLater,
            'legacy:INV:'.$december,
        ], $order);
    }

    /**
     * PLAN §LP3 acceptance + MAPPING-RULES §8.1: "a second full run posts ZERO
     * new journal entries".
     *
     * MUTATION PROOF (resume short-circuit): delete the `isset($alreadyPosted
     * [$docId])` branch from LegacyReplayRunner::run() and this test fails on
     * `already_posted`.
     *
     * NOTE, and it matters: this test alone does NOT prove the idempotency key,
     * because `map_document` short-circuits before the seam is ever reached --
     * verified by mutation (appending uniqid() to the key leaves this test
     * green). The key's own proof is the next test down, which deletes the audit
     * row first so the seam is the only thing left.
     */
    public function test_a_second_full_run_posts_no_new_journal_entries(): void
    {
        $this->balancedDocument('INV', '2025-02-01', '10.000');
        $this->balancedDocument('CRV', '2025-02-02', '20.000');
        $this->balancedDocument('BPV', '2025-02-03', '30.000');

        $first = $this->replay();
        $this->assertSame(3, $first['posted']);

        $transactionsAfterFirst = Transaction::withoutGlobalScopes()->count();
        $linesAfterFirst = JournalEntry::withoutGlobalScopes()->count();

        $second = $this->replay();

        $this->assertSame(0, $second['posted'], 'a re-run must post nothing');
        $this->assertSame(3, $second['already_posted']);
        $this->assertSame($transactionsAfterFirst, Transaction::withoutGlobalScopes()->count());
        $this->assertSame($linesAfterFirst, JournalEntry::withoutGlobalScopes()->count());
    }

    /**
     * The same guarantee WITHOUT the map_document shortcut: a crash between the
     * engine's own commit and the audit write leaves a posted document with no
     * `posted` audit row, so the resumed run retries it and the SEAM's
     * idempotency key is the only thing standing between us and a double post.
     *
     * MUTATION PROOF (IDEMPOTENCY KEY, verified): append `uniqid()` to
     * LegacyDocumentMapper's `idempotencyKey:` argument and this test fails on
     * "the seam key must prevent a second post" -- two transactions, four
     * journal lines, the ledger silently doubled.
     */
    public function test_a_document_posted_but_not_audited_is_not_posted_twice_on_resume(): void
    {
        $docId = $this->balancedDocument('INV', '2025-02-01', '10.000');

        $this->assertSame(1, $this->replay()['posted']);

        // Simulate the crash window: the engine committed, the audit row did not.
        DB::connection('legacy_pilot')->table('map_document')->where('legacy_doc_id', $docId)->delete();

        $second = $this->replay();

        $this->assertSame(1, Transaction::withoutGlobalScopes()->count(), 'the seam key must prevent a second post');
        $this->assertSame(2, JournalEntry::withoutGlobalScopes()->count());
        // The seam returns the EXISTING PostedDocument, so the runner records a
        // normal `posted` outcome pointing at the same transaction.
        $this->assertSame(1, $second['posted']);
        $this->assertSame(
            Transaction::withoutGlobalScopes()->value('id'),
            (int) $this->auditFor($docId)->document_id
        );
    }

    /**
     * PLAN §LP3 acceptance: "a kill-mid-run resume posts exactly the remainder".
     * `--limit` is the deterministic stand-in for the kill.
     */
    public function test_a_run_killed_mid_way_resumes_and_posts_exactly_the_remainder(): void
    {
        $docs = [];

        foreach (range(1, 5) as $i) {
            $docs[] = $this->balancedDocument('INV', sprintf('2025-04-%02d', $i), sprintf('%d.000', $i));
        }

        $partial = $this->replay(['limit' => 2]);
        $this->assertSame(2, $partial['posted']);
        $this->assertSame(2, Transaction::withoutGlobalScopes()->count());

        $resumed = $this->replay();

        $this->assertSame(3, $resumed['posted'], 'exactly the remainder');
        $this->assertSame(2, $resumed['already_posted']);
        $this->assertSame(5, Transaction::withoutGlobalScopes()->count());

        foreach ($docs as $docId) {
            $this->assertSame('posted', $this->auditFor($docId)->status);
        }
    }

    /** `--from-doc` resumes from a DocID, `--type` scopes to one SubType. */
    public function test_the_type_and_from_doc_filters_scope_the_run(): void
    {
        $this->balancedDocument('INV', '2025-05-01', '1.000', ['DocID' => 100]);
        $this->balancedDocument('CRV', '2025-05-02', '2.000', ['DocID' => 200]);
        $this->balancedDocument('INV', '2025-05-03', '3.000', ['DocID' => 300]);

        $this->assertSame(2, $this->replay(['type' => 'INV'])['posted']);
        $this->assertSame(['INV', 'INV'], Transaction::withoutGlobalScopes()->pluck('sub_type')->map(fn ($s) => str_replace('LEGACY_', '', $s))->all());

        // --from-doc narrows the SELECTION; doc 300 is already posted by the run
        // above, so the resumed run correctly posts nothing new and doc 200 is
        // never even considered.
        $resumed = $this->replay(['from_doc' => 250]);
        $this->assertSame(1, $resumed['selected']);
        $this->assertSame(1, $resumed['already_posted']);
        $this->assertSame(0, $resumed['posted']);
    }

    /**
     * §1.6 / §4.2 -- "a dry run writes NOTHING at all", so it can be handed to
     * an owner before anything is committed.
     */
    public function test_a_dry_run_writes_nothing(): void
    {
        $this->balancedDocument('INV', '2025-06-01', '7.000');

        $summary = $this->replay(['dry_run' => true]);

        $this->assertSame(1, $summary['mappable']);
        $this->assertSame(0, $summary['posted']);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(0, DB::connection('legacy_pilot')->table('map_document')->count());
        $this->assertSame(0, DB::connection('legacy_pilot')->table('map_document_line')->count());
    }

    /**
     * §1.8 / O4 -- SINGLE-DOCUMENT TAG: one legacy data defect is classified,
     * reported and the run CONTINUES.
     *
     * The golden-set rule is disabled for this test (config, not a literal)
     * because with the real value of 20 every one of these three documents is
     * inside the golden set and a refusal there is, correctly, a whole-type stop.
     */
    public function test_a_single_document_refusal_is_tagged_and_the_run_continues(): void
    {
        config(['legacy_pilot.replay.o4.golden_set_size' => 0]);

        $good1 = $this->balancedDocument('INV', '2025-07-01', '1.000');

        $bad = $this->stageHeader(['SubType' => 'INV', 'DocType' => 'INV', 'DocDt' => '2025-07-02', 'DocNo' => 'INV/CO/25/bad']);
        $this->stageLine($bad, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'DC' => 'D', 'DocDt' => '2025-07-02']);
        $this->stageLine($bad, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '4.500', 'DC' => 'C', 'DocDt' => '2025-07-02']);

        $good2 = $this->balancedDocument('INV', '2025-07-03', '2.000');

        $summary = $this->replay();

        $this->assertSame(2, $summary['posted']);
        $this->assertSame(1, $summary['refused']);
        $this->assertSame([], $summary['type_stops'], 'one tagged document must NOT stop the type');
        $this->assertSame(0, $summary['skipped_after_type_stop']);
        $this->assertSame('posted', $this->auditFor($good1)->status);
        $this->assertSame('posted', $this->auditFor($good2)->status);
        $this->assertSame('refused', $this->auditFor($bad)->status);
        $this->assertFalse($summary['passed'], 'O5: any refusal fails the run');
    }

    /**
     * §1.8 / O4 -- WHOLE-TYPE STOP when the type's first N documents all refuse
     * with the SAME class. N is config('legacy_pilot.replay.o4.same_class_streak').
     *
     * MUTATION PROOF (O4 stop threshold): delete the `same_class_streak` branch
     * from LegacyReplayRunner::recordOutcome() and this test fails -- the fourth
     * document is replayed and refused instead of being skipped after the stop.
     */
    public function test_the_type_stops_when_its_first_documents_all_refuse_with_the_same_class(): void
    {
        config([
            'legacy_pilot.replay.o4.golden_set_size' => 0,
            'legacy_pilot.replay.o4.same_class_streak' => 3,
            // High enough that the count threshold cannot be what stops the type.
            'legacy_pilot.replay.o4.threshold_floor' => 1000,
        ]);

        $docs = [];

        foreach (range(1, 4) as $i) {
            $docId = $this->stageHeader(['SubType' => 'BRV', 'DocType' => 'RV', 'DocDt' => sprintf('2025-08-%02d', $i), 'DocNo' => 'BRV/CO/25/'.$i]);
            $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'DC' => 'D', 'DocDt' => sprintf('2025-08-%02d', $i)]);
            $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '4.000', 'DC' => 'C', 'DocDt' => sprintf('2025-08-%02d', $i)]);
            $docs[] = $docId;
        }

        $summary = $this->replay();

        $this->assertSame(3, $summary['refused'], 'the type stops after the third same-class refusal');
        $this->assertSame(1, $summary['skipped_after_type_stop']);
        $this->assertContains('BRV', $summary['type_stops']);
        $this->assertStringContainsString('same code', $summary['type_stop_reasons']['BRV']);
        $this->assertNull($this->auditFor($docs[3]), 'a document skipped after the type stop is never mapped or posted');
    }

    /**
     * §1.8: a refusal inside the type's GOLDEN SET (the first N documents, LP2
     * acceptance) stops the whole type -- at the real, configured value.
     */
    public function test_a_refusal_inside_the_golden_set_stops_the_type(): void
    {
        $bad = $this->stageHeader(['SubType' => 'CPV', 'DocType' => 'PV', 'DocDt' => '2025-09-01', 'DocNo' => 'CPV/CO/25/1']);
        $this->stageLine($bad, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'DC' => 'D', 'DocDt' => '2025-09-01']);
        $this->stageLine($bad, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '4.000', 'DC' => 'C', 'DocDt' => '2025-09-01']);

        $later = $this->balancedDocument('CPV', '2025-09-02', '9.000');

        $summary = $this->replay();

        $this->assertContains('CPV', $summary['type_stops']);
        $this->assertStringContainsString('golden set', $summary['type_stop_reasons']['CPV']);
        $this->assertNull($this->auditFor($later));
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    /**
     * §1.8: refusals exceeding max(floor, percent x census) stop the type, even
     * when every individual refusal is only a tag.
     */
    public function test_the_type_stops_once_refusals_exceed_the_type_threshold(): void
    {
        config([
            'legacy_pilot.replay.o4.golden_set_size' => 0,
            'legacy_pilot.replay.o4.same_class_streak' => 1000,
            'legacy_pilot.replay.o4.threshold_floor' => 2,
            'legacy_pilot.replay.o4.threshold_percent' => 0.0,
        ]);

        foreach (range(1, 4) as $i) {
            $date = sprintf('2025-10-%02d', $i);
            // Alternate the refusal code so the same-class streak can never fire.
            $docId = $this->stageHeader(['SubType' => 'ADM', 'DocType' => 'DBN', 'DocDt' => $date, 'DocNo' => 'ADM/CO/25/'.$i]);

            if ($i % 2 === 1) {
                $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'DC' => 'D', 'DocDt' => $date]);
                $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '4.000', 'DC' => 'C', 'DocDt' => $date]);
            } else {
                $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'Credit' => '1.000', 'DC' => 'D', 'DocDt' => $date]);
                $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '4.000', 'DC' => 'C', 'DocDt' => $date]);
            }
        }

        $summary = $this->replay();

        $this->assertSame(3, $summary['refused'], 'the run stops after the refusal that EXCEEDS the threshold of 2');
        $this->assertContains('ADM', $summary['type_stops']);
        $this->assertStringContainsString('type threshold', $summary['type_stop_reasons']['ADM']);
    }

    /**
     * §6 guard (a): every window period must be OPEN before the run starts.
     * `allowLockedPeriods` is deliberately false, and PostingService would SHIFT
     * rather than fail -- so a closed period has to be caught here or not at all.
     */
    public function test_a_closed_period_aborts_the_run_before_anything_is_posted(): void
    {
        $this->balancedDocument('INV', '2025-06-15', '5.000');

        AccountingPeriod::create([
            'company_id' => $this->company->id,
            'year' => 2025,
            'month' => 6,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);

        try {
            $this->replay();
            $this->fail('A closed 2025 period must abort the run.');
        } catch (LegacyReplayAborted $e) {
            $this->assertStringContainsString('06=locked', $e->getMessage());
        }

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    /**
     * §6 guard (b) -- THE silent parity killer. `PostingService` catches
     * `PeriodLockedException` and posts with a SHIFTED `posting_date` while
     * `transaction_date` keeps the legacy date, and `TrialBalanceService` buckets
     * by `COALESCE(posting_date, transaction_date)`. Money moves between periods
     * and nothing fails.
     *
     * The period is locked from inside the run (via the progress callback, after
     * the first document) precisely so preflight cannot be what catches it --
     * this test exercises the IN-RUN guard, not the preflight one.
     *
     * MUTATION PROOF (posting_date guard): delete the
     * `assertNoPostingDateShift()` call from
     * LegacyReplayRunner::processDocument() and this test fails -- the June
     * document posts happily with posting_date 2025-07-01 and the run reports
     * two posted documents.
     */
    public function test_a_posting_date_shift_fails_the_whole_run(): void
    {
        $march = $this->balancedDocument('INV', '2025-03-01', '5.000', ['DocID' => 10]);
        $june = $this->balancedDocument('INV', '2025-06-15', '6.000', ['DocID' => 20]);

        $runner = app(LegacyReplayRunner::class);

        try {
            $runner->run(
                ['company_id' => $this->company->id, 'user_id' => $this->user->id, 'year' => 2025],
                function (array $outcome): void {
                    if ((int) $outcome['doc_id'] === 10) {
                        AccountingPeriod::create([
                            'company_id' => $this->company->id,
                            'year' => 2025,
                            'month' => 6,
                            'status' => AccountingPeriod::STATUS_LOCKED,
                        ]);
                    }
                }
            );
            $this->fail('A posting_date shift must fail the whole run.');
        } catch (LegacyReplayAborted $e) {
            $this->assertStringContainsString('accounting.posting_date_shifted', $e->getMessage());
            $this->assertStringContainsString('document '.$june, $e->getMessage());
        }

        $this->assertSame('posted', $this->auditFor($march)->status);
    }

    /**
     * §1.5 #1 / §1.3: the engine being OFF is not a "skip everything" state --
     * the replay's legacy closure throws, so the run must refuse to start.
     */
    public function test_the_run_refuses_to_start_when_the_posting_engine_is_off(): void
    {
        $this->balancedDocument('INV', '2025-03-01', '5.000');

        config(['accounting.engine.enabled' => false]);

        $this->expectException(LegacyReplayAborted::class);
        $this->expectExceptionMessageMatches('/Posting engine is NOT enabled/');

        $this->replay();
    }

    /**
     * LP4b: the refusal has to be actionable. It names BOTH prerequisites --
     * the ENV VAR that sets the global flag (not just the config key it is
     * read through, which does not exist as a literal in config/accounting.php)
     * and the per-company column -- and says which of the two is off, so the
     * operator does not have to guess which half to go and fix.
     */
    public function test_the_engine_off_refusal_names_the_env_var_and_the_company_flag_and_which_is_off(): void
    {
        $this->balancedDocument('INV', '2025-03-01', '5.000');

        config(['accounting.engine.enabled' => false]);

        try {
            $this->replay();
            $this->fail('The replay must refuse to start with the engine off.');
        } catch (LegacyReplayAborted $e) {
            $message = $e->getMessage();

            $this->assertStringContainsString('ACCOUNTING_ENGINE_ENABLED', $message);
            $this->assertStringContainsString('companies.posting_engine_enabled', $message);
            // The company flag IS on in this fixture; only the env-backed
            // global is off, and the message must distinguish them.
            $this->assertMatchesRegularExpression('/ACCOUNTING_ENGINE_ENABLED.*OFF/s', $message);
            $this->assertMatchesRegularExpression('/companies\.posting_engine_enabled.*ON/s', $message);
        }
    }

    /**
     * The mirror case: the global flag on, the company flag off. Naming only
     * the env var here would send the operator to the wrong file.
     */
    public function test_the_engine_off_refusal_reports_the_company_flag_when_that_is_the_half_that_is_off(): void
    {
        $this->balancedDocument('INV', '2025-03-01', '5.000');

        config(['accounting.engine.enabled' => true]);
        $this->company->forceFill(['posting_engine_enabled' => false])->save();

        try {
            $this->replay();
            $this->fail('The replay must refuse to start with the company flag off.');
        } catch (LegacyReplayAborted $e) {
            $message = $e->getMessage();

            $this->assertMatchesRegularExpression('/ACCOUNTING_ENGINE_ENABLED.*ON/s', $message);
            $this->assertMatchesRegularExpression('/companies\.posting_engine_enabled.*OFF/s', $message);
            $this->assertStringContainsString('UPDATE companies SET posting_engine_enabled = 1', $message);
        }
    }

    /**
     * Coordinator ruling 2026-09-08: 12 of the 21 legacy `IsFreeze` accounts
     * carry 2025 lines, and a legacy freeze is FORWARD-LOOKING -- those lines
     * must replay. With the flag on (its default), an account behind a frozen
     * leaf that arrived soft-deleted is an import DEFECT and aborts the run
     * rather than quietly tagging thousands of documents.
     */
    public function test_a_soft_deleted_account_behind_a_frozen_legacy_leaf_aborts_the_run(): void
    {
        $this->assertTrue(config('legacy_pilot.replay.ignore_frozen_for_legacy_docs'), 'the flag defaults ON');

        $this->mapLegacyAccount($this->company->id, 5555, $this->bankAccount->id, 'direct', null, true);
        DB::table('accounts')->where('id', $this->bankAccount->id)->update(['deleted_at' => now()]);

        $this->expectException(LegacyReplayAborted::class);
        $this->expectExceptionMessageMatches('/forward-looking/');

        $this->replay();
    }

    /** A frozen legacy leaf whose Akeed account is live replays without complaint. */
    public function test_a_frozen_legacy_leaf_with_a_live_account_replays_normally(): void
    {
        $this->mapLegacyAccount($this->company->id, 5555, $this->bankAccount->id, 'direct', null, true);

        $docId = $this->stageHeader(['SubType' => 'CPV', 'DocType' => 'PV', 'DocDt' => '2025-03-10', 'DocNo' => 'CPV/CO/25/frozen']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Debit' => '4.000', 'DC' => 'D', 'DocDt' => '2025-03-10']);
        $this->stageLine($docId, ['AccID_FK' => '5555', 'Credit' => '4.000', 'DC' => 'C', 'DocDt' => '2025-03-10']);

        $this->assertSame(1, $this->replay()['posted']);
    }

    /**
     * `LegacyCoaImporter` preserves a legacy IsFreeze as `accounts.disabled = 1`,
     * and PostingService refuses a disabled account. A pilot instance that has
     * not set the bypass would discover that mid-run, 12 accounts' worth of
     * documents in, so the runner refuses to start and names the variable.
     */
    public function test_a_disabled_frozen_leaf_aborts_at_preflight_when_the_bypass_is_not_configured(): void
    {
        $this->assertNull(config('legacy_pilot.replay.frozen_leaf_bypass_sub_type_like'), 'the engine bypass is off by default');

        $this->mapLegacyAccount($this->company->id, 5555, $this->bankAccount->id, 'direct', null, true);
        DB::table('accounts')->where('id', $this->bankAccount->id)->update(['disabled' => true]);

        $this->balancedDocument('INV', '2025-03-01', '5.000');

        $this->expectException(LegacyReplayAborted::class);
        $this->expectExceptionMessageMatches('/LEGACY_PILOT_FROZEN_BYPASS_SUB_TYPE_LIKE/');

        $this->replay();
    }

    /**
     * §4.2 -- the parity crux. Every posted document's line count and sums must
     * equal the staged ones. The assertion is proven live here by recording what
     * it compares; its mutation proof is stated on the class.
     */
    public function test_the_audit_records_the_staged_and_posted_sums_for_every_document(): void
    {
        $docId = $this->balancedDocument('INV', '2025-03-01', '123.456');

        $this->replay();

        $audit = $this->auditFor($docId);
        $this->assertSame('123.456', (string) $audit->staged_debit_sum);
        $this->assertSame('123.456', (string) $audit->staged_credit_sum);
        $this->assertSame('123.456', (string) $audit->posted_debit_sum);
        $this->assertSame('123.456', (string) $audit->posted_credit_sum);
        $this->assertSame(2, (int) $audit->staged_line_count);
        $this->assertSame(2, (int) $audit->posted_line_count);
    }
}
