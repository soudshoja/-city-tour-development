<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Events\Accounting\ReserveAppropriationRecorded;
use App\Exceptions\Accounting\ReserveAppropriationException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * XBRL X9 (PLAN.md L13, X9, L25 rule 1; H-M3, H m4, H2 N-m7, N-m3): the year-end reserve
 * appropriation, `accounting:appropriate-reserves {company} {year}`.
 *
 * ── What is due (Companies Law 1/2016 Art. 222 and 225, applied to a WLL by Art. 118; D §3) ────
 * - **Statutory reserve:** 10% of the year's net profit, transferred IN FULL in any year that opens
 *   with the statutory reserve at or below 50% of the issued capital, and NIL in a year that opens
 *   above it (N-m7). At exactly 50% a transfer is still due (H m4). "Opens" is the reserve's
 *   balance at the end of the previous year; the issued capital is its balance at this year's end
 *   (the capital the general meeting resolves on).
 * - **Voluntary reserve:** the owner-set rate (OD-3, default 0%, at most 10%) of the net profit.
 *   The rate is kept per year: an explicit rate, else the rate this year's last run recorded,
 *   else `accounting.reserves.voluntary_rate_basis_points`.
 * - **Zero or negative profit (Akeed FY2026):** nothing is due. No journal is posted; a "nil
 *   appropriation" is recorded with the reason and the profit (it satisfies the close checklist, so
 *   a loss year is never blocked). A later profit year never catches up a missed transfer.
 * - The net profit is the year's PRE-CLOSING profit: every Income and Expenses leaf, the year-end
 *   close family and the prior-period adjustment family excluded ({@see ClosingDocuments}), on the
 *   company's ledger source ({@see LedgerSource}). Amounts are integer fils; a percentage is
 *   rounded half-up to the fils.
 *
 * ── How it posts (H-M3) ─────────────────────────────────────────────────────────────────────────
 * One `APR` journal through {@see PostingService}, dated 31 December of the year, Dr Retained
 * Earnings, Cr Statutory Reserve / Voluntary Reserve. It runs AFTER the year-end adjusting
 * journals and BEFORE December is locked and the YEC; it is equity-to-equity, so the YEC is
 * unaffected. It never passes the locked-period bypass:
 * - December `locked` and a journal is due: refused, naming the fix (reopen or run before locking);
 * - December `soft_closed` for the audit (L25 rule 1): posted through the existing soft-closed
 *   override, which needs `accounting.period.post-soft-closed` on the acting user AND a reason;
 *   with no reason it refuses; a journal that would land anywhere but 31 December (the silent
 *   shift of a post with no valid override) is refused and rolled back, never left shifted.
 *
 * ── Idempotent, and re-computed when stale ─────────────────────────────────────────────────────
 * What is already appropriated is read from the ledger: the `APR` family (an `APR` and a reversal
 * of one) on the two reserve accounts, dated in the year. A run posts only the DIFFERENCE between
 * what is due now and what is posted, as a new `APR` journal (a correction when the profit changed
 * after the first run, for example an audit adjustment posted into the soft-closed December). So a
 * re-run with nothing changed posts nothing and records nothing, and a profit that turns into a
 * loss is corrected back to a nil transfer, never to a negative one. A correction journal is used,
 * not a reversal, because {@see PostingService::reverse()} carries no override reason and would be
 * shifted out of a soft-closed December.
 *
 * Serialised with the close, the reopen and the re-close of the same year by the same row lock
 * ({@see YearEndCloseService::lockYear()}), and the journal's idempotency key is derived from the
 * number of `APR` journals already dated in the year, so two racing runs derive the same key.
 *
 * Records: one `reserve_appropriation` row in `accounting_audit_log` per change (the host's
 * durable record), and {@see ReserveAppropriationRecorded}, which the XBRL module turns into its
 * filing-trail event.
 */
final class ReserveAppropriationService
{
    public const DOC_TYPE = 'APR';

    public const AUDIT_ACTION = 'reserve_appropriation';

