<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\ClosedYearProcedureException;
use App\Exceptions\Accounting\ReserveAppropriationException;
use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * XBRL-X9r (PLAN.md X9r, L15, L25; H2 N-B2): posting into a year that is already closed.
 *
 * ── Why this exists ───────────────────────────────────────────────────────────────────────────────
 * A plain post dated in a `locked` or `soft_closed` period with no valid override is NOT refused:
 * {@see PostingService::post()} step 5 silently moves it to the earliest open period. So an audit
 * adjustment for FY2025, posted after FY2025 was locked and closed, lands in January 2026 and
 * nothing errors (X9rClosedYearTest keeps that trap as a record). Two supported routes remain:
 *
 * 1. **Reopen the closed year** (L25 rule 2, L15 route (i)): {@see self::reopenYear()} reopens the
 *    periods from the latest closed one back to December of the year (through
 *    {@see PeriodCloseService::reopen()}, which enforces the later-first dependency rule and
 *    writes one audit row per period), then reverses the year's live YEC through
 *    {@see PostingService::reverse()}, dated 31 December. The adjustments are then posted dated
 *    in December; {@see self::recloseYear()} locks December, re-runs the YEC (which, since X9r,
 *    treats a reversed YEC as not closed and posts under a fresh idempotency key) and restores
 *    every later period to the status it had before. {@see self::postIntoClosedYear()} runs the
 *    three steps as one atomic unit and refuses an adjustment that would not land on its own date.
 *
 * 2. **A prior-period adjustment** (L25 rule 3, L15 route (ii), IAS 8): a `PPA` document dated
 *    1 January of the current year, posted through PostingService, which enforces its shape
 *    (balance-sheet leaves and opening retained earnings only). {@see self::openingPositionIncludingPpa()}
 *    is the opening position the statements read, PPA lines included.
 *
 * ── Concurrency (X9R-VERIFY B-1, m-3) ─────────────────────────────────────────────────────────────
 * reopenYear(), recloseYear() and YearEndCloseService::run() each run in one transaction that
 * first takes the same per-(company, year) row lock ({@see YearEndCloseService::lockYear()}), then
 * reads the live YEC with a locking read. Two operators racing on one year are serialised: the
 * second reopen is refused as "already reopened", the second re-close finds its reopen consumed.
 * Measured with two real processes on committed rows: one reversal, one new YEC, RE 6,000.
 *
 * ── Hard rules ──────────────────────────────────────────────────────────────────────────────────
 * - The locked-period bypass (`allowLockedPeriods`) is never passed here; only the YEC job inside
 *   {@see YearEndCloseService::run()} uses it (L13).
 * - A reversal that PostingService refuses (for example a reconciled line on the YEC:
 *   {@see \App\Exceptions\Accounting\ProtectedLineException}) stops the procedure with that
 *   message. It is never forced.
 * - Balances are read from `journal_entries` through {@see LedgerSource::restrict()}; this class
 *   never reads `accounts.actual_balance` or `journal_entries.balance`.
 * - XBRL X9: recloseYear() re-runs the reserve appropriation ({@see ReserveAppropriationService})
 *   inside the same transaction, while December is still open, before it locks December and
 *   re-runs the YEC. It re-runs a year's appropriation only when one was recorded before.
 */
final class ClosedYearAdjustmentService
{
    /** accounting_audit_log.action of the one row reopenYear() writes; recloseYear() reads it back. */
    public const REOPEN_ACTION = 'reopen_year';

    public const RECLOSE_ACTION = 'reclose_year';

    public const AUDIT_SUBJECT_TYPE = 'fiscal_year';

    private readonly LedgerSource $ledgerSource;

    public function __construct(
        private readonly PeriodCloseService $periods,
        private readonly PostingService $posting,
        private readonly YearEndCloseService $yearEndClose,
        ?LedgerSource $ledgerSource = null,
        private ?ReserveAppropriationService $reserveAppropriation = null,
    ) {
        $this->ledgerSource = $ledgerSource ?? app(LedgerSource::class);
    }

