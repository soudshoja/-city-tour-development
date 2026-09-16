<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP4 -- the AKEED side of every parity comparison.
 *
 * ------------------------------------------------------------------------
 * WHY THIS CLASS EXISTS INSTEAD OF CALLING TrialBalanceService::generate()
 * ------------------------------------------------------------------------
 * The DEFINITIONS below are TrialBalanceService's, deliberately and
 * verbatim -- PLAN.md §2.2 rules that service "the parity source of truth".
 * What this class does NOT reuse is its RETURN SHAPE, for two reasons that
 * are both correctness issues, not preferences:
 *
 *  1. SIGN CONVENTION. TrialBalanceService::generate() folds every account
 *     into a normal-side-signed `closing_balance`
 *     (TrialBalanceService.php lines 41-46, via getNormalBalance() at the
 *     foot of that file: Assets & Expenses are debit-normal, Liabilities,
 *     Equity & Income credit-normal). The legacy anchors are NOT signed
 *     that way: README-MANIFEST.md line 51 defines
 *     `ClosingNet = OpeningNet + PeriodDr - PeriodCr` for EVERY account,
 *     debit-positive throughout. Comparing our normal-side-signed closing
 *     against their debit-positive ClosingNet would invert the sign of
 *     every liability, equity and income account -- ~790 of the 532-row
 *     closing anchor's mapped population -- and produce a wall of
 *     "2x the balance" deltas that look like a posting bug and are not.
 *     So: same numerator, their sign convention.
 *
 *  2. ZERO-ROW FILTERING. generate() drops accounts with no movement in
 *     the range unless `show_zero` is set
 *     (TrialBalanceService::getAccountBalances(), the `$options['show_zero']`
 *     filter) -- but an account with a non-zero OPENING and no 2025
 *     movement still has a non-zero ClosingNet in their anchor, and must be
 *     compared. Parity needs every leaf, always.
 *
 * Everything else is copied exactly, and any drift is a bug in THIS file:
 *
 *   - date basis `COALESCE(je.posting_date, je.transaction_date)`, never a
 *     bare posting_date (TrialBalanceService getAccountBalances/
 *     getOpeningBalances, P2.5.B / BUG-C4);
 *   - `whereNull('je.deleted_at')` on every journal-entry join;
 *   - leaf-only via `NOT EXISTS (SELECT 1 FROM accounts child WHERE
 *     child.parent_id = a.id)`;
 *   - whole-document exclusion of `doc_type = 'YEC'` from PERIOD movement
 *     and NOT from opening -- the asymmetry is deliberate in that service
 *     and is load-bearing here too (a year-end close's sweep must carry
 *     into the next year's opening, never count as its own year's trading).
 *
 * The one definition this class ADDS is the pilot-specific opening
 * exclusion from MAPPING-RULES §9.1: the replayed 2025 OJV is dated
 * 2025-01-01, i.e. INSIDE the period range, while their PeriodDr/PeriodCr
 * are "posted NON-OJV activity". Excluding `sub_type = LEGACY_OJV` from
 * period movement is what reproduces their definition; omitting it
 * double-counts the entire opening position and fails every account at
 * once. It is the single easiest way to get this wrong, which is why it is
 * a named, configured constant rather than a literal buried in a query. The
 * matching half -- putting that same opening journal INTO the opening
 * bucket even though it is dated inside the range -- is the OJV date trap
 * documented at openingNet() below, and is the one place where §9.1's own
 * wording is self-contradictory and must not be followed literally.
 *
 * READ-ONLY. This class never writes journal_entries, transactions or
 * accounts (tests/Feature/Accounting/ArchitectureTest.php's sole-writer
 * ratchet); the only rows LP4 writes are parity_run / parity_diff on the
 * quarantined legacy_pilot connection.
 */
