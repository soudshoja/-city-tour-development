<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\BeforeImageOwnership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CT-A9 T3 — repair the tasks whose foreign price was recorded as if it were already Kuwaiti dinars.
 *
 * ── The defect, and how small it really is ─────────────────────────────────────────────────────
 * `CT-FX-EXPOSURE-2026-09-16.md` §3.2 sized the genuine arithmetic error in the City Travelers
 * ledger at **7 tasks and KWD 1,628.919 of OVERSTATEMENT** — 0.026 % of a KWD 6,154,780.031 gross
 * ledger. Not the ~KWD 390k a previous assessment reported: those 4,553 'USD'-labelled lines are
 * already KWD and are a LABEL defect, repaired by `accounting:repair-currency-label`, not this
 * command. The two are unrelated and must not be conflated again.
 *
 * The shape is always the same. A task is sourced in a foreign currency
 * (`tasks.original_currency`), converted INTO Kuwaiti dinars (`tasks.exchange_currency = 'KWD'`),
 * and the foreign figure was typed into the local field: `tasks.total` carries (near enough) the
 * foreign number instead of `original_total × exchange_rate`. Because a dinar buys about 3.23
 * dollars, this OVERSTATES — the ledger carries 2.92× too much on those documents.
 *
 * `TaskIssuancePayableService.php:197-202` already refuses this exact shape at posting time and
 * names one of these seven by its numbers: *"amount 368.000, originalAmount 368.000, rate 0.340000,
 * which implies 125.120"* — task 15993. This command is the historical half of the same finding.
 *
 * ── Four are repairable exactly. Three are NOT, and never will be. ─────────────────────────────
 * Four of the seven carry their own `tasks.exchange_rate`, captured at task creation — 0.305220 and
 * 0.340000 ×3. That is CONTEMPORANEOUS evidence, and it is the only rate this command will ever
 * convert on.
 *
 * Three (all EUR) carry no rate at all, and the historical rate is **genuinely unrecoverable from
 * this system**, which was checked rather than assumed:
 *   - `currency_exchanges` holds ONE MUTABLE ROW per (company, pair) with **no effective date** —
 *     today's number, not the number on the day;
 *   - `exchange_rate_histories` holds **3 rows in fourteen months**;
 *   - `system_exchange_rates` is a USD-base API feed with 31 timestamps and no KWD pair;
 *   - no supplier invoice document is stored anywhere.
 * So the command REPAIRS THE FOUR AND REFUSES THE THREE. It reports them by id for owner
 * disclosure, at KWD 1,133.79 across three documents.
 *
 * ── What this command is NOT (CT-A9 verify — the narrowing) ───────────────────────────────────
 * It is NOT "make `tasks.total` equal `original_total × exchange_rate`". That was the first cut's
 * predicate and it was wrong: measured against the development database it flagged **130** tasks
 * rather than 7, and **109 of the 130 would have RAISED** the booked total — a net **+KWD
 * 6,148.524**, the opposite direction to the KWD 1,628.919 OVERstatement the lane exists to correct.
 * Tasks fail that reconciliation for ordinary reasons (markup, tax, part amounts, later credits),
 * and a repair tool that treats every one of them as a defect is not a repair tool.
 *
 * The signature it looks for is CT-FX's own: **the local figure IS the foreign figure** while the
 * currency trades nowhere near parity. On the same population that returns **exactly 7**.
 *
 * ── Contemporaneity is TESTED, not assumed (CT-A9 verify) ─────────────────────────────────────
 * The same dry run appeared to destroy the premise that `tasks.exchange_rate` is captured at task
 * creation: 354 of 490 in-scope tasks carry a rate equal to TODAY's table rate to six decimals.
 * {@see self::rateInForceAt()} has the full working, but the short version is that the appearance is
 * an artefact of a static rate table — for USD, the one pair whose rate moved twice, **211 of 211
 * tasks carry exactly the rate in force on their own creation date**, with the date ranges disjoint
 * at exactly the two recorded changes. The premise holds, and it is now CHECKED per task rather than
 * asserted: a task whose stored rate is not the rate in force the day it was created is REFUSED.
 *
 * ── Detection is allowed to use today's rate. CORRECTION is not. ───────────────────────────────
 * This is the one distinction the whole command turns on, and it is why the three are refused
 * rather than silently "corrected":
 *
 *   - To DETECT that a figure was never converted, it is enough to know the rate is nowhere near 1
 *     while `total / original_total` IS near 1. Today's table rate is fine for that: EUR at 0.353
 *     versus a booked ratio of 1.00-1.06 is not a rounding question, and no plausible 2025 EUR rate
 *     turns 1,125 into 1,150. Detection is robust to being wrong about the rate by tens of percent.
 *   - To CORRECT the figure you need THE rate, on THE day, and being wrong by tens of percent puts
 *     a wrong number in the books wearing the authority of a correction. Today's rate cannot do
 *     that job and is never used for it.
 *
 * There is therefore no flag, no default and no fallback anywhere in this command that converts a
 * task on a `currency_exchanges` rate. `--historical-rate=<taskId>:<rate>` exists for the one case
 * `CT-FX-EXPOSURE` §8.2 left open — *"an external historical source the owner supplies"* — it is
 * per task, it requires `--apply`, it is refused for any task that already has its own rate, and
 * every task repaired through it gets an extra `__operator_supplied_rate__` before-image row
 * recording the rate and that a human supplied it. An operator-asserted external rate is evidence;
 * a table lookup is a guess wearing evidence's clothes.
 *
 * ── The LEDGER is reported, never rewritten ───────────────────────────────────────────────────
 * This command writes `tasks.total` and `tasks.price`. It does **not** touch `journal_entries`.
 * A posted ledger is corrected by a correcting DOCUMENT — that is the engine's keystone rule
 * ("exactly one code path may write journal_entries", {@see \App\Services\Accounting\PostingService})
 * — and an UPDATE against a posted line would move money with no document, no date, no period and
 * no audit beyond this table. Every run therefore prints the posted journal lines that carry each
 * affected `task_id`, with their ids and amounts, so the owner can raise the correcting documents
 * through the engine and see exactly what they are correcting. The trial balance is byte-identical
 * before and after this command runs, by construction.
 *
 * ── Ownership ─────────────────────────────────────────────────────────────────────────────────
 * Before-images go to `coa_linkage_changes` under `tasks.total` / `tasks.price` — a subject table
 * no other command owns. The (subject_table, column_name) key CT-A8 introduced is what makes that
 * safe alongside `journal_entries.currency` (`accounting:repair-currency-label`),
 * `journal_entries.type_reference_id` (`accounting:backfill-payable-party`) and
 * `accounts.supplier_id` (`accounting:backfill-supplier-leaf`).
 */