    public const AUDIT_SUBJECT_TYPE = 'fiscal_year';

    /** Companies Law Art. 222: 10% of net profit, in basis points. */
    public const STATUTORY_RATE_BASIS_POINTS = 1_000;

    /** Companies Law Art. 225 (Arabic text, governing): the voluntary reserve is at most 10%. */
    public const MAX_VOLUNTARY_RATE_BASIS_POINTS = 1_000;

    private const DATE_BASIS = 'COALESCE(je.posting_date, je.transaction_date)';

    private readonly LedgerSource $ledgerSource;

    public function __construct(
        private readonly PostingService $posting,
        private readonly PeriodGuard $periodGuard,
        private readonly PeriodCloseService $periods,
        private readonly YearEndCloseService $yearEndClose,
        private readonly AccountResolver $accountResolver,
        ?LedgerSource $ledgerSource = null,
    ) {
        $this->ledgerSource = $ledgerSource ?? app(LedgerSource::class);
    }

    /**
     * What is due for the year and what is already posted, in fils; nothing is written.
     *
     * @return array{
     *     company_id: int, year: int, profit_fils: int, capital_fils: int,
     *     statutory_opening_fils: int, cap_reached: bool,
     *     statutory_due_fils: int, voluntary_rate_basis_points: int, voluntary_due_fils: int,
     *     statutory_posted_fils: int, voluntary_posted_fils: int,
     *     outcome: string, nil_reason: ?string, stale: bool,
     * }
     */
    public function compute(int $companyId, int $year, ?int $voluntaryRateBasisPoints = null): array
    {
        $rate = $this->resolveVoluntaryRate($companyId, $year, $voluntaryRateBasisPoints);

        $profit = $this->profitFils($companyId, $year);
        $capital = $this->creditBalanceFils($companyId, $this->capitalAccountIds($companyId), sprintf('%04d-12-31', $year));

        $statutory = $this->optionalPurpose('STATUTORY_RESERVE', $companyId);
        $voluntary = $this->optionalPurpose('VOLUNTARY_RESERVE', $companyId);

        $statutoryOpening = $statutory === null ? 0 : $this->creditBalanceFils($companyId, [$statutory->id], sprintf('%04d-12-31', $year - 1));

        // N-m7 / H m4: the full 10% in a year that OPENS at or below 50% of the issued capital;
        // nil in a year that opens above it. 2 * reserve <= capital is "at or below half", in
        // integers (no division, so 50% + 1 fils is above and exactly 50% is not).
        $capReached = 2 * $statutoryOpening > $capital;

        $statutoryDue = 0;
        $voluntaryDue = 0;
        if ($profit > 0) {
            $statutoryDue = $capReached ? 0 : self::percentOf($profit, self::STATUTORY_RATE_BASIS_POINTS);
            $voluntaryDue = self::percentOf($profit, $rate);
        }

        $statutoryPosted = $statutory === null ? 0 : $this->appropriatedFils($companyId, $statutory->id, $year);
        $voluntaryPosted = $voluntary === null ? 0 : $this->appropriatedFils($companyId, $voluntary->id, $year);

        $outcome = match (true) {
            $profit <= 0 => ReserveAppropriationRecorded::OUTCOME_NIL,
            $statutoryDue + $voluntaryDue > 0 => ReserveAppropriationRecorded::OUTCOME_POSTED,
            default => ReserveAppropriationRecorded::OUTCOME_CAP,
        };
        $nilReason = match (true) {
            $profit < 0 => 'loss',
            $profit === 0 => 'zero_profit',
            default => null,
        };

        $last = $this->lastRecord($companyId, $year);
        $stale = $statutoryDue !== $statutoryPosted
            || $voluntaryDue !== $voluntaryPosted
            || $last === null
            || ($last->after['outcome'] ?? null) !== $outcome
            || ($last->after['profit_fils'] ?? null) !== $profit;

        return [
            'company_id' => $companyId,
            'year' => $year,
            'profit_fils' => $profit,
            'capital_fils' => $capital,
            'statutory_opening_fils' => $statutoryOpening,
            'cap_reached' => $capReached,
            'statutory_due_fils' => $statutoryDue,
            'voluntary_rate_basis_points' => $rate,
            'voluntary_due_fils' => $voluntaryDue,
            'statutory_posted_fils' => $statutoryPosted,
            'voluntary_posted_fils' => $voluntaryPosted,
            'outcome' => $outcome,
            'nil_reason' => $nilReason,
            'stale' => $stale,
        ];
    }

