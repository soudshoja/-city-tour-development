<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Replay;

use App\Exceptions\Accounting\FrozenAccountException;
use App\Exceptions\Accounting\PostingException;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use App\Services\Onboarding\LegacyColumn;
use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\LegacyStagingCast;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- the replay engine (PLAN.md §LP3, MAPPING-RULES.md
 * §1.3, §1.8, §4.2, §6, §8).
 *
 * Chronological by (DocDt, DocID). Idempotent through the seam's own key.
 * Resumable after a crash. One audit row per document in `map_document`, one
 * per line in `map_document_line`. NOTHING is ever silently skipped: every
 * document the selector yields ends up with a row and a status.
 *
 * ── The five hard rules this class exists to enforce ─────────────────────────
 *  1. `PostingSeam::post()` is the ONLY write path. No draft builder, no
 *     PostingService, no JournalEntry/Transaction write anywhere in this
 *     namespace (the ArchitectureTest sole-writer ratchet).
 *  2. The `$legacy` closure THROWS. A legacy fallback during a migration is a
 *     silent-corruption path, not a safety net -- and because it throws, an
 *     accidentally-OFF engine aborts on document 1 instead of quietly doing
 *     nothing for eight hours.
 *  3. Any return that is not a `PostedDocument` -- including the documented
 *     bare `null` the seam returns on the OFF path when the key already exists
 *     -- is a HARD ERROR, not a success.
 *  4. §4.2's parity crux, made testable: for every replayed document the posted
 *     line count must equal the number of non-zero staged lines and the posted
 *     sums must equal the staged sums exactly. Any extra line, anywhere, fails
 *     the document and aborts the run.
 *  5. §6's silent-shift trap: `PostingService` CATCHES `PeriodLockedException`
 *     and posts with a shifted `posting_date` while `transaction_date` keeps the
 *     legacy date, and `TrialBalanceService` buckets by
 *     `COALESCE(posting_date, transaction_date)`. A shift moves money between
 *     periods without failing anything. The run therefore (a) asserts every
 *     window period is open before it starts and (b) aborts on the first
 *     document whose posted `posting_date` differs from its `transaction_date`.
 *
 * ── Why the chronological walk is done in PHP ───────────────────────────────
 * Every stg_* column is `text` (see {@see LegacyStagingCast}). `Posted` is the literal
 * `'True'`, so `where('posted', 1)` selects NOTHING -- silently. `DocDt` is a
 * date string whose shape is the export's, so a SQL `BETWEEN` is a
 * lexicographic comparison that only accidentally works. Both failures look
 * exactly like a finished run. So the header key set is pulled once (a handful
 * of small columns over ~37 k rows), coerced and filtered HERE, and sorted
 * here; only then are full headers and lines loaded, in batches.
 */
final class LegacyReplayRunner
{
    private const H_DOC_ID = 'DocID';

    private const H_DOC_DT = 'DocDt';

    private const H_SUB_TYPE = 'SubType';

    private const H_POSTED = 'Posted';

    private const H_DOC_YEAR = 'DocYear';

    public function __construct(
        private readonly PostingSeam $seam,
        private readonly LegacyDocumentMapper $mapper,
    ) {}

    /**
     * @param  array{company_id?:int,user_id?:int,year?:int,type?:?string,from_doc?:?int,limit?:?int,dry_run?:bool,run_id?:string}  $options
     * @param  callable(array<string,mixed>):void|null  $progress  called once per document
     * @return array<string, mixed> the run summary
     *
     * @throws LegacyReplayAborted
     */
    public function run(array $options = [], ?callable $progress = null): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $companyId = (int) ($options['company_id'] ?? config('legacy_pilot.default_company_id'));
        $userId = (int) ($options['user_id'] ?? 0);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $runId = (string) ($options['run_id'] ?? bin2hex(random_bytes(8)));
        $typeFilter = isset($options['type']) && $options['type'] !== null ? strtoupper((string) $options['type']) : null;
        $fromDoc = isset($options['from_doc']) && $options['from_doc'] !== null ? (int) $options['from_doc'] : null;
        $limit = isset($options['limit']) && $options['limit'] !== null ? (int) $options['limit'] : null;

        $this->assertPreflight($companyId, $options, $dryRun);

        $candidates = $this->selectDocuments($companyId, $options, $typeFilter, $fromDoc);

        $summary = $this->emptySummary($runId, $companyId, $dryRun);
        $summary['selected'] = count($candidates);