class RepairTaskFxConversion extends Command
{
    protected $signature = 'accounting:repair-task-fx-conversion
                            {--company= : Company id to process (default: every company with accounts)}
                            {--dry-run : Report the full change list without writing anything (the default whenever --apply is absent)}
                            {--apply : Actually write the converted figures}
                            {--historical-rate=* : taskId:rate — an EXTERNAL historical rate the OWNER supplies for a task that carries none, e.g. --historical-rate=9916:0.312500. Requires --apply. Refused for any task that has its own recorded rate. Recorded in the before-image as operator-supplied, and reported as an ESTIMATE.}
                            {--rollback= : Undo a previous --apply run by its run id. One run id PER --apply invocation.}
                            {--limit= : Cap the number of tasks considered per company, for a staged rollout}
                            {--batch-size=500 : Tasks per transaction. One batch = one transaction = one commit.}';

    protected $description = 'CT-A9 T3 — convert tasks whose foreign price was recorded as if it were already base currency (CT-FX §3.2: 7 tasks, KWD 1,628.919 overstated). Repairs only on the task\'s OWN contemporaneous rate; refuses every task whose historical rate is unrecoverable. Dry-run by default; never touches journal_entries.';

    public const SUBJECT_TABLE = 'tasks';

    public const COLUMN_TOTAL = 'total';

    public const COLUMN_PRICE = 'price';

    /**
     * `column_name` sentinel: "this task was converted on a rate a HUMAN supplied from an external
     * source, not on evidence this system holds". Same shape as CoaLinkageChange's own
     * ROW_CREATED/ROW_DELETED/ROW_SWEPT sentinels. Never restored by --rollback (there is nothing
     * to put back — it is a label, not a value); reported by it.
     */
    public const OPERATOR_SUPPLIED_RATE = '__operator_supplied_rate__';

    /**
     * How close `total / original_total` has to be to 1 before a task is called "the foreign figure,
     * unconverted".
     *
     * ── This is now the FIRST GATE, not a late one (CT-A9 verify) ──────────────────────────────
     * The first cut of this command did not gate on the signature at all: it flagged any task whose
     * `total` failed to reconcile with `original_total × rate`. That is a different and much wider
     * question, and the dry run showed how much wider — **130 tasks instead of 7, of which 109 would
     * have moved the total UPWARD, a net +KWD 6,148.524, against a finding of KWD 1,628.919
     * OVERstated.** Real tasks fail reconciliation for entirely ordinary reasons: markup, tax, a
     * part amount, a later credit. "Does not reconcile" is not "was never converted".
     *
     * CT-FX's actual test is this one — the local figure IS (near enough) the foreign figure, while
     * the currency trades nowhere near parity. Applied to the same 492-task population it returns
     * **exactly 7**: CT-FX's seven, no more and no fewer.
     *
     * The band is not zero because markup and tax move the ratio a little: the seven sit at 1.000
     * ×5, 1.022 and 1.060.
     */
    private const UNCONVERTED_RATIO_BAND = 0.10;