    /**
     * Whether the year's appropriation must be (re-)run: nothing recorded yet, or the profit, the
     * cap or the rate changed since it was (the close checklist's "stale appropriation", L13).
     */
    public function isStale(int $companyId, int $year): bool
    {
        return $this->compute($companyId, $year)['stale'];
    }

    /** Whether the year's appropriation was ever run (posted, nil or cap reached). */
    public function hasRecord(int $companyId, int $year): bool
    {
        return $this->lastRecord($companyId, $year) !== null;
    }

    /**
     * Runs the appropriation. Atomic: a refusal at any step leaves nothing written.
     *
     * @return array{changed: bool, transaction_id: ?int, steps: list<string>, figures: array<string, mixed>}
     *
     * @throws ReserveAppropriationException
     * @throws \Illuminate\Auth\Access\AuthorizationException without `accounting.period.close`
     */
    public function appropriate(int $companyId, int $year, int $userId, ?string $reason = null, ?int $voluntaryRateBasisPoints = null): array
    {
        $this->periods->assertCanClose($userId);

        return DB::transaction(function () use ($companyId, $year, $userId, $reason, $voluntaryRateBasisPoints) {
            // The same per-(company, year) lock as the close, the reopen and the re-close.
            $this->yearEndClose->lockYear($companyId, $year);

            $f = $this->compute($companyId, $year, $voluntaryRateBasisPoints);
            $statutoryDelta = $f['statutory_due_fils'] - $f['statutory_posted_fils'];
            $voluntaryDelta = $f['voluntary_due_fils'] - $f['voluntary_posted_fils'];

            $steps = [sprintf(
                'FY%d net profit/(loss) %s; issued capital %s; statutory reserve at the start of the year %s (%s 50%% of capital).',
                $year,
                self::kwd($f['profit_fils']),
                self::kwd($f['capital_fils']),
                self::kwd($f['statutory_opening_fils']),
                $f['cap_reached'] ? 'above' : 'at or below',
            )];

            $transactionId = null;
            if ($statutoryDelta !== 0 || $voluntaryDelta !== 0) {
                $transactionId = $this->postDifference($companyId, $year, $userId, $reason, $statutoryDelta, $voluntaryDelta);
                $steps[] = sprintf(
                    'Posted APR journal #%d dated %d-12-31: statutory reserve %s, voluntary reserve %s (the year now carries %s and %s).',
                    $transactionId,
                    $year,
                    self::signedKwd($statutoryDelta),
                    self::signedKwd($voluntaryDelta),
                    self::kwd($f['statutory_due_fils']),
                    self::kwd($f['voluntary_due_fils']),
                );
            } else {
                $steps[] = sprintf(
                    'Nothing to post: the year already carries statutory %s and voluntary %s, which is what is due.',
                    self::kwd($f['statutory_posted_fils']),
                    self::kwd($f['voluntary_posted_fils']),
                );
            }

            $last = $this->lastRecord($companyId, $year);
            $record = $transactionId !== null
                || $last === null
                || ($last->after['outcome'] ?? null) !== $f['outcome']
                || ($last->after['profit_fils'] ?? null) !== $f['profit_fils']
                || ($last->after['voluntary_rate_basis_points'] ?? null) !== $f['voluntary_rate_basis_points'];

            if ($record) {
                AccountingLog::write(
                    action: self::AUDIT_ACTION,
                    companyId: $companyId,
                    subjectType: self::AUDIT_SUBJECT_TYPE,
                    subjectId: $year,
                    transactionId: $transactionId,
                    before: ['statutory_posted_fils' => $f['statutory_posted_fils'], 'voluntary_posted_fils' => $f['voluntary_posted_fils']],
                    after: [
                        'outcome' => $f['outcome'],
                        'nil_reason' => $f['nil_reason'],
                        'profit_fils' => $f['profit_fils'],
                        'capital_fils' => $f['capital_fils'],
                        'statutory_opening_fils' => $f['statutory_opening_fils'],
                        'cap_reached' => $f['cap_reached'],
                        'statutory_fils' => $f['statutory_due_fils'],
                        'voluntary_rate_basis_points' => $f['voluntary_rate_basis_points'],
                        'voluntary_fils' => $f['voluntary_due_fils'],
                    ],
                    reason: $reason !== null && trim($reason) !== '' ? $reason : null,
                    actorId: $userId,
                    postingPeriod: sprintf('%04d-12', $year),
                );

                event(new ReserveAppropriationRecorded(
                    companyId: $companyId,
                    fiscalYear: $year,
                    outcome: $f['outcome'],
                    profitFils: $f['profit_fils'],
                    statutoryFils: $f['statutory_due_fils'],
                    voluntaryFils: $f['voluntary_due_fils'],
                    nilReason: $f['nil_reason'],
                    transactionId: $transactionId,
                    userId: $userId,
                ));

                $steps[] = match ($f['outcome']) {
                    ReserveAppropriationRecorded::OUTCOME_NIL => sprintf('Recorded a nil appropriation (%s, profit %s): no transfer is due.', $f['nil_reason'], self::kwd($f['profit_fils'])),
                    ReserveAppropriationRecorded::OUTCOME_CAP => 'Recorded: no statutory transfer is due (the reserve opened above 50% of capital) and the voluntary rate is 0.',
                    default => 'Recorded the appropriation.',
                };
            } else {
                $steps[] = 'Already recorded for these figures; nothing changed.';
            }

            return ['changed' => $record, 'transaction_id' => $transactionId, 'steps' => $steps, 'figures' => $f];
        });
    }

