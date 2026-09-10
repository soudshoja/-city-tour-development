<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1). One document class the `accounting:replay` backfill command can drive.
 *
 * ── Why this exists ─────────────────────────────────────────────────────────────────────────────
 * CT-A2 §3 had to write the replay by hand, twice (`ct_a2_replay_sale.php`, `ct_a2_replay_more.php`,
 * later `ct_a3_replay_issuance.php`), outside the repo. CT-A3 wave 1 §7 left "the
 * `accounting:replay` backfill command" open, and the phase plan's E9 names it as blocking "the
 * whole migration": a cutover has to be able to walk history in document-date order, post through
 * the SAME feeders and the SAME idempotency keys production uses, and be re-runnable.
 *
 * ── The one hard rule every source obeys ────────────────────────────────────────────────────────
 * A source posts through the production feeder's own idempotency key, never a replay-only key.
 * That is what makes the command idempotent (a second run posts 0, because
 * {@see \App\Services\Accounting\PostingService::post()} step 1 returns the existing document on a
 * duplicate `(company_id, idempotency_key)` pair) and what makes it safe to interleave with live
 * traffic. {@see self::idempotencyKeyFor()} is asserted against the feeder's own literal key
 * format by `tests/Feature/Accounting/CtA3/W21AccountingReplayCommandTest.php`, so a feeder that
 * changes its key without changing its replay source fails the suite rather than silently
 * double-posting on the next cutover.
 *
 * ── And the one thing no source ever does ───────────────────────────────────────────────────────
 * Touch a legacy journal row. Every source READS source tables and WRITES only through
 * `PostingService`, which stamps `posting_date` on everything it writes; the legacy rows
 * (`posting_date IS NULL`, `idempotency_key IS NULL`) are never selected, updated or deleted.
 */
interface ReplaySource
{
    /** The `--class=` token this source answers to (`sale`, `commission`, `receipt`, …). */
    public function name(): string;

    /**
     * Every source row for this company inside the date window, in DOCUMENT-DATE order — the
     * order a real backfill must post in, so period-by-period totals are meaningful while the
     * run is still going.
     *
     * @return iterable<Model>
     */
    public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable;

    /** The production feeder's own idempotency key for this row. */
    public function idempotencyKeyFor(Model $row): string;

    /**
     * Decide and post this one row.
     *
     * There is deliberately no `$dryRun` flag here. `accounting:replay --dry-run` wraps the WHOLE
     * run in one database transaction and rolls it back at the end, so a dry run exercises the
     * real feeders, the real {@see \App\Services\Accounting\AccountResolver}, and every one of
     * PostingService's twelve guards - and therefore reports the refusals a real run would
     * actually hit, with their real exception classes. A flag-driven "simulation" could only ever
     * report the refusals someone remembered to re-implement, which is exactly how CT-A2's ad-hoc
     * script came to under-report CT-F34's 31 refusals as a bare count with no reason.
     *
     * A source MUST NOT throw for an ordinary refusal - it returns
     * {@see ReplayOutcome::refused()} carrying the reason and the exception class, so one bad row
     * cannot end a 12,000-document backfill. It MAY let a genuinely unexpected error escape; the
     * command catches that too and records it as a refusal against the row, then continues.
     */
    public function replay(Model $row): ReplayOutcome;

    /**
     * A one-line human description of the row for the refusal listing (e.g. `invoice_detail #442`).
     */
    public function describe(Model $row): string;
}