final class LedgerFigures
{
    /**
     * Per-leaf-account figures on the anchors' own debit-positive
     * convention.
     *
     * @return array<int, array<string, mixed>> keyed by our account_id:
     *                                          account_id, code, name, root_name, opening_net, period_dr,
     *                                          period_cr, closing_net
     */
    public function perAccount(
        int $companyId,
        CarbonImmutable $periodStart,
        CarbonImmutable $asOf,
        string $openingSubType
    ): array {
        $opening = $this->openingNet($companyId, $periodStart, $asOf, $openingSubType);
        $period = $this->periodMovement($companyId, $periodStart, $asOf, $openingSubType);

        $out = [];

        foreach ($this->leafAccounts($companyId) as $account) {
            $id = (int) $account->id;
            $openingNet = (float) ($opening[$id] ?? 0.0);
            $dr = (float) ($period[$id]['debit'] ?? 0.0);
            $cr = (float) ($period[$id]['credit'] ?? 0.0);

            $out[$id] = [
                'account_id' => $id,
                'code' => (string) ($account->code ?? ''),
                'name' => (string) ($account->name ?? ''),
                'root_name' => (string) ($account->root_name ?? ''),
                'opening_net' => $openingNet,
                'period_dr' => $dr,
                'period_cr' => $cr,
                'closing_net' => $openingNet + $dr - $cr,
            ];
        }

        return $out;
    }

    /**
     * OpeningNet per leaf account, on the anchor's own definition.
     *
     * ------------------------------------------------------------------
     * THE OJV DATE TRAP -- read this before changing the predicate
     * ------------------------------------------------------------------
     * MAPPING-RULES §9.1 states OurOpening as
     * "COALESCE(posting_date, transaction_date) < 2025-01-01 ≡ the
     * replayed 2025 OJV alone", and then, four paragraphs later, that "the
     * 2025 OJV is dated 2025-01-01, i.e. *inside* the 2025 range". Both
     * cannot hold: a document dated 2025-01-01 is NOT strictly before
     * 2025-01-01. Taking the first sentence literally, while
     * periodMovement() correctly excludes sub_type=LEGACY_OJV from period
     * activity per the same section, makes the opening journal fall into
     * NEITHER bucket -- and the entire opening position, 205 accounts of
     * it, silently evaporates from every closing figure. The failure is
     * invisible in the code (both queries look right in isolation) and
     * catastrophic in the result.
     *
     * What both halves of §9.1 actually mean, and what their own anchor
     * definition says (README-MANIFEST.md line 49: "OpeningNet =
     * sum(Debit-Credit) of OJV DocYear=2025 lines"), is a PARTITION:
     * every line at or before the comparison date belongs to exactly one
     * of opening and period. So opening is
     *
     *     dated before period start   OR   an opening-journal document
     *                                      dated within the range
     *
     * and period is everything else in the range. Nothing is counted
     * twice; nothing is dropped. The partition property is what the
     * closing identity (opening + Dr - Cr) depends on, and it is asserted
     * directly by LegacyParityDefinitionsTest.
     *
     * doc_type='YEC' is excluded from BOTH buckets for an in-range close,
     * exactly as TrialBalanceService does: getAccountBalances() excludes
     * the whole YEC document from movement, and getOpeningBalances()
     * includes it only once it is genuinely in the past (i.e. for the
     * FOLLOWING year's opening), which the `< periodStart` limb preserves
     * here unchanged.
     *
     * @return array<int, float>
     */
    private function openingNet(int $companyId, CarbonImmutable $periodStart, CarbonImmutable $asOf, string $openingSubType): array
    {
        $rows = DB::table('journal_entries as je')
            ->selectRaw('je.account_id, COALESCE(SUM(je.debit), 0) - COALESCE(SUM(je.credit), 0) AS net')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $asOf->endOfDay())
            ->where(function ($q) use ($periodStart, $openingSubType) {
                $q->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<', $periodStart->startOfDay())
                    ->orWhereExists(function ($sub) use ($openingSubType) {
                        $sub->selectRaw('1')
                            ->from('transactions as ojv_t')
                            ->whereColumn('ojv_t.id', 'je.transaction_id')
                            ->where('ojv_t.sub_type', $openingSubType)
                            ->where(function ($inner) {
                                $inner->whereNull('ojv_t.doc_type')->orWhere('ojv_t.doc_type', '<>', 'YEC');
                            });
                    });
            })
            ->groupBy('je.account_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->account_id] = (float) $row->net;
        }