    /** Posts one `APR` journal carrying the difference; returns its id. */
    private function postDifference(int $companyId, int $year, int $userId, ?string $reason, int $statutoryDelta, int $voluntaryDelta): int
    {
        $yearEnd = Carbon::create($year, 12, 31)->startOfDay();
        $status = $this->periodGuard->statusFor($companyId, $yearEnd);

        if ($status === AccountingPeriod::STATUS_LOCKED) {
            throw new ReserveAppropriationException(sprintf(
                'December %d is locked, so the reserve appropriation cannot be posted into it (it never uses the locked-period bypass). '
                .'Reopen December or run before locking: accounting:reopen-year %d %d when the year is closed, then accounting:reclose-year, which re-runs the appropriation.',
                $year,
                $companyId,
                $year,
            ));
        }

        $softClosed = $status === AccountingPeriod::STATUS_SOFT_CLOSED;
        if ($softClosed && ($reason === null || trim($reason) === '')) {
            throw new ReserveAppropriationException(sprintf(
                'December %d is soft-closed for the audit: the appropriation posts into it only through the soft-closed override. '
                .'Run again with --reason= and a user holding accounting.period.post-soft-closed. Nothing was posted.',
                $year,
            ));
        }

        $branchId = Company::find($companyId)?->branches()->value('id');
        if ($branchId === null) {
            throw new ReserveAppropriationException("Company #{$companyId} has no branch to post the appropriation against. Nothing was posted.");
        }

        $retained = $this->accountResolver->resolve('RETAINED_EARNINGS', $companyId);
        $lines = [];
        foreach ([['STATUTORY_RESERVE', $statutoryDelta, 'Statutory reserve (Companies Law Art. 222)'], ['VOLUNTARY_RESERVE', $voluntaryDelta, 'Voluntary reserve']] as [$purpose, $delta, $label]) {
            if ($delta === 0) {
                continue;
            }
            $account = $this->accountResolver->resolve($purpose, $companyId);
            $lines[] = self::line($account->id, $delta > 0 ? 'credit' : 'debit', abs($delta), "FY{$year} {$label}: ".($delta > 0 ? 'transfer from' : 'correction back to').' retained earnings.');
        }
        $total = $statutoryDelta + $voluntaryDelta;
        if ($total !== 0) {
            $lines[] = self::line($retained->id, $total > 0 ? 'debit' : 'credit', abs($total), "FY{$year} reserve appropriation out of retained earnings.");
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) $branchId,
            docType: self::DOC_TYPE,
            subType: null,
            docDate: $yearEnd,
            narration: "Year-end reserve appropriation FY{$year} (Companies Law Art. 222/225).",
            lines: $lines,
            idempotencyKey: $this->nextIdempotencyKey($companyId, $year),
            userId: $userId,
            overrideReason: $softClosed ? $reason : null,
        );