    /** XBRL X9: resolved on first use, so the procedure's existing constructions stay valid. */
    private function reserves(): ReserveAppropriationService
    {
        return $this->reserveAppropriation ??= app(ReserveAppropriationService::class);
    }

    /**
     * Step 1 of L25 rule 2. Atomic: if any step refuses, every period reopened so far and the
     * reversal are rolled back, and the exception propagates unchanged.
     *
     * @return array{
     *     reopened: list<array{year: int, month: int, status_before: string}>,
     *     yec_transaction_id: int,
     *     reversal_transaction_id: int,
     *     audit_log_id: int,
     * }
     *
     * @throws ClosedYearProcedureException when the year has no live YEC, or the reversal would not
     *                                      land in the year.
     * @throws \Illuminate\Auth\Access\AuthorizationException without `accounting.period.reopen`.
     * @throws \App\Exceptions\Accounting\PostingException when PostingService refuses the reversal.
     */
    public function reopenYear(int $companyId, int $year, int $userId, string $reason): array
    {
        if (trim($reason) === '') {
            throw new ClosedYearProcedureException('A reason is required to reopen a closed year.');
        }

        $this->periods->assertCanReopen($userId);

        return DB::transaction(function () use ($companyId, $year, $userId, $reason) {
            // X9R-VERIFY m-3: the same per-(company, year) lock as the close, taken FIRST, then a
            // locking read of the live YEC. A second, racing reopen waits here and then sees the
            // first one's reversal, and is refused cleanly below instead of dying inside post().
            $this->yearEndClose->lockYear($companyId, $year);
            $yec = $this->yearEndClose->liveYec($companyId, $year, forUpdate: true);

            if ($yec === null && ($pending = $this->pendingReopen($companyId, $year)) !== null) {
                throw new ClosedYearProcedureException(sprintf(
                    'Fiscal year %d for company #%d is already reopened (audit row #%d, %s) and not yet re-closed. '
                    .'Post the adjustments dated in December %d, then run accounting:reclose-year %d %d.',
                    $year,
                    $companyId,
                    $pending->id,
                    $pending->created_at,
                    $year,
                    $companyId,
                    $year,
                ));
            }

            if ($yec === null) {
                throw new ClosedYearProcedureException(sprintf(
                    'Fiscal year %d for company #%d has no live year-end close (YEC) document, so there is nothing to reopen. '
                    .'If its December is soft-closed for the audit, post the adjustment into December with the soft-closed override and a reason instead.',
                    $year,
                    $companyId,
                ));
            }

            $reopened = [];

            // Latest first: PeriodCloseService::reopen() refuses to leapfrog a later closed period.
            foreach ($this->closedPeriodsFrom($companyId, $year) as $period) {
                $reopened[] = [
                    'year' => (int) $period->year,
                    'month' => (int) $period->month,
                    'status_before' => (string) $period->status,
                ];

                $this->periods->reopen($companyId, (int) $period->year, (int) $period->month, $userId, $reason);
            }

            $yearEnd = Carbon::create($year, 12, 31)->startOfDay();
            $reversal = $this->posting->reverse($yec, $yearEnd, $userId)->transaction;
            $reversal->refresh();

            $reversalPostingDate = Carbon::parse($reversal->posting_date ?? $reversal->transaction_date);

            if ($reversalPostingDate->year !== $year) {
                throw new ClosedYearProcedureException(sprintf(
                    'The reversal of YEC #%d would post on %s, outside fiscal year %d (its December is still not open). Nothing was changed.',
                    $yec->id,
                    $reversalPostingDate->toDateString(),
                    $year,
                ));
            }

            $log = AccountingLog::write(
                action: self::REOPEN_ACTION,
                companyId: $companyId,
                subjectType: self::AUDIT_SUBJECT_TYPE,
                subjectId: $year,
                transactionId: (int) $reversal->id,
                before: ['yec_transaction_id' => (int) $yec->id, 'periods' => $reopened],
                after: ['reversal_transaction_id' => (int) $reversal->id, 'status' => 'reopened'],
                reason: $reason,
                actorId: $userId,
                postingPeriod: sprintf('%04d-12', $year),
            );

            return [
                'reopened' => $reopened,
                'yec_transaction_id' => (int) $yec->id,
                'reversal_transaction_id' => (int) $reversal->id,
                'audit_log_id' => (int) $log->id,
            ];
        });
    }