    /**
     * How far today's table rate must be from 1.0 before it is allowed to say anything at all.
     *
     * Guards the detection against a near-parity currency, where "the figure looks unconverted" and
     * "the figure IS converted" are the same number and no evidence can separate them.
     */
    private const MIN_RATE_DISTANCE_FROM_PARITY = 0.10;

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

        $supplied = $this->parseHistoricalRates();

        if ($supplied === null) {
            return self::FAILURE;
        }

        if ($supplied !== [] && ! $apply) {
            $this->error('--historical-rate requires --apply. An externally-supplied rate is an owner decision, '
                .'not something to leave sitting in a dry run where it might be mistaken for evidence this system holds.');

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

        $totals = ['repaired' => 0, 'estimated' => 0, 'refused' => 0, 'untestable' => 0, 'out_of_signature' => 0];
        $usedSupplied = [];

        foreach ($companyIds as $companyId) {
            $result = $this->processCompany($companyId, $apply, $runId, $limit, $batchSize, $supplied, $usedSupplied);

            foreach ($totals as $key => $_) {
                $totals[$key] += $result[$key];
            }
        }

        $unusedSupplied = array_diff(array_keys($supplied), $usedSupplied);

        if ($unusedSupplied !== []) {
            $this->newLine();
            $this->error('These --historical-rate task ids were NOT used: '.implode(', ', $unusedSupplied).'.');
            $this->line('  A task is only convertible on a supplied rate when it is in the REFUSED-UNRECOVERABLE '
                .'bucket: in scope, demonstrably unconverted, and carrying no rate of its own. A task that '
                .'already has a rate is repaired on ITS OWN rate and a supplied one is refused; a task that is '
                .'not demonstrably defective is not repaired at all.');
        }

        $this->newLine();
        $this->line(sprintf(
            '%s: %d task(s) %s on their own recorded rate, %d %s on an OPERATOR-SUPPLIED rate (ESTIMATE), '
                .'%d refused, %d untestable, %d outside the unconverted signature.',
            $apply ? 'APPLIED' : 'DRY RUN',
            $totals['repaired'],
            $apply ? 'converted' : 'would be converted',
            $totals['estimated'],
            $apply ? 'converted' : 'would be converted',
            $totals['refused'],
            $totals['untestable'],
            $totals['out_of_signature']
        ));

        if ($totals['estimated'] > 0) {
            $this->warn(sprintf(
                'DISCLOSURE: %d task(s) were converted on a rate this system does not hold. Those figures are '
                    .'ESTIMATES, not corrections to a known figure, and are recorded as such in '
                    .'coa_linkage_changes under column_name = %s.',
                $totals['estimated'],
                self::OPERATOR_SUPPLIED_RATE
            ));
        }

        if ($apply && ($totals['repaired'] > 0 || $totals['estimated'] > 0)) {
            $this->line("  run id: {$runId}");
            $this->line("  undo with: php artisan accounting:repair-task-fx-conversion --rollback={$runId}");
            $this->line('  NOTE: each --apply invocation gets its OWN run id.');
        }

        if (! $apply) {
            $this->line('  Nothing was written. Re-run with --apply to write.');
        }

        $this->line('  journal_entries was NOT touched by this command, in any mode. The ledger lines listed '
            .'above are corrected by raising a correcting DOCUMENT through the posting engine.');

        return self::SUCCESS;
    }

