<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\ReconciliationRun;
use App\Services\TrialBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §6) — the daily, trial-balance-style
 * Reconciliation Center grid. "Key distinction: the running/book balance is a ledger property,
 * always available (TrialBalanceService, never accounts.actual_balance). Reconciliation produces
 * the confirmed balance. The grid displays both, plus their difference, side by side."
 *
 * Rows are grouped EXACTLY the way {@see PeriodCloseChecklistService} already groups the month-end
 * account-treatment table (bank/cash/gateway/cheque clearing -> control accounts -> clearing/
 * roll-forward -> P&L review-only) — the brief's own words: "the month-end close checklist reads
 * this same grid." P2.5.G verify fix: the account-SET resolution the two classes need (which
 * leaves count as "bank/cash", which purpose codes/anchors are "control") now lives in ONE place —
 * {@see AccountResolver::bankCashLeafIds()} / {@see AccountResolver::controlAccountGroups()} — so a
 * future config change can never leave the grid and the checklist silently disagreeing about the
 * account set (the drift risk a verify pass flagged when this was two independent copies of the
 * same leaf-walk). What still legitimately differs between the two classes is everything
 * DOWNSTREAM of that shared set: this grid reports book/confirmed/gap/status/counts per row (and
 * drives the drill-down panels below); the checklist reports a pass/fail close-gate. Same
 * account-resolution convention {@see \App\Services\Accounting\PeriodCloseChecklistService::rootMovement()}
 * already established for `getNormalBalance()`'s identical one-line rule — pull the SHARED part
 * into one place, leave the genuinely-different part local to each class.
 *
 * ── BOOK / CONFIRMED / GAP definitions (v0 — no external statement import, reconciliation-design.md
 *    §6/§7) ──────────────────────────────────────────────────────────────────────────────────────
 * BOOK: the leaf's (or leaf-group's) normal-balance-signed closing balance as of the grid's as-of
 * date, from {@see TrialBalanceService::generate()} — never `accounts.actual_balance`.
 *
 * CONFIRMED: BOOK minus the normal-balance-signed net of every line NOT yet locked as "matched":
 *   - bank/cash/clearing groups: `reconciled = 0` lines (journal_entries' own pre-existing match
 *     flag — {@see \App\Services\Accounting\ReconciliationService} already sets `reconciled = 1`
 *     on a matched line; this service's own approve()/manualMatch() do the same).
 *   - control groups: lines with `type_reference_id IS NULL` — the same "untraceable to a party
 *     sub-ledger" definition {@see PeriodCloseChecklistService::controlAccountRow()} already uses
 *     for its own BLOCK-on-mismatch check, so this row's GAP and that checklist's blocking
 *     condition are, by construction, the same number.
 *
 * GAP = BOOK - CONFIRMED (i.e. exactly the "not yet matched" net above). Zero (within
 * `accounting.engine.balance_tolerance`) is green/"reconciled". A non-zero GAP on a control row is
 * a BLOCKING condition for close (mirrors PeriodCloseChecklistService); on a bank/cash/clearing row
 * it is a WARN-only condition, never blocking — brief: "control-row gap blocks close while
 * bank-row gap only warns."
 */
final class ReconciliationCenterService
{
    public function __construct(private readonly AccountResolver $accountResolver) {}

    public const GROUP_BANK_CASH = 'bank_cash';

    public const GROUP_CONTROL = 'control';

    public const GROUP_CLEARING = 'clearing';

    public const GROUP_REVIEW_ONLY = 'review_only';

    /**
     * @return array{
     *   company_id:int, mode:string, as_of:string, period_start:string, period_end:string,
     *   rows: list<array<string,mixed>>,
     * }
     */
    public function grid(int $companyId, \DateTimeInterface $asOf, string $mode = 'day'): array
    {
        $asOfCarbon = $asOf instanceof Carbon ? $asOf : Carbon::parse($asOf->format('Y-m-d H:i:s'));
        [$periodStart, $periodEnd] = $this->bounds($asOfCarbon, $mode);

        $tb = app(TrialBalanceService::class)->generate($companyId, $periodStart, $periodEnd, ['show_zero' => true]);
        $byAccountId = collect($tb['accounts'])->keyBy('id');

        $rows = [];
        $rows = array_merge($rows, $this->bankCashRows($companyId, $byAccountId, $periodEnd));
        $rows = array_merge($rows, $this->controlRows($companyId, $byAccountId, $periodEnd));
        $rows = array_merge($rows, $this->clearingRows($companyId, $byAccountId, $periodStart, $periodEnd));
        $rows = array_merge($rows, $this->reviewOnlyRows($companyId, $periodStart, $periodEnd));

        return [
            'company_id' => $companyId,
            'mode' => $mode,
            'as_of' => $asOfCarbon->toDateString(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'rows' => $rows,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function bounds(Carbon $asOf, string $mode): array
    {
        if ($mode === 'month') {
            return [$asOf->copy()->startOfMonth()->startOfDay(), $asOf->copy()->endOfMonth()->endOfDay()];
        }

        return [$asOf->copy()->startOfDay(), $asOf->copy()->endOfDay()];
    }

    private function tolerance(): float
    {
        return (float) config('accounting.engine.balance_tolerance', 0.0005);
    }

    private function ageingOverDays(): int
    {
        return (int) config('accounting.reconciliation.ageing_over_days', 30);
    }

    private function isDebitNormal(string $rootName): bool
    {
        return in_array($rootName, ['Assets', 'Expenses'], true);
    }

    // ── Row builders ────────────────────────────────────────────────────────────────────────────

    /** @return list<array> */
    private function bankCashRows(int $companyId, \Illuminate\Support\Collection $byAccountId, Carbon $periodEnd): array
    {
        $rows = [];
        foreach ($this->bankCashLeafIds($companyId) as $accountId) {
            $account = Account::withoutGlobalScopes()->find($accountId);
            if ($account === null) {
                continue;
            }

            $tbRow = $byAccountId->get($accountId);
            $book = $tbRow !== null ? (float) $tbRow->closing_balance : 0.0;
            $isDebitNormal = $tbRow !== null ? $this->isDebitNormal($tbRow->root_name) : true;

            $unreconciledNet = $this->signedNet(
                DB::table('journal_entries')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->where('reconciled', 0)
                    ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $periodEnd),
                $isDebitNormal
            );

            $rows[] = $this->buildRow(
                key: 'bank:'.$accountId,
                group: self::GROUP_BANK_CASH,
                label: $account->name,
                code: $account->code,
                accountIds: [$accountId],
                opening: ($tbRow !== null ? (float) $tbRow->closing_balance : 0.0) - ($this->periodNet($tbRow, $isDebitNormal)),
                periodDebit: $tbRow !== null ? (float) $tbRow->total_debit : 0.0,
                periodCredit: $tbRow !== null ? (float) $tbRow->total_credit : 0.0,
                book: $book,
                gap: $unreconciledNet,
                companyId: $companyId,
                blocksClose: false,
            );
        }

        return $rows;
    }

    /**
     * P2.5.G verify fix: delegates to {@see AccountResolver::bankCashLeafIds()} — see this class's
     * own docblock for why the account-set resolution moved to one shared place.
     *
     * @return int[]
     */
    private function bankCashLeafIds(int $companyId): array
    {
        return $this->accountResolver->bankCashLeafIds($companyId);
    }

    /**
     * Every GATEWAY_CLEARING_* leaf configured for this company — used by
     * {@see self::timingDifferenceLineIds()} to name a gateway-clearing row's unmatched lines as a
     * "gateway settlement lag" timing difference rather than a plain unmatched item (owner
     * refinement 2026-08-30's gap-explanation panel).
     *
     * @return int[]
     */
    private function gatewayLeafIds(int $companyId): array
    {
        $ids = [];
        foreach (array_keys(config('accounting.purpose_codes.gateways', [])) as $gatewayKey) {
            try {
                $ids[] = $this->accountResolver->resolve('GATEWAY_CLEARING_'.$gatewayKey, $companyId)->id;
            } catch (UnmappedPurposeException) {
                // Not every company has every gateway mapped.
            }
        }

        return $ids;
    }

    private function gatewaySettlementLagDays(): int
    {
        return (int) config('accounting.reconciliation.gateway_settlement_lag_days', 5);
    }

    /**
     * P2.5.G verify fix: the (purpose_code/anchor -> leaf ids) resolution now lives in
     * {@see AccountResolver::controlAccountGroups()}, shared with
     * PeriodCloseChecklistService::checkControlAccounts() — see this class's own docblock. An
     * empty `account_ids` (nothing mapped/minted for this company yet) is simply omitted from the
     * grid, exactly as before this fix.
     *
     * @return list<array>
     */
    private function controlRows(int $companyId, \Illuminate\Support\Collection $byAccountId, Carbon $periodEnd): array
    {
        $rows = [];

        foreach ($this->accountResolver->controlAccountGroups($companyId) as $group) {
            if ($group['account_ids'] === []) {
                continue;
            }

            $rows[] = $this->controlRow($companyId, $group['purpose_code'], $group['label'], $group['account_ids'], $byAccountId, $periodEnd);
        }

        return $rows;
    }

    /** @param int[] $leafIds */
    private function controlRow(int $companyId, string $purposeCode, string $label, array $leafIds, \Illuminate\Support\Collection $byAccountId, Carbon $periodEnd): array
    {
        $book = 0.0;
        $opening = 0.0;
        $periodDebit = 0.0;
        $periodCredit = 0.0;
        $isDebitNormal = true;

        foreach ($leafIds as $id) {
            $tbRow = $byAccountId->get($id);
            if ($tbRow === null) {
                continue;
            }
            $isDebitNormal = $this->isDebitNormal($tbRow->root_name);
            $book += (float) $tbRow->closing_balance;
            $periodDebit += (float) $tbRow->total_debit;
            $periodCredit += (float) $tbRow->total_credit;
            $opening += (float) $tbRow->closing_balance - $this->periodNet($tbRow, $isDebitNormal);
        }

        $unattributedNet = $this->signedNet(
            DB::table('journal_entries')
                ->whereIn('account_id', $leafIds)
                ->whereNull('deleted_at')
                ->whereNull('type_reference_id')
                ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $periodEnd),
            $isDebitNormal
        );

        return $this->buildRow(
            key: 'control:'.$purposeCode,
            group: self::GROUP_CONTROL,
            label: $label,
            code: $purposeCode,
            accountIds: $leafIds,
            opening: $opening,
            periodDebit: $periodDebit,
            periodCredit: $periodCredit,
            book: $book,
            gap: $unattributedNet,
            companyId: $companyId,
            blocksClose: true,
        );
    }

    /** @return list<array> */
    private function clearingRows(int $companyId, \Illuminate\Support\Collection $byAccountId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $rows = [];

        foreach (config('accounting.period_close.clearing_rollforward_codes', []) as $code => $label) {
            // PHP casts a numeric-string array key ('2632') to a real int -- same gotcha
            // PeriodCloseChecklistService::checkClearingRollforward() already documents and
            // guards against; cast back to string for both the row key and the `code` column
            // (typed ?string) and the WHERE clause (accounts.code is a string column).
            $code = (string) $code;
            $account = Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();
            if ($account === null) {
                continue;
            }

            $tbRow = $byAccountId->get($account->id);
            $isDebitNormal = $tbRow !== null ? $this->isDebitNormal($tbRow->root_name) : true;
            $book = $tbRow !== null ? (float) $tbRow->closing_balance : 0.0;

            $unreconciledNet = $this->signedNet(
                DB::table('journal_entries')
                    ->where('account_id', $account->id)
                    ->whereNull('deleted_at')
                    ->where('reconciled', 0)
                    ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $periodEnd),
                $isDebitNormal
            );

            $rows[] = $this->buildRow(
                key: 'clearing:'.$code,
                group: self::GROUP_CLEARING,
                label: $label,
                code: $code,
                accountIds: [$account->id],
                opening: $book - $this->periodNet($tbRow, $isDebitNormal),
                periodDebit: $tbRow !== null ? (float) $tbRow->total_debit : 0.0,
                periodCredit: $tbRow !== null ? (float) $tbRow->total_credit : 0.0,
                book: $book,
                gap: $unreconciledNet,
                companyId: $companyId,
                blocksClose: false,
            );
        }

        return $rows;
    }

    /** @return list<array> */
    private function reviewOnlyRows(int $companyId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $priorLength = $periodStart->diffInDays($periodEnd) + 1;
        $priorStart = $periodStart->copy()->subDays($priorLength);
        $priorEnd = $periodStart->copy()->subDay()->endOfDay();

        $rows = [];
        foreach (['Income', 'Expenses'] as $rootName) {
            $thisPeriod = $this->rootMovement($companyId, $rootName, $periodStart, $periodEnd);
            $priorPeriod = $this->rootMovement($companyId, $rootName, $priorStart, $priorEnd);

            $rows[] = [
                'key' => 'review:'.$rootName,
                'group' => self::GROUP_REVIEW_ONLY,
                'label' => $rootName,
                'code' => null,
                'account_ids' => [],
                'opening_balance' => null,
                'period_debit' => null,
                'period_credit' => null,
                'book_balance' => $thisPeriod,
                'confirmed_balance' => null,
                'gap' => $thisPeriod - $priorPeriod,
                'status' => 'review_only',
                'blocks_close' => false,
                'counts' => ['proposals' => 0, 'unmatched' => 0, 'ageing_over' => 0],
            ];
        }

        return $rows;
    }

    private function rootMovement(int $companyId, string $rootName, Carbon $start, Carbon $end): float
    {
        $isDebitNormal = $rootName === 'Expenses';

        $totals = DB::table('journal_entries as je')
            ->join('accounts as a', 'je.account_id', '=', 'a.id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $companyId)
            ->where('root.name', $rootName)
            ->whereNull('je.deleted_at')
            ->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$start, $end])
            ->selectRaw('COALESCE(SUM(je.debit),0) as d, COALESCE(SUM(je.credit),0) as c')
            ->first();

        $debit = (float) $totals->d;
        $credit = (float) $totals->c;

        return $isDebitNormal ? $debit - $credit : $credit - $debit;
    }

    /** @param object|null $tbRow one row from TrialBalanceService::generate()['accounts'] */
    private function periodNet(?object $tbRow, bool $isDebitNormal): float
    {
        if ($tbRow === null) {
            return 0.0;
        }

        $d = (float) $tbRow->total_debit;
        $c = (float) $tbRow->total_credit;

        return $isDebitNormal ? $d - $c : $c - $d;
    }

    private function signedNet(\Illuminate\Database\Query\Builder $query, bool $isDebitNormal): float
    {
        $totals = $query->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();
        $d = (float) $totals->d;
        $c = (float) $totals->c;

        return $isDebitNormal ? $d - $c : $c - $d;
    }

    /** @param int[] $accountIds */
    private function buildRow(
        string $key,
        string $group,
        string $label,
        ?string $code,
        array $accountIds,
        float $opening,
        float $periodDebit,
        float $periodCredit,
        float $book,
        float $gap,
        int $companyId,
        bool $blocksClose,
    ): array {
        $confirmed = $book - $gap;
        $tolerance = $this->tolerance();
        $isZeroGap = abs($gap) <= $tolerance;

        $pendingProposals = ReconciliationProposal::query()
            ->forCompany($companyId)
            ->whereIn('account_id', $accountIds)
            ->pending()
            ->count();

        // P2.5.G verify fix: a line already covered by a pending proposal belongs to the
        // PROPOSALS count above, not the UNMATCHED count too — see
        // {@see self::pendingProposalBookLineIds()}'s own docblock for why (the same exclusion
        // {@see self::unmatchedFor()} applies, so the grid's own counts and the drill-down panel
        // agree on how many lines are in each bucket).
        $pendingLineIds = $this->pendingProposalBookLineIds($companyId, $accountIds);

        $unmatchedQuery = $group === self::GROUP_CONTROL
            ? JournalEntry::withoutGlobalScopes()->whereIn('account_id', $accountIds)->whereNull('deleted_at')->whereNull('type_reference_id')
            : JournalEntry::withoutGlobalScopes()->whereIn('account_id', $accountIds)->whereNull('deleted_at')->where('reconciled', 0)
                ->where(fn ($q) => $q->where('debit', '!=', 0)->orWhere('credit', '!=', 0));

        if ($pendingLineIds !== []) {
            $unmatchedQuery->whereNotIn('id', $pendingLineIds);
        }

        $unmatchedCount = (clone $unmatchedQuery)->count();
        $ageingCutoff = now()->subDays($this->ageingOverDays());
        $ageingOverCount = (clone $unmatchedQuery)
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<', $ageingCutoff)
            ->count();

        $status = match (true) {
            $isZeroGap => 'reconciled',
            $pendingProposals > 0 => 'proposals_pending',
            default => 'exceptions',
        };

        return [
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'code' => $code,
            'account_ids' => $accountIds,
            'opening_balance' => round($opening, 3),
            'period_debit' => round($periodDebit, 3),
            'period_credit' => round($periodCredit, 3),
            'book_balance' => round($book, 3),
            'confirmed_balance' => round($confirmed, 3),
            'gap' => round($gap, 3),
            'status' => $status,
            'blocks_close' => $blocksClose && ! $isZeroGap,
            'counts' => [
                'proposals' => $pendingProposals,
                'unmatched' => $unmatchedCount,
                'ageing_over' => $ageingOverCount,
            ],
        ];
    }

    // ── Drill-down: PROPOSALS / UNMATCHED / HISTORY / GAP EXPLANATION ──────────────────────────────

    /**
     * @param  int[]  $accountIds
     * @return \Illuminate\Support\Collection<int, ReconciliationProposal>
     */
    public function proposalsFor(int $companyId, array $accountIds, ?string $status = ReconciliationProposal::STATUS_PENDING): \Illuminate\Support\Collection
    {
        return ReconciliationProposal::query()
            ->forCompany($companyId)
            ->whereIn('account_id', $accountIds)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * P2.5.G verify fix (CONFIRMED bug): every `book_journal_entry_id` already covered by a PENDING
     * proposal — excluded from {@see self::unmatchedFor()} (and from {@see self::buildRow()}'s own
     * `counts.unmatched`) so a line is never counted in BOTH the PROPOSALS panel/component AND the
     * UNMATCHED panel/component at once. Before this fix {@see self::explainGap()} summed
     * "Proposals pending approval" (this same line's amount) AND "Unmatched items" (the identical
     * line, still `reconciled = 0` by construction while its proposal is pending) — double-
     * subtracting it from BOOK and reporting a false EXCEPTION with a misleading advice line on
     * every row that has any pending proposal, which is the NORMAL state for a system built around
     * nightly-generated proposals awaiting approval, not an edge case.
     *
     * @param  int[]  $accountIds
     * @return int[]
     */
    private function pendingProposalBookLineIds(int $companyId, array $accountIds): array
    {
        return ReconciliationProposal::query()
            ->forCompany($companyId)
            ->whereIn('account_id', $accountIds)
            ->pending()
            ->whereNotNull('book_journal_entry_id')
            ->pluck('book_journal_entry_id')
            ->all();
    }

    /**
     * P2.5.G verify fix (missing decomposition line): the owner refinement's fourth
     * gap-explanation component — "known timing differences (gateway settlement lag, cheques in
     * hand/issued not cleared)" — named here as two identifiable line SETS rather than left to
     * silently fall into the generic "unmatched" bucket:
     *
     *   - a cheque leg (`cheque_no` set) that has not yet cleared the bank (`cheque_clearance_date`
     *     is null, or is still in the future as of the grid's as-of date) — the SAME "has this
     *     cheque actually cleared" test {@see ReconciliationAutoMatchService::detectClearingRollforward()}
     *     already uses (in reverse: that detector proposes a match once clearance HAS passed; this
     *     one names the timing difference while it has NOT).
     *   - an unmatched line on a GATEWAY_CLEARING_* leaf still inside the ordinary gateway
     *     settlement window (`accounting.reconciliation.gateway_settlement_lag_days`, default 5) —
     *     a gateway settling a few days late is routine, not an exception; a gateway line OLDER
     *     than that window is left as a genuine unmatched/ageing item instead (still worth
     *     flagging), never silently absorbed into "timing".
     *
     * @param  int[]  $accountIds
     * @return int[]
     */
    private function timingDifferenceLineIds(int $companyId, array $accountIds, \DateTimeInterface $asOf): array
    {
        $asOfCarbon = $asOf instanceof Carbon ? $asOf->copy() : Carbon::parse($asOf->format('Y-m-d H:i:s'));
        $asOfDate = $asOfCarbon->toDateString();

        $chequeIds = JournalEntry::withoutGlobalScopes()
            ->whereIn('account_id', $accountIds)
            ->whereNull('deleted_at')
            ->where('reconciled', 0)
            ->whereNotNull('cheque_no')
            ->where(fn ($q) => $q->whereNull('cheque_clearance_date')->orWhere('cheque_clearance_date', '>', $asOfDate))
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $asOfCarbon)
            ->pluck('id')
            ->all();

        $gatewayLeafIds = array_values(array_intersect($accountIds, $this->gatewayLeafIds($companyId)));
        $gatewayIds = [];
        if ($gatewayLeafIds !== []) {
            $lagCutoff = $asOfCarbon->copy()->subDays($this->gatewaySettlementLagDays());
            $gatewayIds = JournalEntry::withoutGlobalScopes()
                ->whereIn('account_id', $gatewayLeafIds)
                ->whereNull('deleted_at')
                ->where('reconciled', 0)
                ->where(fn ($q) => $q->where('debit', '!=', 0)->orWhere('credit', '!=', 0))
                ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '>=', $lagCutoff)
                ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $asOfCarbon)
                ->pluck('id')
                ->all();
        }

        return array_values(array_unique(array_merge($chequeIds, $gatewayIds)));
    }

    /**
     * Unmatched items with ageing buckets — the second drill-down panel. Excludes any line already
     * covered by a pending proposal (see {@see self::pendingProposalBookLineIds()}'s docblock —
     * that line belongs to the PROPOSALS panel, not this one). A line that IS a known timing
     * difference ({@see self::timingDifferenceLineIds()}) still appears in `items` (nothing is
     * hidden from the operator) but is bucketed under `timing_cheque`/`timing_gateway` instead of
     * an ageing bucket, and its net is reported separately under those same two bucket keys — so
     * `array_sum($buckets)` still equals the full unmatched net, but a caller that wants "genuinely
     * unmatched, ageing" vs. "explained by a known timing difference" can tell the two apart
     * without double-counting either (see {@see self::explainGap()}).
     *
     * accounting-builds T9 (Wave 2): extended to return BOTH sides when
     * `$bankStatementImportId` is given (spec: "unreconciled report (both directions)") — adds a
     * `statement_items`/`statement_buckets` pair (book lines are `items`/`buckets`, exactly as
     * before; the STATEMENT side is `App\Models\BankStatementImportLine` rows for that one import
     * still in state `unmatched`/`disputed`, aged the SAME way against `$asOf` and the SAME
     * `config('accounting.reconciliation.ageing_over_days')` bucket boundaries the book side
     * already uses). Additive and OPT-IN: omitting the parameter (every pre-existing caller,
     * including {@see self::explainGap()}) returns EXACTLY today's book-only shape — no existing
     * caller's return-array shape changes.
     *
     * @param  int[]  $accountIds
     * @return array{items: list<array>, buckets: array<string,float>, statement_items?: list<array>, statement_buckets?: array<string,float>}
     */
    public function unmatchedFor(int $companyId, array $accountIds, string $group, \DateTimeInterface $asOf, ?int $bankStatementImportId = null): array
    {
        $query = $group === self::GROUP_CONTROL
            ? JournalEntry::withoutGlobalScopes()->whereIn('account_id', $accountIds)->whereNull('deleted_at')->whereNull('type_reference_id')
            : JournalEntry::withoutGlobalScopes()->whereIn('account_id', $accountIds)->whereNull('deleted_at')->where('reconciled', 0)
                ->where(fn ($q) => $q->where('debit', '!=', 0)->orWhere('credit', '!=', 0));

        $pendingLineIds = $this->pendingProposalBookLineIds($companyId, $accountIds);
        if ($pendingLineIds !== []) {
            $query->whereNotIn('id', $pendingLineIds);
        }

        $lines = $query
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $asOf)
            ->orderBy(DB::raw('COALESCE(posting_date, transaction_date)'))
            ->get();

        $timingIds = in_array($group, [self::GROUP_BANK_CASH, self::GROUP_CLEARING], true)
            ? $this->timingDifferenceLineIds($companyId, $accountIds, $asOf)
            : [];

        $asOfCarbon = $asOf instanceof Carbon ? $asOf : Carbon::parse($asOf->format('Y-m-d H:i:s'));
        $buckets = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'timing_cheque' => 0.0, 'timing_gateway' => 0.0];
        $items = [];

        foreach ($lines as $line) {
            $lineDate = Carbon::parse($line->posting_date ?? $line->transaction_date);
            $ageDays = $lineDate->diffInDays($asOfCarbon, false);
            $net = (float) $line->debit - (float) $line->credit;

            if (in_array($line->id, $timingIds, true)) {
                $bucket = $line->cheque_no !== null ? 'timing_cheque' : 'timing_gateway';
            } else {
                $bucket = match (true) {
                    $ageDays <= 30 => '0_30',
                    $ageDays <= 60 => '31_60',
                    $ageDays <= 90 => '61_90',
                    default => 'over_90',
                };
            }

            $buckets[$bucket] += $net;

            $items[] = [
                'id' => $line->id,
                'transaction_id' => $line->transaction_id,
                'date' => $lineDate->toDateString(),
                'age_days' => $ageDays,
                'ageing_bucket' => $bucket,
                'is_timing_difference' => str_starts_with($bucket, 'timing_'),
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'description' => $line->description,
                'voucher_number' => $line->voucher_number,
                'document_url' => \Illuminate\Support\Facades\Route::has('journal-entries.index')
                    ? route('journal-entries.index', $line->transaction_id) : null,
                'log_center_url' => AuditLogLinker::forSubject('journal_entry', (int) $line->id),
            ];
        }

        $result = ['items' => $items, 'buckets' => $buckets];

        if ($bankStatementImportId !== null) {
            [$result['statement_items'], $result['statement_buckets']] = $this->unmatchedStatementSideFor($bankStatementImportId, $asOfCarbon);
        }

        return $result;
    }

    /**
     * accounting-builds T9 (Wave 2). The STATEMENT side of {@see self::unmatchedFor()}'s "both
     * directions" extension — every {@see \App\Models\BankStatementImportLine} on one import still
     * in state `unmatched`/`disputed` (never `matched`), aged against `$asOf` with the SAME bucket
     * boundaries the book side uses.
     *
     * @return array{0: list<array>, 1: array<string,float>}
     */
    private function unmatchedStatementSideFor(int $bankStatementImportId, Carbon $asOfCarbon): array
    {
        $lines = \App\Models\BankStatementImportLine::where('import_id', $bankStatementImportId)
            ->whereIn('state', [
                \App\Models\BankStatementImportLine::STATE_UNMATCHED,
                \App\Models\BankStatementImportLine::STATE_DISPUTED,
            ])
            ->orderBy('row_no')
            ->get();

        $buckets = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        $items = [];

        foreach ($lines as $line) {
            $lineDate = Carbon::parse($line->value_date);
            $ageDays = $lineDate->diffInDays($asOfCarbon, false);
            $net = $line->amount();

            $bucket = match (true) {
                $ageDays <= 30 => '0_30',
                $ageDays <= 60 => '31_60',
                $ageDays <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucket] += $net;

            $items[] = [
                'id' => $line->id,
                'row_no' => $line->row_no,
                'date' => $lineDate->toDateString(),
                'age_days' => $ageDays,
                'ageing_bucket' => $bucket,
                'state' => $line->state,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'reference' => $line->reference,
                'auth_no' => $line->auth_no,
                'description' => $line->description,
                'note' => $line->note,
            ];
        }

        return [$items, $buckets];
    }

    /**
     * P2.5.F-sourced HISTORY drawer: "who approved/rejected/matched/unmatched what, when, reason."
     *
     * @param  int[]  $accountIds
     */
    public function historyFor(int $companyId, array $accountIds): \Illuminate\Support\Collection
    {
        $lineIds = JournalEntry::withoutGlobalScopes()->whereIn('account_id', $accountIds)->pluck('id');
        $proposalIds = ReconciliationProposal::forCompany($companyId)->whereIn('account_id', $accountIds)->pluck('id');

        return \App\Models\AccountingAuditLog::query()
            ->forCompany($companyId)
            ->where(function ($q) use ($lineIds, $proposalIds) {
                $q->where(fn ($q2) => $q2->where('subject_type', 'journal_entry')->whereIn('subject_id', $lineIds))
                    ->orWhere(fn ($q2) => $q2->where('subject_type', 'reconciliation_proposal')->whereIn('subject_id', $proposalIds));
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
    }

    /**
     * GAP EXPLANATION panel (owner refinement 2026-08-30): BOOK -> minus proposals pending ->
     * minus unmatched book items by ageing bucket -> +/- known timing differences -> = CONFIRMED;
     * the residual is flagged EXCEPTION with an ADVICE line. See class docblock for the BOOK/
     * CONFIRMED/GAP definitions this decomposes.
     *
     * P2.5.G verify fix (CONFIRMED double-counting bug): "proposals pending" and "unmatched items"
     * no longer overlap — {@see self::unmatchedFor()} now excludes any line already covered by a
     * pending proposal (see that method's own docblock for the exact bug this closes) — so summing
     * both components here no longer double-subtracts the same line from BOOK. The owner's fourth
     * decomposition line ("known timing differences — gateway settlement lag, cheques in
     * hand/issued not cleared") is now a real, separate component too, sourced from
     * {@see self::unmatchedFor()}'s own `timing_cheque`/`timing_gateway` buckets rather than being
     * silently folded into a generic "unmatched" bucket.
     *
     * @param  array<string,mixed>  $row  one row from {@see self::grid()}
     * @return array{book:float, confirmed:float, gap:float, components:list<array>, residual:float,
     *               exception:bool, advice:?array}
     */
    public function explainGap(int $companyId, array $row, \DateTimeInterface $asOf): array
    {
        $accountIds = $row['account_ids'];
        $group = $row['group'];

        $pendingProposals = $this->proposalsFor($companyId, $accountIds, ReconciliationProposal::STATUS_PENDING);
        // Approximation, documented: each pending proposal's own (unsigned) `amount` is the
        // magnitude of book-side movement it would confirm on approval — the exact SIGNED
        // contribution depends on which side of the line it sits on, which the advisory panel
        // does not need to get to the fils to be useful (it names a cause and a fix-now action,
        // it does not itself gate anything).
        $pendingAmount = (float) $pendingProposals->sum('amount');

        $unmatched = $this->unmatchedFor($companyId, $accountIds, $group, $asOf);
        $ageingBuckets = array_filter($unmatched['buckets'], fn (string $k): bool => ! str_starts_with($k, 'timing_'), ARRAY_FILTER_USE_KEY);
        $ageingTotal = array_sum($ageingBuckets);
        $timingNet = ($unmatched['buckets']['timing_cheque'] ?? 0.0) + ($unmatched['buckets']['timing_gateway'] ?? 0.0);

        $components = [
            ['label' => 'Proposals pending approval', 'amount' => round($pendingAmount, 3), 'would_confirm_on_approval' => true],
        ];
        foreach ($ageingBuckets as $bucket => $amount) {
            if (abs($amount) > $this->tolerance()) {
                $components[] = ['label' => 'Unmatched items ('.str_replace('_', '-', $bucket).'d)', 'amount' => round($amount, 3), 'would_confirm_on_approval' => false];
            }
        }
        if (abs($timingNet) > $this->tolerance()) {
            $components[] = [
                'label' => 'Known timing differences (gateway settlement lag; cheques in hand/issued not yet cleared)',
                'amount' => round($timingNet, 3),
                'would_confirm_on_approval' => false,
                'is_timing_difference' => true,
            ];
        }

        $explained = $pendingAmount + $ageingTotal + $timingNet;
        $residual = round($row['gap'] - $explained, 3);
        $exception = abs($residual) > $this->tolerance();

        return [
            'book' => $row['book_balance'],
            'confirmed' => $row['confirmed_balance'],
            'gap' => $row['gap'],
            'components' => $components,
            'residual' => $residual,
            'exception' => $exception,
            'advice' => $exception ? $this->adviseFor($group, $row, $residual, $unmatched) : null,
        ];
    }

    /**
     * Best-effort advisory heuristic (owner refinement 2026-08-30: "advisory text plus a one-click
     * DRAFT, never an auto-post"). This is intentionally a small, honest set of rules, not a
     * scored classifier — v0 scope names exactly these mechanisms (bank-charge PV, gateway-timing
     * JV, client overpay, duplicate receipt, stale open item).
     */
    private function adviseFor(string $group, array $row, float $residual, array $unmatched): array
    {
        if ($group === self::GROUP_BANK_CASH) {
            $isGateway = str_contains(strtolower((string) $row['label']), 'gateway') || str_contains(strtolower((string) $row['label']), 'clearing');
            if ($isGateway) {
                return [
                    'cause' => 'Likely gateway settlement timing (fees or a settlement lag not yet booked).',
                    'fix_now_kind' => \App\Models\ReconciliationFixDraft::KIND_GATEWAY_TIMING_JV,
                    'label' => 'Draft a gateway-timing JV to 5147',
                ];
            }

            return [
                'cause' => 'Likely an unrecorded bank charge or fee.',
                'fix_now_kind' => \App\Models\ReconciliationFixDraft::KIND_BANK_CHARGE_PV,
                'label' => 'Draft a bank-charge PV',
            ];
        }

        if ($group === self::GROUP_CONTROL) {
            if ($unmatched['buckets']['over_90'] !== 0.0 && abs($unmatched['buckets']['over_90']) >= abs($residual) * 0.5) {
                return [
                    'cause' => 'A stale open item over 90 days old accounts for most of the gap.',
                    'fix_now_kind' => \App\Models\ReconciliationFixDraft::KIND_WRITEOFF_PROPOSAL,
                    'label' => 'Draft a write-off proposal to 5218',
                ];
            }

            if ($residual > 0) {
                return [
                    'cause' => 'Possible duplicate or unapplied receipt inflating the balance.',
                    'fix_now_kind' => \App\Models\ReconciliationFixDraft::KIND_UNAPPLY_REAPPLY_RECEIPT,
                    'label' => 'Un-apply / re-apply the receipt',
                ];
            }

            return [
                'cause' => 'Client overpayment or credit not yet dispositioned.',
                'fix_now_kind' => null,
                'label' => 'Review client credit disposition',
            ];
        }

        return [
            'cause' => 'Clearing/roll-forward balance not fully explained by known items.',
            'fix_now_kind' => \App\Models\ReconciliationFixDraft::KIND_WRITEOFF_PROPOSAL,
            'label' => 'Consider a write-off proposal to 5218 if this balance is genuinely stale',
        ];
    }

    // ── Run-status panel ────────────────────────────────────────────────────────────────────────

    public function runStatus(int $companyId): array
    {
        $lastNightly = ReconciliationRun::forCompany($companyId)->where('trigger', ReconciliationRun::TRIGGER_NIGHTLY)->latest('id')->first();
        $lastAny = ReconciliationRun::forCompany($companyId)->latest('id')->first();

        return [
            'last_nightly_run' => $lastNightly?->only(['id', 'status', 'started_at', 'finished_at', 'proposals_created', 'auto_matched_pending', 'exceptions_count', 'duration_ms']),
            'last_run' => $lastAny?->only(['id', 'status', 'trigger', 'started_at', 'finished_at', 'proposals_created', 'auto_matched_pending', 'exceptions_count', 'duration_ms']),
        ];
    }

    /**
     * Resolves the accounting_periods status for a journal line's posting_date — used by
     * {@see ReconciliationProposalService::manualUnmatch()}'s "unmatch refused when the period is
     * locked" rule. A missing row means open (PeriodGuard's own "no row = open" convention).
     */
    public function periodStatusFor(int $companyId, \DateTimeInterface $date): string
    {
        $date = Carbon::instance($date);
        $isAnnual = (string) config('accounting.period.length', 'monthly') === 'annual';
        $month = $isAnnual ? AccountingPeriod::ANNUAL_MONTH : $date->month;

        $period = AccountingPeriod::where('company_id', $companyId)->where('year', $date->year)->where('month', $month)->first();

        return $period?->status ?? AccountingPeriod::STATUS_OPEN;
    }
}