        $alreadyPosted = $dryRun ? [] : $this->alreadyPostedDocIds($companyId);

        /** @var array<string, array{seen:int,refused:int,streak_code:?string,streak:int,stopped:bool}> $typeState */
        $typeState = [];
        $processed = 0;

        foreach ($candidates as $candidate) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }

            $docId = $candidate['doc_id'];
            $subType = $candidate['sub_type'];

            // §8.4 resumability: any DocID WITHOUT a status='posted' row is
            // retried. A DocID with one is skipped without touching the engine,
            // which is what makes "a second full run posts ZERO new journal
            // entries" true even before the seam's own idempotency guard.
            if (isset($alreadyPosted[$docId])) {
                $summary['already_posted']++;

                continue;
            }

            $processed++;
            $typeState[$subType] ??= ['seen' => 0, 'refused' => 0, 'streak_code' => null, 'streak' => 0, 'stopped' => false];

            if ($typeState[$subType]['stopped']) {
                $summary['skipped_after_type_stop']++;
                $summary['by_type'][$subType]['skipped_after_type_stop'] = ($summary['by_type'][$subType]['skipped_after_type_stop'] ?? 0) + 1;

                continue;
            }

            $typeState[$subType]['seen']++;

            $outcome = $this->processDocument($candidate, $companyId, $userId, $dryRun, $runId);

            $this->recordOutcome($summary, $typeState, $subType, $outcome);

            if ($progress !== null) {
                $progress($outcome + ['sub_type' => $subType, 'doc_id' => $docId]);
            }
        }

        $summary['type_stops'] = array_keys(array_filter($typeState, fn (array $s): bool => $s['stopped']));
        $summary['passed'] = $summary['refused'] === 0 && $summary['type_stops'] === [];

        return $summary;
    }

    /**
     * MAPPING-RULES §6 + §1.5 #1 + the coordinator's frozen-account ruling.
     * Every one of these is a whole-run abort: none of them can be true and the
     * replay still mean anything.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws LegacyReplayAborted
     */
    private function assertPreflight(int $companyId, array $options, bool $dryRun): void
    {
        if (! $dryRun && ! $this->seam->isEnabledFor($companyId)) {
            // LP4b: name BOTH prerequisites, and say which one is off. The
            // old wording named the config keys but not the ENV VAR that
            // sets the global one, so "config accounting.engine.enabled" sent
            // more than one operator hunting through config/accounting.php
            // for a value that is only ever read from the environment.
            $globalOn = (bool) config('accounting.engine.enabled');
            $companyOn = (bool) Company::query()->whereKey($companyId)->value('posting_engine_enabled');

            throw new LegacyReplayAborted(sprintf(
                'Posting engine is NOT enabled for company %d. legacy:replay requires BOTH of these, and refuses to start without them '
                .'(the replay\'s legacy closure throws, so every document would abort individually):'
                .PHP_EOL.'  1. ACCOUNTING_ENGINE_ENABLED=true in .env  (read as config accounting.engine.enabled)  — currently %s'
                .PHP_EOL.'  2. companies.posting_engine_enabled = 1 for company %d  '
                .'(UPDATE companies SET posting_engine_enabled = 1 WHERE id = %d;)  — currently %s'
                .PHP_EOL.'Set the missing one, run `php artisan config:clear`, and re-run.',
                $companyId,
                $globalOn ? 'ON' : 'OFF',
                $companyId,
                $companyId,
                $companyOn ? 'ON' : 'OFF',
            ));
        }

        $year = (int) ($options['year'] ?? \Carbon\CarbonImmutable::parse((string) config('legacy_pilot.replay.window_start'))->year);

        // §6: 2025 periods must be OPEN before the run. A missing row is treated
        // as open by PeriodGuard, so only a present non-open row is a failure.
        $closed = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('status', '!=', AccountingPeriod::STATUS_OPEN)
            ->orderBy('month')
            ->get(['month', 'status']);

        if ($closed->isNotEmpty()) {
            throw new LegacyReplayAborted(sprintf(
                'Period(s) for %d are not open: %s. MAPPING-RULES §6 requires every window period OPEN before the replay starts — allowLockedPeriods is deliberately false, and PostingService would SHIFT the posting_date rather than fail.',
                $year,
                $closed->map(fn ($p): string => sprintf('%02d=%s', $p->month, $p->status))->implode(', ')
            ));
        }

        if ((bool) config('legacy_pilot.replay.ignore_frozen_for_legacy_docs', true)) {
            $this->assertFrozenLeavesArePostable($companyId);
        }
    }

    /**
     * Coordinator ruling 2026-09-08: a legacy `IsFreeze` is FORWARD-LOOKING, so
     * the 2025 lines on those 12 accounts MUST replay. `LegacyCoaImporter`
     * preserves the freeze as `accounts.disabled = 1`, and `PostingService`
     * step 3e refuses a disabled account with `FrozenAccountException` unless
     * BOTH pilot keys are set -- so a pilot instance that forgets the env var
     * would otherwise discover the problem 12 accounts' worth of documents into
     * an unattended overnight run.
     *
     * This preflight turns that into one sentence at second zero, naming the
     * exact variable to set. It also covers the soft-delete condition, which no
     * flag bypasses.
     *
     * @throws LegacyReplayAborted
     */
    private function assertFrozenLeavesArePostable(int $companyId): void
    {
        $frozenAccountIds = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->where('legacy_is_freeze', true)
            ->whereNotNull('account_id')
            ->pluck('account_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($frozenAccountIds === []) {
            return;
        }

        $softDeleted = DB::table('accounts')->whereIn('id', $frozenAccountIds)->whereNotNull('deleted_at')->pluck('id')->all();

        if ($softDeleted !== []) {
            throw new LegacyReplayAborted(sprintf(
                'Account(s) %s sit behind a legacy IsFreeze leaf and are SOFT-DELETED on the Akeed side. A legacy freeze is forward-looking (MAPPING-RULES §1.5 #6): their 2025 lines MUST replay, so this is an import defect, not data, and no flag bypasses a soft delete.',
                implode(', ', $softDeleted)
            ));
        }

        $disabled = DB::table('accounts')->whereIn('id', $frozenAccountIds)->where('disabled', true)->pluck('id')->all();

        if ($disabled !== [] && config('legacy_pilot.replay.frozen_leaf_bypass_sub_type_like') === null) {
            throw new LegacyReplayAborted(sprintf(
                'Account(s) %s carry the legacy freeze as accounts.disabled = 1, and PostingService refuses a disabled account. A legacy freeze is forward-looking, so these documents MUST replay: set LEGACY_PILOT_FROZEN_BYPASS_SUB_TYPE_LIKE=%s%% on this pilot instance before running. Refusing to start rather than tagging thousands of documents mid-run.',
                implode(', ', $disabled),
                (string) config('legacy_pilot.replay.ledger_type_prefix')
            ));
        }
    }

    /**
     * The document selector, MAPPING-RULES §2.14:
     *   Posted = 1 AND (DocDt in the window OR (SubType='OJV' AND DocYear=2025))
     * with the withheld OJV years excluded outright.
     *
     * Unposted headers get a `skipped_unposted` audit row (O11: exclude, COUNT,
     * report) rather than vanishing.
     *
     * @param  array<string, mixed>  $options
     * @return list<array{doc_id:int,sub_type:string,doc_dt:string,sort:string}>
     */
    private function selectDocuments(int $companyId, array $options, ?string $typeFilter, ?int $fromDoc): array
    {
        $col = static fn (string $legacy): string => LegacyColumn::name($legacy);

        $year = isset($options['year']) ? (int) $options['year'] : null;
        $windowStart = \Carbon\CarbonImmutable::parse((string) config('legacy_pilot.replay.window_start'));
        $windowEnd = \Carbon\CarbonImmutable::parse((string) config('legacy_pilot.replay.window_end'));

        if ($year !== null) {
            $windowStart = $windowStart->setYear($year)->startOfYear();
            $windowEnd = $windowEnd->setYear($year)->endOfYear();
        }

        $openingOjvYear = (int) config('legacy_pilot.replay.opening_ojv_doc_year');
        $withheld = array_map('intval', (array) config('legacy_pilot.replay.withheld_ojv_doc_years', []));

        $rows = DB::connection('legacy_pilot')->table('stg_acc_header')->get([
            $col(self::H_DOC_ID), $col(self::H_DOC_DT), $col(self::H_SUB_TYPE),
            $col(self::H_POSTED), $col(self::H_DOC_YEAR),
        ]);

        $selected = [];

        foreach ($rows as $row) {
            $docId = LegacyStagingCast::toInt($row->{$col(self::H_DOC_ID)} ?? null);
            $subType = LegacyStagingCast::toString($row->{$col(self::H_SUB_TYPE)} ?? null);

            if ($docId === null || $subType === null) {
                continue;
            }

            $subType = strtoupper($subType);
            $docDate = LegacyStagingCast::toDate($row->{$col(self::H_DOC_DT)} ?? null);
            $docYear = LegacyStagingCast::toInt($row->{$col(self::H_DOC_YEAR)} ?? null);

            $isOpeningOjv = $subType === 'OJV' && $docYear === $openingOjvYear;
            $inWindow = $docDate !== null && $docDate->greaterThanOrEqualTo($windowStart) && $docDate->lessThanOrEqualTo($windowEnd);

            if (! $inWindow && ! $isOpeningOjv) {
                continue;
            }

            // §2.10 (b): the withheld OJV never enters the run at all. The mapper
            // ALSO refuses it with class `withheld_ojv` — belt and braces, because
            // a hand-run `--type=OJV` must not be able to post it either.
            if ($subType === 'OJV' && in_array($docYear, $withheld, true)) {
                continue;
            }

            if ($typeFilter !== null && $subType !== $typeFilter) {
                continue;
            }

            if ($fromDoc !== null && $docId < $fromDoc) {
                continue;
            }

            $selected[] = [
                'doc_id' => $docId,
                'sub_type' => $subType,
                'doc_dt' => $docDate?->toDateString() ?? '',
                // §2.14: `Posted` is the literal 'True'/'False' text. NEVER
                // filtered in SQL; coerced here. A cell that is neither reads as
                // NOT posted, so an unreadable flag can never post a document.
                'posted' => LegacyStagingCast::toBool($row->{$col(self::H_POSTED)} ?? null),
                'sort' => sprintf('%s|%020d', $docDate?->toDateString() ?? '9999-12-31', $docId),
            ];
        }

        usort($selected, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $selected;
    }

    /** @return array<int, true> */
    private function alreadyPostedDocIds(int $companyId): array
    {
        return DB::connection('legacy_pilot')->table('map_document')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->pluck('legacy_doc_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @param  array{doc_id:int,sub_type:string,doc_dt:string,posted:bool,sort:string}  $candidate
     * @return array<string, mixed>
     *
     * @throws LegacyReplayAborted
     */
    private function processDocument(array $candidate, int $companyId, int $userId, bool $dryRun, string $runId): array
    {
        $docId = $candidate['doc_id'];
        $startedAt = microtime(true);

        $header = DB::connection('legacy_pilot')->table('stg_acc_header')
            ->where(LegacyColumn::name(self::H_DOC_ID), (string) $docId)
            ->first();

        if ($header === null) {
            throw new LegacyReplayAborted(sprintf('Header %d disappeared between selection and replay.', $docId));
        }

        $base = $this->auditBase($header, $candidate, $companyId, $runId);

        // §2.14 / O11: excluded, COUNTED, reported. The export's own TB
        // definitions are Posted=1-only; including these would guarantee a
        // parity mismatch.
        if (! $candidate['posted']) {
            return $this->finish($base, ['status' => 'skipped_unposted'], $dryRun, $startedAt, []);
        }

        try {
            $mapped = $this->mapper->map($header, $companyId, $userId);
        } catch (LegacyDocumentRefused $e) {
            return $this->finish($base, $this->refusalRow($e), $dryRun, $startedAt, []);
        }

        $base['staged_debit_sum'] = LegacyAmount::toDecimalString($mapped->stagedDebitMillis);
        $base['staged_credit_sum'] = LegacyAmount::toDecimalString($mapped->stagedCreditMillis);
        $base['staged_line_count'] = $mapped->stagedLineCount;
        $base['dropped_zero_line_count'] = $mapped->droppedZeroLineCount;
        $base['dc_mismatch_line_count'] = $mapped->dcMismatchLineCount;
        $base['fc_rebased_line_count'] = $mapped->fcRebasedLineCount;
        $base['off_branch_line_count'] = $mapped->offBranchLineCount;
        $base['currency_unresolved_line_count'] = $mapped->currencyUnresolvedLineCount;

        if (! $mapped->isPostable()) {
            // §1.6 (2)/(3): skip, count, report. Not an error.
            return $this->finish($base, ['status' => $mapped->status], $dryRun, $startedAt, []);
        }

        $base['idempotency_key'] = $mapped->draft->idempotencyKey;

        if ($dryRun) {
            return $this->finish($base, ['status' => 'dry_run'], true, $startedAt, $mapped->lineAudits);
        }

        try {
            $posted = $this->seam->post(
                $mapped->draft,
                // §1.3: the legacy fallback THROWS.
                static function (): never {
                    throw new \LogicException('legacy fallback reached during replay');
                },
                (string) config('legacy_pilot.replay.feeder_key'),
            );
        } catch (FrozenAccountException $e) {
            if ((bool) config('legacy_pilot.replay.ignore_frozen_for_legacy_docs', true)) {
                throw new LegacyReplayAborted(sprintf(
                    'Document %d was refused with FrozenAccountException while legacy_pilot.replay.ignore_frozen_for_legacy_docs is on. A legacy freeze is forward-looking, so this is a DEFECT, not a document to tag: %s',
                    $docId,
                    $e->getMessage()
                ));
            }

            return $this->finish($base, $this->exceptionRow($e), false, $startedAt, $mapped->lineAudits);
        } catch (PostingException|\InvalidArgumentException $e) {
            return $this->finish($base, $this->exceptionRow($e), false, $startedAt, $mapped->lineAudits);
        }

        // §1.3 rule 3 -- anything that is not a PostedDocument (the documented
        // bare null included) is a hard error, never a success.
        if (! $posted instanceof PostedDocument) {
            throw new LegacyReplayAborted(sprintf(
                'PostingSeam::post() returned %s for document %d. Only a PostedDocument is a successful post; a bare null means the engine was OFF and the key already existed.',
                get_debug_type($posted),
                $docId
            ));
        }

        $this->assertNoPostingDateShift($posted, $docId);
        $this->assertLineCountAndSums($posted, $mapped, $docId);

        [$postedDebit, $postedCredit] = $this->postedSums($posted);

        return $this->finish($base, [
            'status' => 'posted',
            'document_id' => (int) $posted->transaction->id,
            'our_reference_number' => $posted->transaction->reference_number,
            'posted_debit_sum' => LegacyAmount::toDecimalString($postedDebit),
            'posted_credit_sum' => LegacyAmount::toDecimalString($postedCredit),
            'posted_line_count' => count($posted->lines),
        ], false, $startedAt, $mapped->lineAudits, $posted);
    }

    /**
     * §6 / LP4 check 11. `PostingService` catches `PeriodLockedException` and
     * posts with a shifted `posting_date` while `transaction_date` keeps the
     * legacy date, logging `accounting.posting_date_shifted`. Comparing the two
     * persisted dates detects exactly that event, on the document it happened
     * to, without depending on log capture.
     *
     * @throws LegacyReplayAborted
     */
    private function assertNoPostingDateShift(PostedDocument $posted, int $docId): void
    {
        $transactionDate = $posted->transaction->transaction_date;
        $postingDate = $posted->transaction->posting_date;

        if ($postingDate === null || $transactionDate === null) {
            return;
        }

        $a = \Carbon\CarbonImmutable::parse((string) $transactionDate)->toDateString();
        $b = \Carbon\CarbonImmutable::parse((string) $postingDate)->toDateString();

        if ($a !== $b) {
            throw new LegacyReplayAborted(sprintf(
                'accounting.posting_date_shifted on document %d (transaction #%d): transaction_date %s but posting_date %s. TrialBalanceService buckets by COALESCE(posting_date, transaction_date), so a shift moves money between periods without failing anything. The run stops here.',
                $docId,
                $posted->transaction->id,
                $a,
                $b
            ));
        }
    }

    /**
     * §4.2 -- "the parity crux made testable". It has TWO directions, and only
     * one of them is visible from the draft:
     *
     *  (a) something ADDS a line the legacy document did not have -- an
     *      observer, a model hook, a future draft-builder leak. PostingService
     *      inserts exactly count($draft->lines) rows, so draft-vs-posted
     *      catches it.
     *
     *  (b) the MAPPER silently drops a line it should have mapped.
     *      Draft-vs-posted is blind to this BY CONSTRUCTION -- the draft is the
     *      mapper's own output, so posted == draft holds trivially while both
     *      are short of the staged truth. The mapper's balance check does not
     *      save it either: omitting a BALANCED PAIR leaves Sigma-debit ==
     *      Sigma-credit, so the document posts, balances, satisfies every engine
     *      invariant, and is silently missing money from the anchor. Verified:
     *      with only the draft comparison in place, a mapper mutated to skip a
     *      balanced pair kept the entire LP3 suite green.
     *
     * So the staged side is read INDEPENDENTLY of anything the mapper decided:
     * `stagedLineCount` is a COUNT over `stg_acc_detail` for this DocID, and
     * `droppedZeroLineCount` is the §1.6 (1) zero-amount drop, which is a rule
     * rather than a judgement. Their difference is what the ledger must carry.
     * Pinned by {@see \Tests\Feature\Legacy\LegacyLineCountParityTest}.
     *
     * @throws LegacyReplayAborted
     */
    private function assertLineCountAndSums(PostedDocument $posted, MappedDocument $mapped, int $docId): void
    {
        $drafted = count($mapped->draft->lines);
        $actual = count($posted->lines);

        if ($drafted !== $actual) {
            throw new LegacyReplayAborted(sprintf(
                'Document %d posted %d journal lines but the draft carried %d. Something added a line the legacy document did not have (MAPPING-RULES §4.2).',
                $docId,
                $actual,
                $drafted
            ));
        }

        // Direction (b). The staged truth, not the mapper's opinion of it.
        $expectedFromStaging = $mapped->stagedLineCount - $mapped->droppedZeroLineCount;

        if ($expectedFromStaging !== $actual) {
            throw new LegacyReplayAborted(sprintf(
                'Document %d posted %d journal lines but stg_acc_detail holds %d staged lines of which %d were dropped as zero-amount, so %d were expected. The MAPPER lost a line (MAPPING-RULES §4.2) — a balanced pair going missing this way still balances, so nothing else can catch it.',
                $docId,
                $actual,
                $mapped->stagedLineCount,
                $mapped->droppedZeroLineCount,
                $expectedFromStaging
            ));
        }

        [$postedDebit, $postedCredit] = $this->postedSums($posted);

        if ($postedDebit !== $mapped->stagedDebitMillis || $postedCredit !== $mapped->stagedCreditMillis) {
            throw new LegacyReplayAborted(sprintf(
                'Document %d posted Dr %s / Cr %s but the staged decimals are Dr %s / Cr %s (MAPPING-RULES §4.2, §5.3 float-drift canary).',
                $docId,
                LegacyAmount::toDecimalString($postedDebit),
                LegacyAmount::toDecimalString($postedCredit),
                LegacyAmount::toDecimalString($mapped->stagedDebitMillis),
                LegacyAmount::toDecimalString($mapped->stagedCreditMillis)
            ));
        }
    }

    /** @return array{0:int,1:int} debit/credit millis */
    private function postedSums(PostedDocument $posted): array
    {
        $debit = 0;
        $credit = 0;

        foreach ($posted->lines as $line) {
            // The persisted values are DECIMAL columns; read them as the strings
            // the driver hands back so the comparison stays exact.
            $debit += LegacyAmount::millis($line->debit, 'posted debit');
            $credit += LegacyAmount::millis($line->credit, 'posted credit');
        }

        return [$debit, $credit];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function auditBase(object $header, array $candidate, int $companyId, string $runId): array
    {
        $col = static fn (string $legacy): string => LegacyColumn::name($legacy);

        return [
            'company_id' => $companyId,
            'legacy_doc_id' => $candidate['doc_id'],
            'legacy_doc_no' => LegacyStagingCast::toString($header->{$col('DocNo')} ?? null),
            'sub_type' => $candidate['sub_type'],
            // The Akeed-side value, identical to transactions.sub_type. LP4's
            // opening/movement partition keys on LEGACY_OJV and must never have
            // to re-derive it from the legacy token.
            'engine_sub_type' => config('legacy_pilot.replay.doc_type_map')[$candidate['sub_type']]['sub_type'] ?? null,
            'legacy_doc_type' => LegacyStagingCast::toString($header->{$col('DocType')} ?? null),
            'doc_dt' => $candidate['doc_dt'] !== '' ? $candidate['doc_dt'] : null,
            'legacy_branch_id' => LegacyStagingCast::toInt($header->{$col('BranchID_FK')} ?? null),
            'legacy_ref_type' => LegacyStagingCast::toString($header->{$col('RefType')} ?? null),
            'legacy_ref_no' => LegacyStagingCast::toString($header->{$col('RefNo')} ?? null),
            'legacy_ref_code' => LegacyStagingCast::toString($header->{$col('RefCode')} ?? null),
            'legacy_posted' => $candidate['posted'],
            'run_id' => $runId,
            // Every nullable audit column is defaulted here rather than left
            // absent, because a RESUMED run updates a row that may already carry
            // a previous attempt's refusal. An absent key would leave the stale
            // refusal_class/exception sitting on a document that has since posted.
            'idempotency_key' => null,
            'refusal_class' => null,
            'failure_code' => null,
            'exception_class' => null,
            'exception_message' => null,
            'document_id' => null,
            'our_reference_number' => null,
            'posted_debit_sum' => null,
            'posted_credit_sum' => null,
            'posted_line_count' => 0,
            'staged_debit_sum' => '0.000',
            'staged_credit_sum' => '0.000',
            'staged_line_count' => 0,
            'dropped_zero_line_count' => 0,
            'dc_mismatch_line_count' => 0,
            'fc_rebased_line_count' => 0,
            'off_branch_line_count' => 0,
            'currency_unresolved_line_count' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function refusalRow(LegacyDocumentRefused $e): array
    {
        // §2.10 (b): a document withheld BY RULE is not an unclassified refusal
        // — O5's pass line requires `refused = 0`, and counting a deliberate
        // withhold as a refusal would make that identity unsatisfiable. It is
        // recorded as its own skip status with the refusal's own class token
        // preserved in failure_code.
        if (in_array($e->failureCode, (array) config('legacy_pilot.replay.withhold_failure_codes', []), true)) {
            return [
                'status' => 'skipped_withheld_ojv',
                'failure_code' => $e->failureCode,
                'exception_message' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'refused',
            'failure_code' => $e->failureCode,
            'refusal_class' => $this->classify($e->failureCode),
            'exception_class' => LegacyDocumentRefused::class,
            'exception_message' => $e->getMessage(),
        ];
    }

    /** @return array<string, mixed> */
    private function exceptionRow(\Throwable $e): array
    {
        $short = class_basename($e);

        return [
            'status' => 'refused',
            'failure_code' => $short,
            'refusal_class' => $this->classify($short),
            'exception_class' => $e::class,
            'exception_message' => $e->getMessage(),
        ];
    }

    /**
     * §1.8's three buckets. A structural code describes the MAPPING, so it is a
     * mapping defect; everything else the engine or the mapper raised on one
     * document is a legacy data defect until a human re-files it. O5 requires
     * ZERO unclassified refusals — so there is no "unknown" branch.
     */
    private function classify(string $failureCode): string
    {
        return $this->isStructural($failureCode) ? 'mapping_defect' : 'legacy_data_defect';
    }

    private function isStructural(string $failureCode): bool
    {
        return in_array($failureCode, (array) config('legacy_pilot.replay.o4.structural_failure_codes', []), true);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $result
     * @param  array<int, array<string, mixed>>  $lineAudits
     * @return array<string, mixed>
     */
    private function finish(array $base, array $result, bool $dryRun, float $startedAt, array $lineAudits, ?PostedDocument $posted = null): array
    {
        // NOTE the operand order: PHP's `+` keeps the LEFT operand on a key
        // collision, so the per-outcome result must be on the left or every
        // nullable default in $base would silently win.
        $row = $result + $base;
        $row['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        if ($dryRun) {
            return $row;
        }

        // A resumed run RETRIES every document without a `posted` row, so the
        // audit must be an upsert. created_at is written only on first sight —
        // the row's own age is evidence when a later run is being diagnosed.
        $key = ['company_id' => $row['company_id'], 'legacy_doc_id' => $row['legacy_doc_id']];
        $table = DB::connection('legacy_pilot')->table('map_document');

        if ($table->where($key)->exists()) {
            $table->where($key)->update($row + ['updated_at' => now()]);
        } else {
            $table->insert($row + ['created_at' => now(), 'updated_at' => now()]);
        }

        if ($posted !== null && $lineAudits !== []) {
            $this->writeLineAudits($row['company_id'], $lineAudits, $posted);
        }

        return $row;
    }

    /**
     * §8.2 -- map_document_line. Draft lines and posted lines are one-to-one and
     * IN ORDER (PostingService builds `$resolved` one-for-one from
     * `$draft->lines`), and {@see assertLineCountAndSums()} has already proven
     * the counts match, so index alignment is safe here and nowhere else.
     *
     * @param  array<int, array<string, mixed>>  $lineAudits
     */
    private function writeLineAudits(int $companyId, array $lineAudits, PostedDocument $posted): void
    {
        $rows = [];

        foreach ($lineAudits as $index => $audit) {
            $audit['company_id'] = $companyId;
            $audit['our_journal_entry_id'] = $posted->lines[$index]->id ?? null;
            $audit['amount'] = LegacyAmount::toDecimalString($audit['amount_millis']);
            unset($audit['amount_millis']);
            $audit['created_at'] = now();
            $audit['updated_at'] = now();
            $rows[] = $audit;
        }

        DB::connection('legacy_pilot')->table('map_document_line')->upsert(
            $rows,
            ['company_id', 'legacy_acc_detail_id'],
            array_keys(reset($rows) ?: [])
        );
    }

    /**
     * §1.8, operational form. Four independent stop conditions, all from config:
     * a structural class, a refusal inside the type's golden set, N consecutive
     * refusals with the SAME code from the type's first document, and the
     * type's refusal threshold max(floor, percent x census).
     *
     * @param  array<string, mixed>  $summary
     * @param  array<string, array{seen:int,refused:int,streak_code:?string,streak:int,stopped:bool}>  $typeState
     * @param  array<string, mixed>  $outcome
     */
    private function recordOutcome(array &$summary, array &$typeState, string $subType, array $outcome): void
    {
        $status = (string) $outcome['status'];

        $summary['by_status'][$status] = ($summary['by_status'][$status] ?? 0) + 1;
        $summary['by_type'][$subType][$status] = ($summary['by_type'][$subType][$status] ?? 0) + 1;

        if ($status === 'posted') {
            $summary['posted']++;
        } elseif ($status === 'dry_run') {
            $summary['mappable']++;
        } elseif (str_starts_with($status, 'skipped_')) {
            $summary['skipped']++;
        }

        if ($status !== 'refused') {
            $typeState[$subType]['streak_code'] = null;
            $typeState[$subType]['streak'] = 0;

            return;
        }

        $summary['refused']++;
        $typeState[$subType]['refused']++;

        $code = (string) ($outcome['failure_code'] ?? '');
        $summary['refusal_codes'][$code] = ($summary['refusal_codes'][$code] ?? 0) + 1;

        if ($typeState[$subType]['streak_code'] === $code) {
            $typeState[$subType]['streak']++;
        } else {
            $typeState[$subType]['streak_code'] = $code;
            $typeState[$subType]['streak'] = 1;
        }

        $o4 = (array) config('legacy_pilot.replay.o4');
        $reason = null;

        if ($this->isStructural($code)) {
            $reason = sprintf('structural failure code "%s"', $code);
        } elseif ($typeState[$subType]['seen'] <= (int) $o4['golden_set_size']) {
            $reason = sprintf('a refusal inside the type\'s golden set (first %d documents)', (int) $o4['golden_set_size']);
        } elseif ($typeState[$subType]['streak'] >= (int) $o4['same_class_streak'] && $typeState[$subType]['refused'] === $typeState[$subType]['seen']) {
            $reason = sprintf('the type\'s first %d documents all refused with the same code "%s"', $typeState[$subType]['streak'], $code);
        } else {
            $census = (int) (config('legacy_pilot.census_2025')[$subType] ?? 0);
            $threshold = max((int) $o4['threshold_floor'], (int) ceil($census * (float) $o4['threshold_percent']));

            if ($typeState[$subType]['refused'] > $threshold) {
                $reason = sprintf('refusals (%d) exceeded the type threshold max(%d, %s%% of %d) = %d', $typeState[$subType]['refused'], (int) $o4['threshold_floor'], (float) $o4['threshold_percent'] * 100, $census, $threshold);
            }
        }

        if ($reason !== null) {
            $typeState[$subType]['stopped'] = true;
            $summary['type_stop_reasons'][$subType] = $reason;
        }
    }

    /** @return array<string, mixed> */
    private function emptySummary(string $runId, int $companyId, bool $dryRun): array
    {
        return [
            'run_id' => $runId,
            'company_id' => $companyId,
            'dry_run' => $dryRun,
            'selected' => 0,
            'posted' => 0,
            'mappable' => 0,
            'skipped' => 0,
            'refused' => 0,
            'already_posted' => 0,
            'skipped_after_type_stop' => 0,
            'by_status' => [],
            'by_type' => [],
            'refusal_codes' => [],
            'type_stops' => [],
            'type_stop_reasons' => [],
            'passed' => false,
        ];
    }
}