    /**
     * Step 3 of L25 rule 2: close the reopened year again, in order.
     *
     * ONE transaction under the year's close lock (X9R-VERIFY B-1): a refusal at any step (a
     * blocking close checklist, a refused YEC) rolls every step back, and a second, racing
     * re-close waits for the first and then finds its reopen already completed. It completes
     * exactly one reopen: the `reclose_year` audit row it writes marks that reopen as consumed,
     * and a later run refuses to replay it (m-4), so a second run changes nothing and writes
     * nothing.
     *
     * @return array{steps: list<string>, changed: bool, yec_transaction_id: ?int}
     *
     * @throws ClosedYearProcedureException when no reopen is recorded, or a step is refused.
     * @throws \Illuminate\Auth\Access\AuthorizationException without `accounting.period.close`.
     */
    public function recloseYear(int $companyId, int $year, int $userId): array
    {
        $this->periods->assertCanClose($userId);

        return DB::transaction(function () use ($companyId, $year, $userId) {
            // X9R-VERIFY B-1: the same lock as the close and the reopen, taken first.
            $this->yearEndClose->lockYear($companyId, $year);

            $reopenLog = $this->latestReopen($companyId, $year);

            if ($reopenLog === null) {
                throw new ClosedYearProcedureException(sprintf(
                    'No reopen of fiscal year %d is recorded for company #%d (accounting:reopen-year), so there is nothing to re-close.',
                    $year,
                    $companyId,
                ));
            }

            // X9R-VERIFY m-4: a reopen that has already been re-closed is never replayed. Replaying
            // it would re-lock every period it recorded, including one reopened on purpose since.
            $done = $this->recloseOf($companyId, $year, (int) $reopenLog->id);

            if ($done !== null) {
                return [
                    'steps' => [sprintf(
                        'Fiscal year %d was already re-closed after reopen #%d (re-close audit row #%d); nothing to do.',
                        $year,
                        $reopenLog->id,
                        $done->id,
                    )],
                    'changed' => false,
                    'yec_transaction_id' => $this->yearEndClose->liveYec($companyId, $year)?->id,
                ];
            }

            /** @var list<array{year: int, month: int, status_before: string}> $recorded */
            $recorded = (array) (($reopenLog->before ?? [])['periods'] ?? []);
            $targetMonth = $this->targetMonth();
            $steps = [];
            $changed = false;

            // 1. XBRL X9 (L13, L25 rule 2): re-run the reserve appropriation while December is still
            // open, BEFORE it is locked and before the YEC. It is idempotent: it posts only the
            // difference the adjustments made to what is due (nothing when nothing changed), or turns
            // a transfer into a nil appropriation when the year became a loss. A year whose
            // appropriation was never run is not given one here: that is the accountant's own
            // approval-gated step (accounting:appropriate-reserves), and the step says so.
            $appropriation = $this->reserves();
            if ($appropriation->hasRecord($companyId, $year)) {
                try {
                    $run = $appropriation->appropriate($companyId, $year, $userId);
                } catch (ReserveAppropriationException $e) {
                    throw new ClosedYearProcedureException('The reserve appropriation refused: '.$e->getMessage(), 0, $e);
                }
                foreach ($run['steps'] as $line) {
                    $steps[] = 'Reserve appropriation: '.$line;
                }
                $changed = $changed || $run['transaction_id'] !== null;
            } else {
                $steps[] = sprintf(
                    'Reserve appropriation: never run for %d, so nothing was re-run. Run accounting:appropriate-reserves %d %d before relying on the figures.',
                    $year,
                    $companyId,
                    $year,
                );
            }

            // 2. Lock the target period (December, or the annual row).
            if ($this->statusOf($companyId, $year, $targetMonth) !== AccountingPeriod::STATUS_LOCKED) {
                $this->closeOrFail($companyId, $year, $targetMonth, AccountingPeriod::STATUS_LOCKED, $userId);
                $steps[] = sprintf('Locked %s.', $this->label($year, $targetMonth));
                $changed = true;
            } else {
                $steps[] = sprintf('%s already locked.', $this->label($year, $targetMonth));
            }

            // 3. Re-run the year-end close.
            $result = $this->yearEndClose->run($companyId, $year, $userId);

            if (! $result['success']) {
                throw new ClosedYearProcedureException(sprintf(
                    'The year-end close of %d refused: %s',
                    $year,
                    implode(' | ', $result['blocking']),
                ));
            }

            $yecId = $result['transaction']?->id;

            if ($result['already_closed']) {
                $steps[] = sprintf('Year-end close already live (YEC #%d).', $yecId);
            } elseif ($result['transaction'] === null) {
                $steps[] = 'Year-end close: no profit or loss left to sweep; nothing posted.';
            } else {
                $steps[] = sprintf(
                    'Year-end close posted: YEC #%d, net profit/(loss) %s.',
                    $yecId,
                    number_format((float) $result['net_profit'], 3),
                );
                $changed = true;
            }

            // 4. Restore every later period to the status it had before the reopen, oldest first.
            $later = array_values(array_filter(
                $recorded,
                fn (array $p) => ! ((int) $p['year'] === $year && (int) $p['month'] === $targetMonth),
            ));
            usort($later, fn (array $a, array $b) => [(int) $a['year'], (int) $a['month']] <=> [(int) $b['year'], (int) $b['month']]);

            foreach ($later as $p) {
                $y = (int) $p['year'];
                $m = (int) $p['month'];
                $wanted = (string) $p['status_before'];

                if ($this->statusOf($companyId, $y, $m) === $wanted) {
                    $steps[] = sprintf('%s already %s.', $this->label($y, $m), $wanted);

                    continue;
                }

                $this->closeOrFail($companyId, $y, $m, $wanted, $userId);
                $steps[] = sprintf('Restored %s to %s.', $this->label($y, $m), $wanted);
                $changed = true;
            }

            // Written whenever a reopen is completed, changed or not: it is what marks the reopen
            // as consumed (m-4).
            AccountingLog::write(
                action: self::RECLOSE_ACTION,
                companyId: $companyId,
                subjectType: self::AUDIT_SUBJECT_TYPE,
                subjectId: $year,
                transactionId: $yecId,
                before: ['reopen_audit_log_id' => (int) $reopenLog->id],
                after: ['yec_transaction_id' => $yecId, 'steps' => $steps],
                actorId: $userId,
                postingPeriod: sprintf('%04d-12', $year),
            );

            return ['steps' => $steps, 'changed' => $changed, 'yec_transaction_id' => $yecId];
        });
    }