        return $out;
    }

    /**
     * Σ debit and Σ credit within [periodStart, asOf], per leaf account,
     * excluding whole YEC documents (TrialBalanceService) and whole
     * opening-journal documents (MAPPING-RULES §9.1).
     *
     * @return array<int, array{debit: float, credit: float}>
     */
    private function periodMovement(
        int $companyId,
        CarbonImmutable $periodStart,
        CarbonImmutable $asOf,
        string $openingSubType
    ): array {
        $rows = DB::table('journal_entries as je')
            ->selectRaw('je.account_id, COALESCE(SUM(je.debit), 0) AS total_debit, COALESCE(SUM(je.credit), 0) AS total_credit')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$periodStart->startOfDay(), $asOf->endOfDay()])
            ->whereNotExists(function ($sub) use ($openingSubType) {
                $sub->selectRaw('1')
                    ->from('transactions as ex_t')
                    ->whereColumn('ex_t.id', 'je.transaction_id')
                    ->where(function ($q) use ($openingSubType) {
                        $q->where('ex_t.doc_type', 'YEC')
                            ->orWhere('ex_t.sub_type', $openingSubType);
                    });
            })
            ->groupBy('je.account_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->account_id] = [
                'debit' => (float) $row->total_debit,
                'credit' => (float) $row->total_credit,
            ];
        }

        return $out;
    }

    /**
     * Every LEAF account of the company, with its root name -- the same
     * leaf test and the same `accounts as root` join
     * TrialBalanceService::getAccountBalances() uses, minus the zero-row
     * filter (see the class docblock).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function leafAccounts(int $companyId)
    {
        return DB::table('accounts as a')
            ->selectRaw('a.id, a.code, a.name, root.name AS root_name')
            ->leftJoin('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $companyId)
            ->whereNull('a.deleted_at')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id AND child.deleted_at IS NULL)')
            ->orderBy('a.code')
            ->get();
    }

    /**
     * Per-account P&L movement on the anchor's CREDIT-positive convention
     * (README-MANIFEST.md line 81: `NetIncome = Credit - Debit`), over
     * posted non-OJV 2025 activity. Same exclusions as periodMovement().
     *
     * @return array<int, array{debit: float, credit: float, net_income: float}>
     */
    public function profitAndLoss(
        int $companyId,
        CarbonImmutable $periodStart,
        CarbonImmutable $asOf,
        string $openingSubType
    ): array {
        $out = [];

        foreach ($this->periodMovement($companyId, $periodStart, $asOf, $openingSubType) as $accountId => $movement) {
            $out[$accountId] = [
                'debit' => $movement['debit'],
                'credit' => $movement['credit'],
                'net_income' => $movement['credit'] - $movement['debit'],
            ];
        }

        return $out;
    }

    /**
     * Decomposes a pooled control account into per-party positions.
     *
     * MAPPING-RULES §9.3: a pooled party line carries the party id on
     * `journal_entries.type_reference_id` (the draft's `partyAccountRef`).
     * The position is cumulative THROUGH $asOf -- "opening OJV-2025 +
     * activity through the date" -- so unlike the trial balance's period
     * movement this deliberately does NOT exclude the opening journal: the
     * opening IS part of a party's closing position.
     *
     * `undecomposable` is not a rounding detail. §9.3: "A party position
     * that cannot be decomposed (a pooled line with a NULL
     * type_reference_id) is a hard failure of §1.2 (b)'s party-required
     * rule and should not exist". It is returned rather than thrown so the
     * report can NAME the offending journal entries instead of aborting
     * with a stack trace.
     *
     * @param  list<int>  $controlAccountIds
     * @return array{positions: array<int, float>, undecomposable: list<array<string, mixed>>}
     */
    public function partyPositions(int $companyId, array $controlAccountIds, CarbonImmutable $asOf): array
    {
        if ($controlAccountIds === []) {
            return ['positions' => [], 'undecomposable' => []];
        }

        $rows = DB::table('journal_entries as je')
            ->selectRaw('je.type_reference_id, COALESCE(SUM(je.debit), 0) - COALESCE(SUM(je.credit), 0) AS net')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereIn('je.account_id', $controlAccountIds)
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $asOf->endOfDay())
            ->whereNotNull('je.type_reference_id')
            ->groupBy('je.type_reference_id')
            ->get();

        $positions = [];

        foreach ($rows as $row) {
            $positions[(int) $row->type_reference_id] = (float) $row->net;
        }

        $orphans = DB::table('journal_entries as je')
            ->selectRaw('je.id, je.account_id, je.transaction_id, je.debit, je.credit')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereIn('je.account_id', $controlAccountIds)
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $asOf->endOfDay())
            ->whereNull('je.type_reference_id')
            ->orderBy('je.id')
            ->limit(200)
            ->get();

        return [
            'positions' => $positions,
            'undecomposable' => $orphans->map(fn ($row) => [
                'journal_entry_id' => (int) $row->id,
                'account_id' => (int) $row->account_id,
                'transaction_id' => $row->transaction_id === null ? null : (int) $row->transaction_id,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ])->all(),
        ];
    }

    /**
     * LP4 check 11 (MAPPING-RULES §9.4): `posting_date = transaction_date`
     * on 100 % of legacy-fed lines.
     *
     * WHY THIS IS A MONEY CHECK AND NOT HOUSEKEEPING. PeriodGuard may shift
     * a document dated in a closed period into a later open one, and
     * TrialBalanceService buckets every balance by
     * COALESCE(posting_date, transaction_date). A silent shift therefore
     * moves a legacy document's amounts out of the month -- and, at a year
     * boundary, out of the YEAR -- their anchor put it in, producing a
     * parity failure whose cause is invisible in the amounts themselves
     * (MAPPING-RULES §6, PLAN.md §5.1 R-list). A shift of ONE 2025-12-31
     * document into 2026 breaks the closing anchor for both its accounts
     * while every individual line remains perfectly correct.
     *
     * A NULL posting_date counts as a violation here, not as a pass:
     * PostingService populates it on every document it posts, so a legacy-
     * fed line without one did not come through the seam.
     *
     * @return array{total: int, shifted: int, samples: list<array<string, mixed>>}
     */
    public function postingDateIntegrity(int $companyId, string $legacySubTypePrefix): array
    {
        $base = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereNull('t.deleted_at')
            ->where('t.sub_type', 'like', $legacySubTypePrefix.'%');

        $total = (clone $base)->count();

        $shiftedQuery = (clone $base)->where(function ($q) {
            $q->whereNull('je.posting_date')
                ->orWhereRaw('DATE(je.posting_date) <> DATE(je.transaction_date)');
        });

        $shifted = (clone $shiftedQuery)->count();

        $samples = (clone $shiftedQuery)
            ->selectRaw('je.id, je.account_id, je.transaction_id, t.sub_type, t.reference_number, je.transaction_date, je.posting_date')
            ->orderBy('je.id')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'journal_entry_id' => (int) $row->id,
                'account_id' => (int) $row->account_id,
                'transaction_id' => $row->transaction_id === null ? null : (int) $row->transaction_id,
                'sub_type' => (string) $row->sub_type,
                'reference_number' => $row->reference_number === null ? null : (string) $row->reference_number,
                'transaction_date' => $row->transaction_date === null ? null : (string) $row->transaction_date,
                'posting_date' => $row->posting_date === null ? null : (string) $row->posting_date,
            ])->all();

        return ['total' => $total, 'shifted' => $shifted, 'samples' => $samples];
    }

    /**
     * Journal lines on one of our accounts, for the drill-down. Ordered by
     * date then id so a diff of two runs is stable.
     *
     * @return list<array<string, mixed>>
     */
    public function linesForAccount(int $companyId, int $accountId, CarbonImmutable $asOf, int $limit = 500): array
    {
        return DB::table('journal_entries as je')
            ->leftJoin('transactions as t', 't.id', '=', 'je.transaction_id')
            ->selectRaw('je.id, je.transaction_id, je.type_reference_id, je.debit, je.credit, je.transaction_date, je.posting_date, t.sub_type, t.doc_type, t.reference_number, t.idempotency_key')
            ->where('je.company_id', $companyId)
            ->where('je.account_id', $accountId)
            ->whereNull('je.deleted_at')
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $asOf->endOfDay())
            ->orderByRaw('COALESCE(je.posting_date, je.transaction_date)')
            ->orderBy('je.id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'journal_entry_id' => (int) $row->id,
                'transaction_id' => $row->transaction_id === null ? null : (int) $row->transaction_id,
                'party_id' => $row->type_reference_id === null ? null : (int) $row->type_reference_id,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
                'transaction_date' => $row->transaction_date === null ? null : (string) $row->transaction_date,
                'posting_date' => $row->posting_date === null ? null : (string) $row->posting_date,
                'sub_type' => $row->sub_type === null ? null : (string) $row->sub_type,
                'doc_type' => $row->doc_type === null ? null : (string) $row->doc_type,
                'reference_number' => $row->reference_number === null ? null : (string) $row->reference_number,
                'idempotency_key' => $row->idempotency_key === null ? null : (string) $row->idempotency_key,
            ])->all();
    }
}