    /**
     * ── THE SELECTION PREDICATE, stated exactly ─────────────────────────────────────────────────
     *
     *   SELECT t.id, t.price, t.total, t.original_price, t.original_total,
     *          t.original_currency, t.exchange_currency, t.exchange_rate
     *     FROM tasks t
     *    WHERE t.company_id       = :companyId
     *      AND t.deleted_at      IS NULL
     *      AND t.original_currency IS NOT NULL
     *      AND UPPER(TRIM(t.original_currency)) <> :baseCurrency          -- 'KWD'
     *      AND UPPER(TRIM(COALESCE(t.exchange_currency, :baseCurrency))) = :baseCurrency
     *      AND t.original_total   > 0                                     -- a TRUE foreign anchor
     *      AND t.total            > 0
     *      AND t.id > :lastSeenId
     *    ORDER BY t.id
     *    LIMIT :batchSize
     *
     * `original_total > 0` is what makes a task testable at all: it is the only column that
     * records what the supplier actually billed. CT-FX measured 499 such tasks live against 1,829
     * that carry only an `original_price` (weakly testable — `total` legitimately exceeds `price`
     * by markup and tax, so a ratio near 1 is not proof there) and 22 with no anchor at all.
     * Neither of the latter two groups is in scope, in any mode.
     *
     * Classification then happens per row, in PHP, because every branch of it is a REPORT:
     *
     *   rate > 0 and |total − original_total × rate| <= max(0.01, 1 % × total)  -> OK, untouched
     *   rate > 0 and it does not reconcile                                       -> REPAIRABLE
     *   rate <= 0, table rate missing                                            -> UNTESTABLE
     *   rate <= 0, |tableRate − 1| <= 0.10                                       -> UNTESTABLE
     *   rate <= 0, |total/original_total − 1| <= 0.10                            -> REFUSED (defective, unrecoverable)
     *   rate <= 0, otherwise                                                     -> UNTESTABLE
     *
     * The tolerance is `PostingService` step 3f's own `max(0.01, 1 % × amount)`, not a new number
     * invented here — a task this command calls consistent is exactly a task the engine would
     * accept, and a task it calls defective is exactly one the engine would refuse.
     *
     * Paging is by `id > :lastSeenId`, never by re-querying the defect condition: REFUSED tasks
     * stay defective forever, so a loop that re-queried it would hand back the same batch and spin.
     *
     * @param  array<int, float>  $supplied
     * @param  int[]  $usedSupplied
     * @return array{repaired: int, estimated: int, refused: int, untestable: int}
     */
    private function processCompany(int $companyId, bool $apply, string $runId, ?int $limit, int $batchSize, array $supplied, array &$usedSupplied): array
    {
        $base = $this->baseCurrency();
        $tableRates = $this->tableRates($companyId, $base);

        $counts = ['repaired' => 0, 'estimated' => 0, 'refused' => 0, 'untestable' => 0, 'out_of_signature' => 0];
        $lastId = 0;
        $considered = 0;
        $reports = [];

        while (true) {
            $take = $batchSize;

            if ($limit !== null) {
                $take = min($take, $limit - $considered);

                if ($take <= 0) {
                    break;
                }
            }

            $rows = DB::table('tasks')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNotNull('original_currency')
                ->whereRaw('UPPER(TRIM(original_currency)) <> ?', [$base])
                ->whereRaw('UPPER(TRIM(COALESCE(exchange_currency, ?))) = ?', [$base, $base])
                ->where('original_total', '>', 0)
                ->where('total', '>', 0)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($take)
                ->get(['id', 'price', 'total', 'original_price', 'original_total', 'original_currency', 'exchange_currency', 'exchange_rate', 'created_at']);

            if ($rows->isEmpty()) {
                break;
            }

            $considered += $rows->count();
            $lastId = (int) $rows->last()->id;

            $writes = [];

            foreach ($rows as $row) {
                $taskId = (int) $row->id;
                $code = strtoupper(trim((string) $row->original_currency));
                $total = round((float) $row->total, 3);
                $originalTotal = round((float) $row->original_total, 3);
                $ownRate = (float) ($row->exchange_rate ?? 0);
                $tolerance = max(0.01, abs($total) * 0.01);
                $tableRate = $tableRates[$code] ?? null;

                // ── GATE 1: THE UNCONVERTED SIGNATURE (CT-A9 verify — the narrowing) ───────────
                // See the class docblock's "what this command is NOT" note. Everything below only
                // runs for a task that LOOKS LIKE THE DEFECT, not for one that merely fails to
                // reconcile.
                $ratio = $originalTotal > 0 ? $total / $originalTotal : 0.0;

                if (abs($ratio - 1.0) > self::UNCONVERTED_RATIO_BAND) {
                    $counts['out_of_signature']++;

                    continue;
                }

                // ── GATE 2: the reference rate has to be able to say anything at all ──────────
                if ($tableRate === null || $tableRate <= 0) {
                    $counts['untestable']++;
                    $reports[] = sprintf('  UNTESTABLE task #%d (%s):', $taskId, $code);
                    $reports[] = sprintf('    total %.3f is %.3fx original_total %.3f, but there is no %s row in',
                        $total, $ratio, $originalTotal, $code);
                    $reports[] = '    currency_exchanges, so "looks unconverted" has nothing to be measured against.';

                    continue;
                }

                if (abs($tableRate - 1.0) <= self::MIN_RATE_DISTANCE_FROM_PARITY) {
                    $counts['untestable']++;
                    $reports[] = sprintf('  UNTESTABLE task #%d (%s):', $taskId, $code);
                    $reports[] = sprintf('    the %s rate is %.6f, too near parity for "looks unconverted" to mean anything.',
                        $code, $tableRate);

                    continue;
                }

                // From here the task IS demonstrably unconverted. The only question left is
                // whether a rate exists that may be used to convert it.

                if ($ownRate > 0) {
                    // Supplying a rate for a task that HAS one is refused outright: the point of
                    // --historical-rate is the absence of evidence, and overriding evidence that
                    // exists is exactly the coaxing this command must not permit.
                    if (array_key_exists($taskId, $supplied)) {
                        $reports[] = sprintf('  REFUSED --historical-rate for task #%d:', $taskId);
                        $reports[] = sprintf('    it already carries its own recorded rate %.6f.', $ownRate);
                        $reports[] = '    A supplied rate may only stand in for a rate that does not exist.';

                        continue;
                    }

                    // ── GATE 3: CONTEMPORANEITY, TESTED RATHER THAN ASSUMED (CT-A9 verify) ────
                    // The stored rate is only evidence if it is the rate that was IN FORCE on the
                    // day the task was created. That is reconstructible — see rateInForceAt() —
                    // and a task carrying a rate the table never held on its own creation date is
                    // carrying a number from somewhere this command cannot see. Refuse it.
                    $inForce = $this->rateInForceAt($companyId, $code, (string) ($row->created_at ?? ''), $tableRate);

                    if ($inForce !== null && abs($ownRate - $inForce) > 0.0000005) {
                        $counts['refused']++;
                        $reports[] = sprintf('  REFUSED task #%d (%s): its stored rate is NOT contemporaneous.', $taskId, $code);
                        $reports[] = sprintf('    task created %s, stored rate %.6f, but the rate in force that day was %.6f',
                            substr((string) ($row->created_at ?? '?'), 0, 10), $ownRate, $inForce);
                        $reports[] = '    (reconstructed from currency_exchanges + exchange_rate_histories).';
                        $reports[] = '    The rate came from somewhere this command cannot see. Nothing written; nothing guessed.';

                        continue;
                    }

                    $expected = round($originalTotal * $ownRate, 3);

                    if (abs($total - $expected) <= $tolerance) {
                        continue; // Consistent after all. Not a defect. Untouched and unreported.
                    }

                    $writes[] = $this->plan($row, $ownRate, $expected, false);
                    $counts['repaired']++;
                    $reports[] = sprintf(
                        '  REPAIRABLE task #%d (%s): total %.3f -> %.3f', $taskId, $code, $total, $expected
                    );
                    $reports[] = sprintf(
                        '    original_total %.3f x its own rate %.6f (in force on %s); overstated by %.3f.',
                        $originalTotal, $ownRate, substr((string) ($row->created_at ?? '?'), 0, 10), $total - $expected
                    );

                    continue;
                }

                // ── No recorded rate at all, and the figure is demonstrably unconverted. ──────
                $suppliedRate = $supplied[$taskId] ?? null;

                if ($suppliedRate === null) {
                    $counts['refused']++;
                    $reports[] = sprintf(
                        '  REFUSED task #%d (%s): total %.3f is %.3fx original_total %.3f,',
                        $taskId, $code, $total, $ratio, $originalTotal
                    );
                    $reports[] = sprintf(
                        '    while %s trades near %.6f — the figure was never converted.', $code, $tableRate
                    );
                    $reports[] = '    The task carries NO rate, and this pair has no recorded rate change to';
                    $reports[] = '    reconstruct one from, so the rate on the day is UNRECOVERABLE.';
                    $reports[] = '    Nothing written; nothing guessed. Owner disclosure required.';

                    continue;
                }

                $expected = round($originalTotal * $suppliedRate, 3);
                $writes[] = $this->plan($row, $suppliedRate, $expected, true);
                $counts['estimated']++;
                $usedSupplied[] = $taskId;
                $reports[] = sprintf(
                    '  ESTIMATE task #%d (%s): total %.3f -> %.3f', $taskId, $code, $total, $expected
                );
                $reports[] = sprintf(
                    '    on an OPERATOR-SUPPLIED rate %.6f. This system holds no evidence for it.', $suppliedRate
                );
                $reports[] = '    Recorded as an ESTIMATE, not as a correction to a known figure.';
            }

            if (! $apply || $writes === []) {
                continue;
            }

            DB::transaction(function () use ($writes, $companyId, $runId) {
                $beforeImages = [];

                foreach ($writes as $write) {
                    $beforeImages[] = $this->beforeImage($runId, $companyId, $write['id'], self::COLUMN_TOTAL, $write['before_total'], $write['after_total']);

                    if ($write['writes_price']) {
                        $beforeImages[] = $this->beforeImage($runId, $companyId, $write['id'], self::COLUMN_PRICE, $write['before_price'], $write['after_price']);
                    }

                    if ($write['estimated']) {
                        // The label the brief requires: an estimate says so IN the before-image, so
                        // a reader of coa_linkage_changes six months from now can tell a correction
                        // from a guess without reading this class.
                        $beforeImages[] = $this->beforeImage(
                            $runId,
                            $companyId,
                            $write['id'],
                            self::OPERATOR_SUPPLIED_RATE,
                            null,
                            sprintf('%.6f', $write['rate'])
                        );
                    }
                }

                DB::table('coa_linkage_changes')->insert($beforeImages);

                foreach ($writes as $write) {
                    $update = [self::COLUMN_TOTAL => $write['after_total'], 'updated_at' => now()];

                    if ($write['writes_price']) {
                        $update[self::COLUMN_PRICE] = $write['after_price'];
                    }

                    DB::table('tasks')
                        ->where('id', $write['id'])
                        // Re-asserted at write time: if anything changed this task between the read
                        // and the write, this update matches nothing rather than overwriting it.
                        ->where(self::COLUMN_TOTAL, $write['before_total'])
                        ->update($update);
                }
            });
        }

        if ($considered === 0) {
            $this->line("company {$companyId}: no task with a foreign anchor — nothing to do.");

            return $counts;
        }

        $this->line(sprintf(
            'company %d: %d foreign-sourced task(s) with a true anchor considered; %d outside the unconverted '
                .'signature (not this defect); %d repairable, %d estimated, %d refused, %d untestable.',
            $companyId,
            $considered,
            $counts['out_of_signature'],
            $counts['repaired'],
            $counts['estimated'],
            $counts['refused'],
            $counts['untestable']
        ));

        foreach ($reports as $report) {
            $this->line($report);
        }

        $this->reportLedgerFootprint($companyId, $reports === [] ? [] : $this->affectedTaskIds($reports));

        return $counts;
    }

