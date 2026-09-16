<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\BeforeImageOwnership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CT-A9 T3 — relabel the `journal_entries` rows that say a foreign currency and hold a base-currency
 * amount.
 *
 * ── What is actually wrong ─────────────────────────────────────────────────────────────────────
 * `CT-FX-EXPOSURE-2026-09-16.md` measured 4,553 live journal lines stamped `currency = 'USD'`,
 * `exchange_rate = 1.000000`, gross KWD 389,851.158, and proved five independent ways that the
 * amounts on them **are already Kuwaiti dinars**:
 *
 *   (a) the ledger amount equals the task's KWD `total`, never its foreign `original_total`;
 *   (b) rows labelled 'USD' carry other currencies' rates (AED 0.080000, EGP 0.006301, IQD
 *       0.000234 …) — a field that says USD while carrying the Bahraini dinar's rate is not a
 *       currency field;
 *   (c) one KWD control account (1351 Clients) carries all three labels at once;
 *   (d) the labelled subset does not balance within itself (out by KWD 74,536.254), so the label
 *       was applied per LINE, not per document;
 *   (e) no foreign face value exists anywhere — the largest line in the whole ledger is KWD
 *       15,643.500, while the un-converted faces would run to 2,140,400 XAF.
 *
 * The mechanism is a phantom read: `InvoiceController.php:5500,5530` writes
 * `'currency' => $task->currency ?? 'USD'` against a `tasks` table that **has no `currency` column
 * and never has**, so the literal always wins. The dev line already reads `?? 'KWD'`, which is why
 * the mislabelling stops at 2026-02-01 — and why this command repairs history rather than the
 * future.
 *
 * ── The predicate, and why it is narrower than "every USD row" ─────────────────────────────────
 * A row is repaired only when it is DEMONSTRABLY a base-currency amount, which here means it
 * asserts no foreign fact of its own:
 *
 *   - `currency` is present and is not the base currency (case/space-normalised);
 *   - `original_amount` IS NULL or 0 — **no foreign face value was ever recorded**;
 *   - `original_currency` IS NULL, or names the same code as `currency` (a row whose two currency
 *     columns disagree is asserting something this command has no business overwriting);
 *   - `exchange_rate` is 0.000000 or 1.000000 — **no real rate claims a conversion**.
 *
 * Everything else is REFUSED and reported. In particular the 510 live rows stamped 'USD' that
 * carry a real rate are refused: CT-FX proved their label is wrong, but it did not establish what
 * the RIGHT label is (a 0.080000 rate says AED, a 0.006301 says EGP — says, not proves), and a
 * command that guessed would be writing a currency nobody recorded. A row carrying
 * `original_amount > 0` is refused for the opposite reason: it really does assert a foreign face
 * value, and relabelling it to base would destroy the only FC fact on the ledger.
 *
 * ── The "zero risk" claim, VERIFIED rather than accepted ───────────────────────────────────────
 * `CT-FX-EXPOSURE` §8.2 calls this repair "fully repairable, zero risk". The first half holds on
 * the predicate above. The second half needed checking, because two services on the WRITE path read
 * `journal_entries.exchange_rate` and branch on whether it is > 0:
 *
 *   - {@see \App\Services\Accounting\RealisedFxService::compute()} (`.php:93-100`) SKIPS any apply
 *     whose source or applied line has `exchange_rate <= 0`. Moving a rate from 0.000000 to
 *     1.000000 stops it skipping, and it will then compute a realised FX difference for that apply.
 *   - {@see \App\Services\Accounting\CreditApplicationDraftBuilder::resolvePostedInvoiceRate()}
 *     (`.php:386-395`) returns null for a rate of 0 and the caller falls back to a LIVE lookup,
 *     logged as `accounting.credit_apply_rate_fallback_live_lookup`. At 1.000000 it stops falling
 *     back and uses the posted rate.
 *
 * Both changes are in the direction the ledger wants (a base-currency line's rate IS 1.000000 —
 * `CT-FX-EXPOSURE` §8.1 rule 3), and on the measured population the rate half touches only the ~28
 * live rows that carry a label AND a zero rate; the other 4,553 are already at 1.000000, where
 * writing 1.000000 is a no-op this command does not perform. But "zero risk" is not true as stated,
 * so every dry run prints the two service names and the exact row count they apply to, and
 * `--label-only` exists for an operator who wants the cosmetic half without the behavioural one.
 *
 * The CURRENCY half, by contrast, really is inert on this predicate, and that is checkable rather
 * than assertable: the one place a stored `currency` changes engine behaviour is
 * {@see \App\Services\Accounting\PostingService::reverse()}'s `$hasGenuineFcAmount`, which requires
 * `original_amount > 0` — excluded by the predicate by construction. `CoaController`'s per-currency
 * overlay reads `original_currency`, not `currency`. Every report sums `debit`/`credit` blindly.
 *
 * ── What this command deliberately does NOT do ─────────────────────────────────────────────────
 * It does not touch the 51,529 live rows whose `currency` IS NULL and whose rate is 0. A NULL
 * currency asserts nothing, so there is no mislabel to repair; and switching three quarters of the
 * ledger from rate 0 to rate 1 would enable `RealisedFxService` across the whole book in one
 * command. That is an owner decision, not a repair.
 *
 * It does not touch `debit`, `credit`, `amount`, `account_id` or any header column. No money moves;
 * the trial balance is byte-identical before and after.
 *
 * ── Ownership ──────────────────────────────────────────────────────────────────────────────────
 * Before-images go to `coa_linkage_changes` under the (subject_table, column_name) key CT-A8
 * established — `journal_entries.currency` and `journal_entries.exchange_rate`, both owned by THIS
 * command. `accounting:backfill-payable-party` owns `journal_entries.type_reference_id` and nothing
 * else; its rollback filters on the PAIR (CT-A9 fixed it — it used to filter on `subject_table`
 * alone, which would have made it restore this command's rows as if they were party references).
 */
class RepairCurrencyLabel extends Command
{
    protected $signature = 'accounting:repair-currency-label
                            {--company= : Company id to process (default: every company with accounts)}
                            {--dry-run : Report the full change list without writing anything (the default whenever --apply is absent)}
                            {--apply : Actually write the corrected labels}
                            {--label-only : Repair `currency` but leave `exchange_rate` alone — for an operator who wants the cosmetic half without the RealisedFxService / credit-apply behaviour change the rate half causes}
                            {--rollback= : Undo a previous --apply run by its run id. One run id PER --apply invocation.}
                            {--limit= : Cap the number of rows considered per company, for a staged rollout}
                            {--batch-size=500 : Rows per transaction. One batch = one transaction = one commit.}';

    protected $description = 'CT-A9 T3 — relabel journal_entries rows that name a foreign currency while holding a base-currency amount (the ?? \'USD\' phantom-column defect). Dry-run by default; records a per-row before-image; refuses every row that asserts a foreign fact of its own.';

    public const SUBJECT_TABLE = 'journal_entries';

    public const COLUMN_CURRENCY = 'currency';

    public const COLUMN_RATE = 'exchange_rate';

    /** Refusal reasons, reported by name so an operator can see WHY a row was left alone. */
    private const REASON_FC_AMOUNT = 'carries original_amount > 0 (a real foreign face value)';

    private const REASON_REAL_RATE = 'carries a real exchange_rate (neither 0 nor 1) — the label is wrong but the right label is not recoverable from the rate';

    private const REASON_CURRENCY_DISAGREE = 'currency and original_currency name different codes';

    public function handle(): int
    {
        $rollbackRunId = $this->option('rollback');

        if ($rollbackRunId !== null && $rollbackRunId !== '') {
            if ($this->option('apply') || $this->option('dry-run')) {
                $this->error('--rollback cannot be combined with --apply or --dry-run.');

                return self::FAILURE;
            }

            return $this->rollback((string) $rollbackRunId);
        }

        $apply = (bool) $this->option('apply');

        if ($apply && $this->option('dry-run')) {
            $this->error('--dry-run and --apply are mutually exclusive. Omit both to dry-run.');

            return self::FAILURE;
        }

        $companyIds = $this->companyIds();

        if ($companyIds === []) {
            $this->warn('No companies with accounts found.');

            return self::SUCCESS;
        }

        $runId = (string) Str::ulid();
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $batchSize = max(1, (int) $this->option('batch-size'));
        $labelOnly = (bool) $this->option('label-only');

        $totals = ['relabelled' => 0, 'rated' => 0, 'refused' => 0];

        foreach ($companyIds as $companyId) {
            $result = $this->processCompany($companyId, $apply, $labelOnly, $runId, $limit, $batchSize);
            $totals['relabelled'] += $result['relabelled'];
            $totals['rated'] += $result['rated'];
            $totals['refused'] += $result['refused'];
        }

        $this->newLine();
        $this->line(sprintf(
            '%s: %d line(s) %s to %s, %d rate(s) %s 0.000000 -> 1.000000, %d refused.',
            $apply ? 'APPLIED' : 'DRY RUN',
            $totals['relabelled'],
            $apply ? 'relabelled' : 'would be relabelled',
            $this->baseCurrency(),
            $totals['rated'],
            $apply ? 'moved' : 'would move',
            $totals['refused']
        ));

        // The verification of the "zero risk" claim, printed every run and computed from THIS run's
        // own numbers rather than quoted from the report.
        if ($totals['rated'] > 0) {
            $this->newLine();
            $this->warn('CONSUMER IMPACT — the rate half of this repair is NOT inert:');
            $this->line(sprintf(
                '  %d line(s) move exchange_rate 0.000000 -> 1.000000. Two services branch on '
                    .'`exchange_rate > 0` and will change behaviour for exactly those lines:',
                $totals['rated']
            ));
            $this->line('    - App\Services\Accounting\RealisedFxService::compute() stops SKIPPING them '
                .'(source_rate_zero / applied_rate_zero) and will compute a realised FX difference.');
            $this->line('    - App\Services\Accounting\CreditApplicationDraftBuilder::resolvePostedInvoiceRate() '
                .'stops returning null, so the credit-apply JV uses the posted rate instead of a live lookup.');
            $this->line('  Re-run with --label-only to take the currency half alone.');
        } elseif (! $labelOnly) {
            $this->line('  CONSUMER IMPACT: none — no line in scope carries a zero rate, so no '
                .'RealisedFxService / credit-apply behaviour changes. The currency half is inert by '
                .'construction (PostingService::reverse() requires original_amount > 0, which this '
                .'predicate excludes).');
        }

        if ($apply && ($totals['relabelled'] > 0 || $totals['rated'] > 0)) {
            $this->line("  run id: {$runId}");
            $this->line("  undo with: php artisan accounting:repair-currency-label --rollback={$runId}");
            $this->line('  NOTE: each --apply invocation gets its OWN run id. A repair run in several '
                .'bounded passes needs one --rollback per id to undo it all.');
        }

        if (! $apply) {
            $this->line('  Nothing was written. Re-run with --apply to write.');
        }

        // A refusal is a REPORT, not a failure — same rule as accounting:backfill-payable-party.
        return self::SUCCESS;
    }

    /**
     * ── THE SELECTION PREDICATE, stated exactly ─────────────────────────────────────────────────
     *
     *   SELECT je.id, je.currency, je.exchange_rate, je.original_amount, je.original_currency
     *     FROM journal_entries je
     *    WHERE je.company_id  = :companyId
     *      AND je.deleted_at IS NULL
     *      AND je.currency   IS NOT NULL
     *      AND TRIM(je.currency) <> ''
     *      AND UPPER(TRIM(je.currency)) <> :baseCurrency        -- 'KWD'
     *      AND je.id > :lastSeenId
     *    ORDER BY je.id
     *    LIMIT :batchSize
     *
     * and then, per row, in PHP, because the three remaining conditions are the REFUSAL reasons and
     * a refusal must be reported rather than filtered away invisibly:
     *
     *      original_amount IS NULL OR original_amount = 0            -- else REASON_FC_AMOUNT
     *      exchange_rate IN (0.000000, 1.000000)                     -- else REASON_REAL_RATE
     *      original_currency IS NULL
     *        OR UPPER(TRIM(original_currency)) = UPPER(TRIM(currency)) -- else REASON_CURRENCY_DISAGREE
     *
     * Paging is by `id > :lastSeenId`, never by re-querying the repair condition: REFUSED rows keep
     * their foreign label forever, so a loop that re-queried "currency <> base" would hand back the
     * same refused batch and spin. (That is the CT-A7 R3 paging defect, inherited deliberately as a
     * fix rather than as a bug.)
     *
     * @return array{relabelled: int, rated: int, refused: int}
     */
    private function processCompany(int $companyId, bool $apply, bool $labelOnly, string $runId, ?int $limit, int $batchSize): array
    {
        $base = $this->baseCurrency();
        $relabelled = 0;
        $rated = 0;
        $refused = 0;
        $refusedByReason = [];
        $relabelledByCode = [];
        $lastId = 0;
        $considered = 0;

        while (true) {
            $take = $batchSize;

            if ($limit !== null) {
                $take = min($take, $limit - $considered);

                if ($take <= 0) {
                    break;
                }
            }

            $rows = DB::table('journal_entries')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNotNull('currency')
                ->where('currency', '<>', '')
                ->whereRaw('UPPER(TRIM(currency)) <> ?', [$base])
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($take)
                ->get(['id', 'currency', 'exchange_rate', 'original_amount', 'original_currency']);

            if ($rows->isEmpty()) {
                break;
            }

            $considered += $rows->count();
            $lastId = (int) $rows->last()->id;

            $writes = [];

            foreach ($rows as $row) {
                $reason = $this->refusalReason($row);

                if ($reason !== null) {
                    $refused++;
                    $refusedByReason[$reason] = ($refusedByReason[$reason] ?? 0) + 1;

                    continue;
                }

                $code = strtoupper(trim((string) $row->currency));
                $relabelledByCode[$code] = ($relabelledByCode[$code] ?? 0) + 1;
                $relabelled++;

                // Only a rate that is actually 0 moves. A row already at 1.000000 needs no rate
                // write, and writing one would record a before-image for a change that never
                // happened — a rollback list full of no-ops is a rollback list nobody trusts.
                $movesRate = ! $labelOnly && abs((float) $row->exchange_rate) < 0.0000005;

                if ($movesRate) {
                    $rated++;
                }

                $writes[] = [
                    'id' => (int) $row->id,
                    'before_currency' => (string) $row->currency,
                    'before_rate' => (string) $row->exchange_rate,
                    'moves_rate' => $movesRate,
                ];
            }

            if (! $apply || $writes === []) {
                continue;
            }

            // ONE BATCH = ONE TRANSACTION. Before-images first, in the same transaction as the
            // writes they describe, so a crash can never leave a relabelled row with no way back.
            DB::transaction(function () use ($writes, $companyId, $runId, $base) {
                $beforeImages = [];

                foreach ($writes as $write) {
                    $beforeImages[] = [
                        'run_id' => $runId,
                        'company_id' => $companyId,
                        'subject_table' => self::SUBJECT_TABLE,
                        'subject_id' => $write['id'],
                        'column_name' => self::COLUMN_CURRENCY,
                        // Recorded, not assumed: the rollback restores what was there, not what
                        // this command believed was there.
                        'before_value' => $write['before_currency'],
                        'after_value' => $base,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($write['moves_rate']) {
                        $beforeImages[] = [
                            'run_id' => $runId,
                            'company_id' => $companyId,
                            'subject_table' => self::SUBJECT_TABLE,
                            'subject_id' => $write['id'],
                            'column_name' => self::COLUMN_RATE,
                            'before_value' => $write['before_rate'],
                            'after_value' => '1.000000',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                DB::table('coa_linkage_changes')->insert($beforeImages);

                foreach ($writes as $write) {
                    $update = ['currency' => $base, 'updated_at' => now()];

                    if ($write['moves_rate']) {
                        $update['exchange_rate'] = 1.0;
                    }

                    DB::table('journal_entries')
                        ->where('id', $write['id'])
                        // Re-asserted at write time: if anything relabelled this row between the
                        // read and the write, this update matches nothing rather than overwriting
                        // someone else's later, deliberate value.
                        ->where('currency', $write['before_currency'])
                        ->update($update);
                }
            });
        }

        if ($considered === 0) {
            $this->line("company {$companyId}: nothing to do.");

            return ['relabelled' => 0, 'rated' => 0, 'refused' => 0];
        }

        $this->line(sprintf(
            'company %d: %d foreign-labelled line(s) considered, %d demonstrably %s, %d refused.',
            $companyId,
            $considered,
            $relabelled,
            $base,
            $refused
        ));

        foreach ($relabelledByCode as $code => $count) {
            $this->line(sprintf('  %s -> %s: %d line(s)', $code, $base, $count));
        }

        foreach ($refusedByReason as $reason => $count) {
            // Two lines, not one. The reasons are long enough that a single sprintf() runs past the
            // console width and Symfony wraps it mid-sentence — which makes the closing phrase
            // unassertable and, more to the point, makes it unreadable to the operator it is for.
            $this->line(sprintf('  REFUSED %d line(s): %s.', $count, $reason));
            $this->line('    Nothing written; nothing guessed.');
        }

        return ['relabelled' => $relabelled, 'rated' => $rated, 'refused' => $refused];
    }

    /** The reason this row must be left alone, or null when it is demonstrably a base-currency row. */
    private function refusalReason(object $row): ?string
    {
        if ($row->original_amount !== null && abs((float) $row->original_amount) > 0.0005) {
            return self::REASON_FC_AMOUNT;
        }

        $rate = (float) $row->exchange_rate;

        if (abs($rate) > 0.0000005 && abs($rate - 1.0) > 0.0000005) {
            return self::REASON_REAL_RATE;
        }

        $originalCurrency = $row->original_currency === null ? null : strtoupper(trim((string) $row->original_currency));

        if ($originalCurrency !== null && $originalCurrency !== '' && $originalCurrency !== strtoupper(trim((string) $row->currency))) {
            return self::REASON_CURRENCY_DISAGREE;
        }

        return null;
    }

    private function rollback(string $runId): int
    {
        $ownedColumns = [self::COLUMN_CURRENCY, self::COLUMN_RATE];

        $rows = DB::table('coa_linkage_changes')
            ->where('run_id', $runId)
            ->where('subject_table', self::SUBJECT_TABLE)
            ->whereIn('column_name', $ownedColumns)
            ->whereNull('rolled_back_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            // The SYMMETRIC guard, in the shape CT-A7 R3-3 established and CT-A8 keyed by the pair:
            // three distinct outcomes, only one of them success.
            $anyForRun = DB::table('coa_linkage_changes')->where('run_id', $runId);

            if ((clone $anyForRun)->where('subject_table', self::SUBJECT_TABLE)->whereIn('column_name', $ownedColumns)->exists()) {
                $this->line("Run {$runId}: every recorded line was already rolled back. Nothing to do.");

                return self::SUCCESS;
            }

            $foreignPairs = (clone $anyForRun)
                ->distinct()
                ->get(['subject_table', 'column_name'])
                ->map(fn ($r) => $r->subject_table.'.'.$r->column_name)
                ->unique()
                ->values();

            if ($foreignPairs->isNotEmpty()) {
                $this->error("Run '{$runId}' contains before-images this command does not own and must not restore.");
                $this->line('  Run contains before-images for: '.$foreignPairs->implode(', '));
                $this->line('  Undo it with: '.BeforeImageOwnership::rollbackHint($foreignPairs->all(), $runId));
                $this->line('  Nothing was restored.');

                return self::FAILURE;
            }

            $this->error("Run '{$runId}' is not a run id this command recorded.");
            $this->line('  Run ids are echoed by every --apply run and stored in coa_linkage_changes.run_id.');
            $this->line('  Nothing was restored.');

            return self::FAILURE;
        }

        $restored = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, &$restored, &$skipped) {
            foreach ($rows as $row) {
                $column = (string) $row->column_name;
                $current = DB::table('journal_entries')->where('id', $row->subject_id)->value($column);

                // Refuse a row that has MOVED since the run. Putting a before-image back over
                // someone else's later, deliberate value is not an undo. Compared numerically for
                // the rate (0.000000 and '0' are the same rate) and textually for the label.
                $matches = $column === self::COLUMN_RATE
                    ? abs((float) $current - (float) $row->after_value) < 0.0000005
                    : (string) $current === (string) $row->after_value;

                if (! $matches) {
                    $skipped[] = sprintf(
                        'journal_entries #%d.%s: now %s, this run wrote %s — left alone',
                        (int) $row->subject_id,
                        $column,
                        $current === null ? 'NULL' : (string) $current,
                        (string) $row->after_value
                    );

                    continue;
                }

                DB::table('journal_entries')
                    ->where('id', $row->subject_id)
                    ->update([
                        $column => $row->before_value,
                        'updated_at' => now(),
                    ]);

                DB::table('coa_linkage_changes')->where('id', $row->id)->update([
                    'rolled_back_at' => now(),
                    'updated_at' => now(),
                ]);

                $restored++;
            }
        });

        $this->line("Rolled back run {$runId}: {$restored} value(s) restored.");

        foreach ($skipped as $note) {
            $this->warn('  '.$note);
        }

        return $skipped === [] ? self::SUCCESS : self::FAILURE;
    }

    private function baseCurrency(): string
    {
        return strtoupper(trim((string) config('accounting.engine.base_currency', 'KWD')));
    }

    /** @return int[] */
    private function companyIds(): array
    {
        $option = $this->option('company');

        if ($option !== null && $option !== '') {
            return [(int) $option];
        }

        return DB::table('accounts')
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** The subject table this command owns, for the shared ownership check. */
    public static function subjectTable(): string
    {
        return self::SUBJECT_TABLE;
    }

    /**
     * The column half of the ownership key. This command owns TWO columns of `journal_entries`, so
     * "the table" was never a sufficient discriminator here.
     *
     * @return string[]
     */
    public static function beforeImageColumns(): array
    {
        return [self::COLUMN_CURRENCY, self::COLUMN_RATE];
    }
}