    /** The newest `reopen_year` audit row of the year, or null. */
    private function latestReopen(int $companyId, int $year): ?AccountingAuditLog
    {
        return AccountingAuditLog::query()
            ->where('company_id', $companyId)
            ->where('action', self::REOPEN_ACTION)
            ->where('subject_type', self::AUDIT_SUBJECT_TYPE)
            ->where('subject_id', $year)
            ->orderByDesc('id')
            ->first();
    }

    /** The `reclose_year` row that completed the given reopen, or null. */
    private function recloseOf(int $companyId, int $year, int $reopenLogId): ?AccountingAuditLog
    {
        return AccountingAuditLog::query()
            ->where('company_id', $companyId)
            ->where('action', self::RECLOSE_ACTION)
            ->where('subject_type', self::AUDIT_SUBJECT_TYPE)
            ->where('subject_id', $year)
            ->where('id', '>', $reopenLogId)
            ->orderBy('id')
            ->first();
    }

    /** The newest reopen of the year when it has not been re-closed yet, else null. */
    private function pendingReopen(int $companyId, int $year): ?AccountingAuditLog
    {
        $reopen = $this->latestReopen($companyId, $year);

        return $reopen !== null && $this->recloseOf($companyId, $year, (int) $reopen->id) === null ? $reopen : null;
    }