    /**
     * The per-task write plan. `price` is only rewritten when it has its own foreign anchor
     * (`original_price > 0`) AND is itself inconsistent — a task whose cost was converted correctly
     * while its sell was not is a real shape, and this must not "fix" the half that was right.
     *
     * @return array{id: int, rate: float, estimated: bool, before_total: string, after_total: string, writes_price: bool, before_price: string|null, after_price: string|null}
     */
    private function plan(object $row, float $rate, float $expectedTotal, bool $estimated): array
    {
        $price = round((float) ($row->price ?? 0), 3);
        $originalPrice = round((float) ($row->original_price ?? 0), 3);
        $expectedPrice = round($originalPrice * $rate, 3);
        $priceTolerance = max(0.01, abs($price) * 0.01);

        $writesPrice = $originalPrice > 0 && $price > 0 && abs($price - $expectedPrice) > $priceTolerance;

        return [
            'id' => (int) $row->id,
            'rate' => $rate,
            'estimated' => $estimated,
            'before_total' => (string) $row->total,
            'after_total' => number_format($expectedTotal, 3, '.', ''),
            'writes_price' => $writesPrice,
            'before_price' => $writesPrice ? (string) $row->price : null,
            'after_price' => $writesPrice ? number_format($expectedPrice, 3, '.', '') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function beforeImage(string $runId, int $companyId, int $subjectId, string $column, ?string $before, ?string $after): array
    {
        return [
            'run_id' => $runId,
            'company_id' => $companyId,
            'subject_table' => self::SUBJECT_TABLE,
            'subject_id' => $subjectId,
            'column_name' => $column,
            'before_value' => $before,
            'after_value' => $after,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * The ledger lines this command is NOT touching, named so the owner can raise the correcting
     * documents. Read-only; nothing here writes.
     *
     * @param  int[]  $taskIds
     */
    private function reportLedgerFootprint(int $companyId, array $taskIds): void
    {
        if ($taskIds === []) {
            return;
        }

        $lines = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('task_id', $taskIds)
            ->orderBy('task_id')
            ->orderBy('id')
            ->get(['id', 'task_id', 'account_id', 'debit', 'credit']);

        if ($lines->isEmpty()) {
            $this->line('  LEDGER: no posted journal line carries any of these task ids. Nothing to correct on the ledger.');

            return;
        }

        $this->newLine();
        $this->line(sprintf(
            '  LEDGER FOOTPRINT — %d posted line(s) carry these task ids. NOT touched by this command:',
            $lines->count()
        ));

        foreach ($lines as $line) {
            $this->line(sprintf(
                '    journal_entries #%d  task #%d  account #%d  dr %.3f  cr %.3f',
                (int) $line->id,
                (int) $line->task_id,
                (int) $line->account_id,
                (float) $line->debit,
                (float) $line->credit
            ));
        }

        $this->line('    Correct these by raising a correcting document through the posting engine. An UPDATE '
            .'against a posted line moves money with no document, no date and no period.');
    }

    /** @return int[] */
    private function affectedTaskIds(array $reports): array
    {
        $ids = [];

        foreach ($reports as $report) {
            if (preg_match('/task #(\d+)/', $report, $m) === 1) {
                $ids[] = (int) $m[1];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * `--historical-rate=taskId:rate`, parsed strictly. Returns null (and reports) on anything
     * malformed rather than skipping it quietly — a mistyped rate that is silently ignored is how
     * an operator comes to believe a task was repaired when it was not.
     *
     * @return array<int, float>|null
     */
    private function parseHistoricalRates(): ?array
    {
        $out = [];

        foreach ((array) $this->option('historical-rate') as $raw) {
            $raw = trim((string) $raw);

            if ($raw === '') {
                continue;
            }

            if (preg_match('/^(\d+):([0-9]*\.?[0-9]+)$/', $raw, $m) !== 1) {
                $this->error("--historical-rate '{$raw}' is malformed. Expected taskId:rate, e.g. 9916:0.312500.");

                return null;
            }

            $taskId = (int) $m[1];
            $rate = (float) $m[2];

            if ($rate <= 0) {
                $this->error("--historical-rate '{$raw}': a rate must be > 0.");

                return null;
            }

            if (array_key_exists($taskId, $out)) {
                $this->error("--historical-rate: task {$taskId} was given more than once.");

                return null;
            }

            $out[$taskId] = $rate;
        }

        return $out;
    }

    /**
     * The rate that was IN FORCE for one pair on one date, reconstructed from today's
     * `currency_exchanges` row walked backwards through `exchange_rate_histories`.
     *
     * ── Why this exists, and what it settled (CT-A9 verify) ─────────────────────────────────────
     * The first cut of this command asserted that `tasks.exchange_rate` is "contemporaneous
     * evidence, captured at task creation". A dry run against the development database appeared to
     * demolish that: **354 of 490** in-scope tasks carry a rate equal to TODAY's table rate to six
     * decimals, which looks exactly like a rate copied on later.
     *
     * It is not. `exchange_rate_histories` holds three rows, and two of them are USD:
     *
     *     2025-11-26 06:52:54   USD->KWD   0.305220 -> 0.340000   (manual)
     *     2026-08-20 17:06:08   USD->KWD   0.340000 -> 0.310000   (manual)
     *
     * USD is therefore a natural experiment — the one pair whose rate moved twice. Grouping every
     * USD task by the era its `created_at` falls in:
     *
     *     before 2025-11-26   81 tasks   ALL 0.305220   (2025-08-04 .. 2025-10-25)
     *     between the two     92 tasks   ALL 0.340000   (2025-12-02 .. 2026-08-19)
     *     after  2026-08-20   38 tasks   ALL 0.310000   (2026-08-21 .. 2026-09-16)
     *
     * **211 of 211, no exceptions, and the date ranges are disjoint at exactly the two changes.**
     * So the stored rate IS captured at task creation. The 354 equalities are explained by the
     * table being static: every pair except USD and AED has **zero** recorded changes, so for those
     * pairs "the rate on the day" and "today's rate" are necessarily the same number, and equality
     * is evidence of a rate that never moved rather than of a late copy.
     *
     * That is what makes this a real test rather than a proxy. Returns null when the date is
     * unusable; a null NEVER refuses, because "we could not check" must not read as "we checked and
     * it failed".
     */
    private function rateInForceAt(int $companyId, string $code, string $createdAt, float $todaysRate): ?float
    {
        $createdAt = trim($createdAt);

        if ($createdAt === '') {
            return null;
        }

        $rate = $todaysRate;

        // Newest change first. Every change made AFTER the task was created is undone in turn, so
        // what is left is the rate the table held on the day.
        foreach ($this->rateChanges($companyId, $code) as $change) {
            if (strcmp((string) $change->changed_at, $createdAt) > 0) {
                $rate = (float) $change->old_rate;
            }
        }

        return $rate > 0 ? $rate : null;
    }

    /**
     * Recorded changes for one pair, newest first. Cached per (company, code) for the run — the
     * whole table is three rows today, but the read is per task and this command walks hundreds.
     *
     * @return list<object>
     */
    private function rateChanges(int $companyId, string $code): array
    {
        $key = $companyId.'|'.$code;

        if (! array_key_exists($key, $this->rateChangeCache)) {
            $this->rateChangeCache[$key] = DB::table('exchange_rate_histories')
                ->whereRaw('UPPER(TRIM(base_currency)) = ?', [$code])
                ->whereRaw('UPPER(TRIM(exchange_currency)) = ?', [$this->baseCurrency()])
                ->orderByDesc('changed_at')
                ->get(['changed_at', 'old_rate', 'new_rate'])
                ->all();
        }

        return $this->rateChangeCache[$key];
    }

    /** @var array<string, list<object>> */
    private array $rateChangeCache = [];

    /**
     * Today's `currency_exchanges` rates, keyed by foreign code. Used for DETECTION ONLY — see the
     * class docblock. Note the column naming: `base_currency` holds the FOREIGN code and
     * `exchange_currency` the currency it converts INTO, which is the opposite of what the names
     * suggest and is why this lookup is written out here rather than inlined.
     *
     * @return array<string, float>
     */
    private function tableRates(int $companyId, string $base): array
    {
        return DB::table('currency_exchanges')
            ->where('company_id', $companyId)
            ->whereRaw('UPPER(TRIM(exchange_currency)) = ?', [$base])
            ->get(['base_currency', 'exchange_rate'])
            ->mapWithKeys(fn ($r) => [strtoupper(trim((string) $r->base_currency)) => (float) $r->exchange_rate])
            ->all();
    }

    private function rollback(string $runId): int
    {
        $restorable = [self::COLUMN_TOTAL, self::COLUMN_PRICE];

        // CT-A9 verify (item 6): keyed on the PAIR, like every other read into this table. This was
        // the last one still filtering on `subject_table` alone — safe today, because nothing else
        // writes `tasks` before-images, but that is precisely the assumption
        // `BackfillPayablePartyReference` rested on until a second command started writing
        // `journal_entries`. The owned columns come from the one map rather than from a literal
        // here, so a future writer of `tasks` cannot silently widen this command's undo.
        $ownedColumns = BeforeImageOwnership::columnsOwnedBy('accounting:repair-task-fx-conversion', self::SUBJECT_TABLE);

        $rows = DB::table('coa_linkage_changes')
            ->where('run_id', $runId)
            ->where('subject_table', self::SUBJECT_TABLE)
            ->whereIn('column_name', $ownedColumns)
            ->whereNull('rolled_back_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $anyForRun = DB::table('coa_linkage_changes')->where('run_id', $runId);

            if ((clone $anyForRun)->where('subject_table', self::SUBJECT_TABLE)->whereIn('column_name', $ownedColumns)->exists()) {
                $this->line("Run {$runId}: every recorded task was already rolled back. Nothing to do.");

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
        $estimates = [];
        $skipped = [];

        DB::transaction(function () use ($rows, $restorable, &$restored, &$estimates, &$skipped) {
            foreach ($rows as $row) {
                $column = (string) $row->column_name;

                if ($column === self::OPERATOR_SUPPLIED_RATE) {
                    // A label, not a value — there is nothing to put back. Reported so the undo
                    // says out loud that an ESTIMATE is being withdrawn, then marked done so the
                    // run cannot be half-rolled-back forever.
                    $estimates[] = sprintf('task #%d was converted on an operator-supplied rate %s — estimate withdrawn', (int) $row->subject_id, (string) $row->after_value);

                    DB::table('coa_linkage_changes')->where('id', $row->id)->update([
                        'rolled_back_at' => now(),
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                if (! in_array($column, $restorable, true)) {
                    $skipped[] = sprintf('tasks #%d.%s: this command does not restore that column — left alone', (int) $row->subject_id, $column);

                    continue;
                }

                $current = DB::table('tasks')->where('id', $row->subject_id)->value($column);

                if (abs((float) $current - (float) $row->after_value) > 0.0005) {
                    $skipped[] = sprintf(
                        'tasks #%d.%s: now %s, this run wrote %s — left alone',
                        (int) $row->subject_id,
                        $column,
                        $current === null ? 'NULL' : (string) $current,
                        (string) $row->after_value
                    );

                    continue;
                }

                DB::table('tasks')->where('id', $row->subject_id)->update([
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

        foreach ($estimates as $note) {
            $this->warn('  '.$note);
        }

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

    /** @return string[] */
    public static function beforeImageColumns(): array
    {
        return [self::COLUMN_TOTAL, self::COLUMN_PRICE, self::OPERATOR_SUPPLIED_RATE];
    }
}