        $posted = $this->posting->post($draft, $userId)->transaction;
        $posted->refresh();
        $landed = Carbon::parse($posted->posting_date ?? $posted->transaction_date);

        if ($landed->toDateString() !== $yearEnd->toDateString()) {
            throw new ReserveAppropriationException(sprintf(
                'The appropriation dated %s would land on %s, not in December %d: that period is not open to this post '
                .'(a soft-closed December needs accounting.period.post-soft-closed on the acting user and a reason). Nothing was posted.',
                $yearEnd->toDateString(),
                $landed->toDateString(),
                $year,
            ));
        }

        return (int) $posted->id;
    }

    /**
     * `appr:{company}:{year}` for the first journal of a year, `appr:{company}:{year}:{n}` for the
     * n-th correction: derived from the ledger (the APR journals already dated in the year), so two
     * racing runs derive the same key and the unique `(company_id, idempotency_key)` index makes the
     * second hand back the first's journal.
     */
    private function nextIdempotencyKey(int $companyId, int $year): string
    {
        $existing = DB::table('transactions')
            ->where('company_id', $companyId)
            ->where('doc_type', self::DOC_TYPE)
            ->whereYear('transaction_date', $year)
            ->count();

        return $existing === 0 ? "appr:{$companyId}:{$year}" : "appr:{$companyId}:{$year}:".($existing + 1);
    }

    /** The year's pre-closing profit (loss negative), profit-positive fils. */
    private function profitFils(int $companyId, int $year): int
    {
        $query = DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereIn('root.name', ['Income', 'Expenses'])
            ->where(DB::raw(self::DATE_BASIS), '>=', sprintf('%04d-01-01 00:00:00', $year))
            ->where(DB::raw(self::DATE_BASIS), '<', sprintf('%04d-01-01 00:00:00', $year + 1));

        ClosingDocuments::excludeYearEndCloseFamily($query, 'je.transaction_id');
        ClosingDocuments::excludePriorPeriodAdjustmentFamily($query, 'je.transaction_id');
        $this->ledgerSource->restrict($query, $companyId, 'je.transaction_id');

        return (int) $query->selectRaw('CAST(ROUND(COALESCE(SUM(je.credit - je.debit), 0) * 1000) AS SIGNED) AS f')->value('f');
    }

    /**
     * Credit-positive balance in fils of the given accounts at the END of `$asOf` (every document).
     *
     * @param  list<int>  $accountIds
     */
    private function creditBalanceFils(int $companyId, array $accountIds, string $asOf): int
    {
        if ($accountIds === []) {
            return 0;
        }

        $query = DB::table('journal_entries as je')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereIn('je.account_id', $accountIds)
            ->where(DB::raw(self::DATE_BASIS), '<', Carbon::parse($asOf)->addDay()->toDateString().' 00:00:00');
        $this->ledgerSource->restrict($query, $companyId, 'je.transaction_id');

        return (int) $query->selectRaw('CAST(ROUND(COALESCE(SUM(je.credit - je.debit), 0) * 1000) AS SIGNED) AS f')->value('f');
    }

    /** Credit-positive fils moved on one reserve account by the year's APR family (an APR and a reversal of one). */
    private function appropriatedFils(int $companyId, int $accountId, int $year): int
    {
        $query = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereNull('t.deleted_at')
            ->where('je.account_id', $accountId)
            ->where(fn ($q) => $q->where('t.doc_type', self::DOC_TYPE)
                ->orWhere(fn ($r) => $r->where('t.doc_type', ClosingDocuments::REVERSAL)->where('t.sub_type', self::DOC_TYPE)))
            ->where(DB::raw(self::DATE_BASIS), '>=', sprintf('%04d-01-01 00:00:00', $year))
            ->where(DB::raw(self::DATE_BASIS), '<', sprintf('%04d-01-01 00:00:00', $year + 1));
        $this->ledgerSource->restrict($query, $companyId, 'je.transaction_id');

        return (int) $query->selectRaw('CAST(ROUND(COALESCE(SUM(je.credit - je.debit), 0) * 1000) AS SIGNED) AS f')->value('f');
    }

    /**
     * The issued-capital accounts: every account of the company carrying one of
     * `accounting.reserves.capital_codes` (default the canonical `3100 Capital Stock`), and every
     * descendant of one.
     *
     * @return list<int>
     */
    private function capitalAccountIds(int $companyId): array
    {
        $frontier = DB::table('accounts')->where('company_id', $companyId)
            ->whereIn('code', (array) config('accounting.reserves.capital_codes', ['3100']))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $all = $frontier;
        for ($depth = 0; $depth < 10 && $frontier !== []; $depth++) {
            $frontier = DB::table('accounts')->where('company_id', $companyId)->whereIn('parent_id', $frontier)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $all = array_merge($all, $frontier);
        }

        return array_values(array_unique($all));
    }

    private function optionalPurpose(string $purpose, int $companyId): ?Account
    {
        try {
            return $this->accountResolver->resolve($purpose, $companyId);
        } catch (UnmappedPurposeException) {
            return null;
        }
    }

    private function resolveVoluntaryRate(int $companyId, int $year, ?int $explicit): int
    {
        $rate = $explicit
            ?? ($this->lastRecord($companyId, $year)?->after['voluntary_rate_basis_points'] ?? null)
            ?? (int) config('accounting.reserves.voluntary_rate_basis_points', 0);

        if (! is_int($rate) || $rate < 0 || $rate > self::MAX_VOLUNTARY_RATE_BASIS_POINTS) {
            throw new ReserveAppropriationException(sprintf(
                'The voluntary reserve rate must be between 0%% and 10%% of net profit (Companies Law Art. 225); got %s basis points.',
                var_export($rate, true),
            ));
        }

        return $rate;
    }

    /** The newest `reserve_appropriation` audit row of the year, or null. */
    private function lastRecord(int $companyId, int $year): ?AccountingAuditLog
    {
        return AccountingAuditLog::query()
            ->where('company_id', $companyId)
            ->where('action', self::AUDIT_ACTION)
            ->where('subject_type', self::AUDIT_SUBJECT_TYPE)
            ->where('subject_id', $year)
            ->orderByDesc('id')
            ->first();
    }

    /** `$basisPoints` of a positive fils amount, half-up to the fils, in integers only. */
    public static function percentOf(int $fils, int $basisPoints): int
    {
        return intdiv($fils * $basisPoints + 5_000, 10_000);
    }

    private static function line(int $accountId, string $side, int $fils, string $description): LineDraft
    {
        $amount = $fils / 1000;

        return new LineDraft(
            purposeCode: '',
            accountId: $accountId,
            side: $side,
            amount: $amount,
            currency: (string) config('accounting.engine.base_currency', 'KWD'),
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'RESERVE_APPROPRIATION',
            description: $description,
        );
    }

    private static function kwd(int $fils): string
    {
        return ($fils < 0 ? '-' : '').number_format(intdiv(abs($fils), 1000)).'.'.str_pad((string) (abs($fils) % 1000), 3, '0', STR_PAD_LEFT);
    }

    private static function signedKwd(int $fils): string
    {
        return ($fils > 0 ? '+' : '').self::kwd($fils);
    }
}