    /**
     * The whole of L25 rule 2 for one adjustment, as ONE atomic unit: reopen, post, re-close.
     *
     * Refuses, before anything is written, an adjustment that is not an ordinary document (a YEC,
     * a PPA or a reversal), asks for the locked-period bypass, or is dated outside the year. After
     * posting it refuses (rolling EVERYTHING back) if the adjustment did not land on its own date:
     * that is the silent shift of N-B2, and it happens when the adjustment is dated in a month the
     * reopen does not open (every month before December stays locked).
     *
     * @return array{reopen: array, adjustment_transaction_id: int, reclose: array}
     */
    public function postIntoClosedYear(DocumentDraft $adjustment, int $userId, string $reason): array
    {
        $docDate = Carbon::instance($adjustment->docDate);
        $year = $docDate->year;

        if (in_array($adjustment->docType, [ClosingDocuments::YEAR_END_CLOSE, ClosingDocuments::PRIOR_PERIOD_ADJUSTMENT, ClosingDocuments::REVERSAL], true)) {
            throw new ClosedYearProcedureException("A {$adjustment->docType} document cannot be posted through the reopen procedure.");
        }

        if ($adjustment->allowLockedPeriods) {
            throw new ClosedYearProcedureException('The reopen procedure never uses the locked-period bypass; it is reserved for the year-end close job.');
        }

        return DB::transaction(function () use ($adjustment, $userId, $reason, $docDate, $year) {
            $reopen = $this->reopenYear($adjustment->companyId, $year, $userId, $reason);

            $posted = $this->posting->post($adjustment, $userId)->transaction;
            $posted->refresh();
            $landed = Carbon::parse($posted->posting_date ?? $posted->transaction_date);

            if ($landed->toDateString() !== $docDate->toDateString()) {
                throw new ClosedYearProcedureException(sprintf(
                    'The adjustment dated %s would land on %s, not in its own period, because that period is still closed '
                    .'(the reopen opens December %d and the periods after it only). Date it in December %d. Nothing was changed.',
                    $docDate->toDateString(),
                    $landed->toDateString(),
                    $year,
                    $year,
                ));
            }

            $reclose = $this->recloseYear($adjustment->companyId, $year, $userId);

            return [
                'reopen' => $reopen,
                'adjustment_transaction_id' => (int) $posted->id,
                'reclose' => $reclose,
            ];
        });
    }

    /**
     * The OPENING position of the fiscal year starting `$cyStart` (PLAN.md L15, X2 contract):
     * every row dated on or before the day before `$cyStart` (YEC documents included, so P&L
     * leaves open at zero and Retained Earnings carries the swept profit), PLUS the lines of the
     * prior-period adjustment family (`PPA`, and a reversal of one) dated `$cyStart` itself.
     *
     * Hand-off to the comparatives layer (X14 `ComparativesSource`, not built here): this is the
     * "ledger opening" L15's gate compares with the PY closing on file; `balancesAsOf()` in the X2
     * contract is the same figure WITHOUT the PPA lines.
     *
     * Contract (the X2 figure rules): integer fils, debit-positive, keyed by account id; rows read
     * through LedgerSource::restrict() and `deleted_at IS NULL`; date basis
     * `COALESCE(posting_date, transaction_date)`; an account with no qualifying row, or a zero
     * total, is absent. Every account that carries a line is returned (the engine posts to leaves
     * only), so no money is dropped by a leaf filter.
     *
     * @return array<int, int> account id => signed balance in integer fils (debit-positive)
     */
    public function openingPositionIncludingPpa(int $companyId, CarbonImmutable $cyStart): array
    {
        if ($cyStart->month !== 1 || $cyStart->day !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'The opening position is taken at the first day of a fiscal year (1 January); got %s.',
                $cyStart->toDateString(),
            ));
        }

        $eve = $cyStart->subDay()->endOfDay()->toDateTimeString();
        $dayStart = $cyStart->startOfDay()->toDateTimeString();
        $dayEnd = $cyStart->endOfDay()->toDateTimeString();

        $query = DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->where('a.company_id', $companyId)
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->where(function ($when) use ($eve, $dayStart, $dayEnd) {
                $when->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $eve)
                    ->orWhere(function ($ppa) use ($dayStart, $dayEnd) {
                        $ppa->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$dayStart, $dayEnd])
                            ->whereExists(function ($doc) {
                                $doc->selectRaw('1')
                                    ->from('transactions as ppa_t')
                                    ->whereColumn('ppa_t.id', 'je.transaction_id')
                                    ->whereNull('ppa_t.deleted_at');
                                ClosingDocuments::wherePriorPeriodAdjustmentFamily($doc, 'ppa_t');
                            });
                    });
            });

        $this->ledgerSource->restrict($query, $companyId, 'je.transaction_id');

        $rows = $query
            ->groupBy('je.account_id')
            ->selectRaw('je.account_id AS account_id, CAST(ROUND(SUM(je.debit - je.credit) * 1000) AS SIGNED) AS fils')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $fils = (int) $row->fils;

            if ($fils !== 0) {
                $out[(int) $row->account_id] = $fils;
            }
        }

        ksort($out);

        return $out;
    }

    /**
     * Every soft-closed or locked period from the target period (December of `$year`, or the
     * annual row) onward, latest first.
     *
     * @return \Illuminate\Support\Collection<int, AccountingPeriod>
     */
    private function closedPeriodsFrom(int $companyId, int $year)
    {
        $targetMonth = $this->targetMonth();

        return AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [AccountingPeriod::STATUS_SOFT_CLOSED, AccountingPeriod::STATUS_LOCKED])
            ->where(fn ($q) => $q->where('year', '>', $year)
                ->orWhere(fn ($q2) => $q2->where('year', $year)->where('month', '>=', $targetMonth)))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    private function closeOrFail(int $companyId, int $year, int $month, string $status, int $userId): void
    {
        $result = $this->periods->close($companyId, $year, $month, $status, $userId);

        if (! $result['applied']) {
            $blocking = array_map(
                fn ($b) => is_array($b) ? (string) ($b['message'] ?? json_encode($b)) : (string) $b,
                (array) ($result['checklist']['blocking'] ?? []),
            );

            throw new ClosedYearProcedureException(sprintf(
                'Could not set %s to %s: the close checklist blocks it: %s. Fix it and re-run accounting:reclose-year; the steps already done stand.',
                $this->label($year, $month),
                $status,
                $blocking === [] ? 'no reason given' : implode(' | ', $blocking),
            ));
        }
    }

    private function statusOf(int $companyId, int $year, int $month): string
    {
        return (string) (AccountingPeriod::query()
            ->where('company_id', $companyId)->where('year', $year)->where('month', $month)
            ->value('status') ?? AccountingPeriod::STATUS_OPEN);
    }

    private function targetMonth(): int
    {
        return (string) config('accounting.period.length', 'monthly') === 'annual' ? AccountingPeriod::ANNUAL_MONTH : 12;
    }

    private function label(int $year, int $month): string
    {
        return $month === AccountingPeriod::ANNUAL_MONTH ? sprintf('%04d (annual)', $year) : sprintf('%04d-%02d', $year, $month);
    }
}
