<?php

namespace App\Services;

use App\Services\Accounting\ClosingDocuments;
use App\Services\Accounting\LedgerSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Profit & loss for a company over an arbitrary date range, computed on EXACTLY the
 * definitions the legacy-ledger parity harness and {@see TrialBalanceService} use.
 *
 * WHY THIS CLASS EXISTS (LP5a; see .planning/phases/legacy-ledger-pilot/LP5A-PL-SCREEN-2026-09-07.md)
 * ---------------------------------------------------------------------------------------------
 * `ReportController::profitLoss()` used to select and classify P&L accounts by the FIRST
 * CHARACTER OF THE ACCOUNT CODE -- `code LIKE '4%'` was rendered as "Incomes" and `code LIKE '5%'`
 * as "Expenses" -- on top of a `level = 3` selection rule. That assumes a positional chart of
 * accounts. Akeed's is not one: below the five fixed roots the codes are imported legacy codes.
 * On the run-#6 staging ledger the actual first characters of P&L leaf codes are `2`/`3`/`7`
 * (Income) and `2`/`4`/`5`/`7` (Expenses), so the prefix test put 106 EXPENSE subtrees into the
 * income column (credit-positive, hence a large negative "Total Income"), matched one movement-less
 * account for the expense column (rendering "Total Expenses" as 0.000), and dropped the entire
 * income side -- 1,206,174.379 KWD for FY2025 -- from the page. FY2025 rendered -775,264 against a
 * parity-verified +429,549.662. It was never a sign flip: a sign flip would have shown -429,549.662.
 *
 * THE DEFINITIONS, and why each one is the parity one
 * ---------------------------------------------------
 * - **Population: leaf accounts under the POSITIONAL roots `Income` / `Expenses`.** The root is
 *   resolved by `accounts.root_id` -> `root.name`, the same join
 *   {@see TrialBalanceService::getAccountBalances()} and
 *   {@see \App\Services\Onboarding\Parity\LedgerFigures::leafAccounts()} use, and the root names
 *   are the closed set {@see \App\Services\Accounting\AccountService}::FIXED_ROOT_NAMES enforces.
 *   Level-agnostic on purpose: on staging every Expenses leaf sits at level 3 but every Income leaf
 *   sits at level 4 or 5, so any level-based selection rule silently truncates one side or the other.
 *   The leaf test excludes soft-deleted children (LedgerFigures' variant), so a soft-deleted child
 *   does not turn its still-live parent into a permanent non-leaf whose balance nobody reports.
 * - **Date basis: `COALESCE(posting_date, transaction_date)`.** Same rationale as
 *   TrialBalanceService's own long note: `posting_date` is what PeriodGuard's shift resolves to, but
 *   it is nullable and only guaranteed for rows PostingService::post() wrote -- a bare
 *   `posting_date` filter would make every legacy-written row invisible to this report.
 * - **`deleted_at IS NULL` on the journal lines.** These are raw `DB::table` queries, so the model's
 *   SoftDeletes global scope does not apply and the predicate must be written out.
 * - **Whole-document exclusion of `doc_type = 'YEC'` and `sub_type = <opening journal>`.** A P&L run
 *   over an already-closed year would otherwise net its own trading activity to ~zero against the
 *   year-end close's zeroing lines; and an opening journal is an opening POSITION, never period
 *   movement (MAPPING-RULES §9.1, and LedgerFigures::periodMovement()'s identical `whereNotExists`).
 *   Excluded whole-document by `transaction_id`, not line-by-line, exactly as TrialBalanceService
 *   does -- a partially excluded balanced document unbalances the report.
 *   XBRL-X9r widens the two families: a REVERSAL of a YEC (`REV` / `sub_type = 'YEC'`, which the
 *   reopen-a-closed-year procedure posts) is excluded with the YEC it mirrors, or a reopened year
 *   would show its profit twice; and a prior-period adjustment (`PPA`, IAS 8, dated the first day
 *   of the year) and its reversal are excluded because they restate the OPENING position, never
 *   the current year's profit ({@see \App\Services\Accounting\ClosingDocuments}). PostingService
 *   already refuses a PPA with an Income or Expenses line; this exclusion is what keeps the P&L
 *   right for a PPA row that reached the ledger any other way.
 * - **Sign: `net income = income(Cr - Dr) - expense(Dr - Cr)`.** Income is credit-normal and expense
 *   debit-normal, so both sides come back POSITIVE for a normal trading period and the caller never
 *   needs `abs()`. Summed over all P&L leaves this is identically `Σ(credit - debit)`, which is the
 *   anchor's own convention (README-MANIFEST.md line 81, `NetIncome = Credit - Debit`) and therefore
 *   the figure {@see \App\Services\Onboarding\Parity\ParityHarness}'s `pl` check compares against.
 *
 * Read-only: this class never writes to `journal_entries` or `transactions`.
 */
class ProfitLossService
{
    private LedgerSource $ledgerSource;

    // AK-PORT F1 (CT-A6-2). Optional so every existing `new ProfitLossService` call site keeps
    // working unchanged; the container resolves the real one when nothing is passed. Same shape
    // TrialBalanceService and GeneralLedgerService take.
    public function __construct(?LedgerSource $ledgerSource = null)
    {
        $this->ledgerSource = $ledgerSource ?? app(LedgerSource::class);
    }

    /**
     * The two positional roots whose leaves constitute the profit & loss statement.
     *
     * @var list<string>
     */
    public const PROFIT_LOSS_ROOTS = ['Income', 'Expenses'];

    /**
     * Income and expense sections for [$from, $to], each grouped under its top-level section (the
     * account directly below the `Income` / `Expenses` root), plus the reconciling totals.
     *
     * @return array{
     *     income: list<array{account: object, amount: float, children: list<array{account: object, amount: float}>}>,
     *     expenses: list<array{account: object, amount: float, children: list<array{account: object, amount: float}>}>,
     *     totals: array{income: float, expense: float, net_income: float},
     *     period: array{from: string, to: string}
     * }
     */
    public function generate(int $companyId, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $leaves = $this->leafMovement($companyId, $from, $to);
        $sections = $this->sectionMap($companyId);

        $buckets = ['Income' => [], 'Expenses' => []];
        $totals = ['Income' => 0.0, 'Expenses' => 0.0];

        foreach ($leaves as $leaf) {
            $rootName = (string) $leaf->root_name;

            if (! array_key_exists($rootName, $buckets)) {
                continue;
            }

            // Income is credit-normal, Expenses debit-normal: both sides come back positive.
            $amount = $rootName === 'Income'
                ? (float) $leaf->total_credit - (float) $leaf->total_debit
                : (float) $leaf->total_debit - (float) $leaf->total_credit;

            $totals[$rootName] += $amount;

            $sectionId = $sections[(int) $leaf->id] ?? (int) $leaf->id;

            if (! isset($buckets[$rootName][$sectionId])) {
                $buckets[$rootName][$sectionId] = [
                    'account' => null,
                    'amount' => 0.0,
                    'children' => [],
                ];
            }

            $buckets[$rootName][$sectionId]['amount'] += $amount;

            if ($sectionId === (int) $leaf->id) {
                // The leaf IS its own section (a section with no children of its own).
                $buckets[$rootName][$sectionId]['account'] = $this->accountRow($leaf);

                continue;
            }

            if (abs($amount) >= 0.0005) {
                $buckets[$rootName][$sectionId]['children'][] = [
                    'account' => $this->accountRow($leaf),
                    'amount' => $amount,
                ];
            }
        }

        $income = $this->finaliseSections($companyId, $buckets['Income']);
        $expenses = $this->finaliseSections($companyId, $buckets['Expenses']);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'totals' => [
                'income' => $totals['Income'],
                'expense' => $totals['Expenses'],
                'net_income' => $totals['Income'] - $totals['Expenses'],
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }

    /**
     * Net income per calendar month of $year, keyed 1..12 (months with no activity are 0.0).
     *
     * One grouped query rather than twelve `generate()` calls: the yearly chart on the P&L screen
     * needs only the net figure per bucket, and bucketing happens on the SAME
     * `COALESCE(posting_date, transaction_date)` expression the figure itself is filtered on --
     * never `created_at`, which is when the row was inserted, not which period it belongs to
     * (P2.5.B / BUG-C4).
     *
     * @return array<int, float>
     */
    public function monthlyNetIncome(int $companyId, int $year): array
    {
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();

        $rows = $this->movementQuery($companyId, $from, $to)
            ->selectRaw('MONTH(COALESCE(je.posting_date, je.transaction_date)) AS month')
            ->selectRaw('COALESCE(SUM(je.credit), 0) - COALESCE(SUM(je.debit), 0) AS net_income')
            ->groupBy(DB::raw('MONTH(COALESCE(je.posting_date, je.transaction_date))'))
            ->get();

        $out = array_fill_keys(range(1, 12), 0.0);

        foreach ($rows as $row) {
            if ($row->month === null) {
                continue;
            }

            $out[(int) $row->month] = (float) $row->net_income;
        }

        return $out;
    }

    /**
     * Per-leaf Σ debit / Σ credit for the period, on the parity definitions.
     *
     * `leftJoin` on the journal lines (with every period predicate INSIDE the join) so that a leaf
     * with no activity still produces a row rather than vanishing -- the caller decides whether to
     * render a zero line; the totals are unaffected either way.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function leafMovement(int $companyId, Carbon $from, Carbon $to)
    {
        return $this->movementQuery($companyId, $from, $to)
            ->selectRaw('a.id, a.code, a.name, a.parent_id, a.level, root.name AS root_name')
            ->selectRaw('COALESCE(SUM(je.debit), 0) AS total_debit, COALESCE(SUM(je.credit), 0) AS total_credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.parent_id', 'a.level', 'root.name')
            ->orderBy('a.code')
            ->get();
    }

    /**
     * The shared FROM/WHERE of every figure this class produces. Every predicate here is a parity
     * definition -- see the class docblock for why each one is the way it is.
     */
    private function movementQuery(int $companyId, Carbon $from, Carbon $to): \Illuminate\Database\Query\Builder
    {
        $openingSubType = (string) config('accounting.reports.opening_journal_sub_type', 'LEGACY_OJV');

        return DB::table('accounts as a')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->leftJoin('journal_entries as je', function ($join) use ($companyId, $from, $to, $openingSubType) {
                $join->on('je.account_id', '=', 'a.id')
                    ->where('je.company_id', '=', $companyId)
                    ->whereNull('je.deleted_at')
                    ->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$from, $to])
                    ->whereNotExists(function ($sub) use ($openingSubType) {
                        $sub->selectRaw('1')
                            ->from('transactions as ex_t')
                            ->whereColumn('ex_t.id', 'je.transaction_id')
                            ->where('ex_t.sub_type', $openingSubType);
                    });

                // XBRL-X9r: the year-end close family (a YEC AND its reversal, the reopen
                // procedure's exact mirror) and the prior-period adjustment family (PPA: an
                // opening position, never current-year profit), from the one shared definition.
                ClosingDocuments::excludeYearEndCloseFamily($join, 'je.transaction_id');
                ClosingDocuments::excludePriorPeriodAdjustmentFamily($join, 'je.transaction_id');

                // AK-PORT F1 (CT-A6-2): one company reads engine rows OR legacy rows for its
                // period movement, never both -- the same restriction TrialBalanceService and
                // GeneralLedgerService now carry.
                //
                // This is not cosmetic symmetry. BalanceSheetService builds ONE statement from
                // TWO populations: assets/liabilities/equity via
                // TrialBalanceService::getOpeningBalances() and the equity net-profit line via
                // this class. With the trial balance restricted and the P&L not, the two halves
                // disagreed by exactly the legacy revenue and the sheet stopped footing --
                // measured on a fence at assets 130 / liabilities 100 / equity 90, difference
                // -60.000, is_balanced FALSE, on precisely the engine-ON-with-legacy-history
                // transition this restriction exists to serve.
                //
                // Applied INSIDE the join closure, never as an outer WHERE, so the leftJoin does
                // not degrade into an inner join and a zero-movement P&L leaf keeps its zero row.
                $this->ledgerSource->restrict($join, $companyId, 'je.transaction_id');
            })
            ->where('a.company_id', $companyId)
            ->whereNull('a.deleted_at')
            ->whereIn('root.name', self::PROFIT_LOSS_ROOTS)
            ->whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id AND child.deleted_at IS NULL)');
    }

    /**
     * Maps every account of the company to its SECTION -- the ancestor sitting directly below the
     * root (level 2 in a well-formed tree, but derived by walking `parent_id` rather than trusting
     * `level`, since `level` is denormalised). An account that is itself a root, or whose chain is
     * broken, maps to itself.
     *
     * @return array<int, int>
     */
    private function sectionMap(int $companyId): array
    {
        $accounts = DB::table('accounts')
            ->select('id', 'parent_id')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->get();

        $parents = [];

        foreach ($accounts as $account) {
            $parents[(int) $account->id] = $account->parent_id === null ? null : (int) $account->parent_id;
        }

        $sections = [];

        foreach (array_keys($parents) as $id) {
            $node = $id;
            $seen = [];

            // Climb until the node's PARENT is a root (parent has no parent of its own). The
            // `$seen` guard makes a cyclic parent_id chain -- which no valid tree has, but a
            // corrupted import could -- terminate instead of hanging the report.
            while (
                isset($parents[$node])
                && $parents[$node] !== null
                && isset($parents[$parents[$node]])
                && $parents[$parents[$node]] !== null
                && ! isset($seen[$node])
            ) {
                $seen[$node] = true;
                $node = $parents[$node];
            }

            $sections[$id] = $node;
        }

        return $sections;
    }

    /**
     * Fills in the section header accounts, drops sections with no movement, and orders by code.
     *
     * @param  array<int, array{account: object|null, amount: float, children: list<array{account: object, amount: float}>}>  $sections
     * @return list<array{account: object, amount: float, children: list<array{account: object, amount: float}>}>
     */
    private function finaliseSections(int $companyId, array $sections): array
    {
        $missing = [];

        foreach ($sections as $id => $section) {
            if ($section['account'] === null) {
                $missing[] = $id;
            }
        }

        if ($missing !== []) {
            $headers = DB::table('accounts')
                ->select('id', 'code', 'name')
                ->where('company_id', $companyId)
                ->whereIn('id', $missing)
                ->get()
                ->keyBy('id');

            foreach ($missing as $id) {
                $sections[$id]['account'] = $headers->get($id) ?? (object) [
                    'id' => $id,
                    'code' => '',
                    'name' => '(unknown account #'.$id.')',
                ];
            }
        }

        $out = [];

        foreach ($sections as $section) {
            if (abs($section['amount']) < 0.0005 && $section['children'] === []) {
                continue;
            }

            $out[] = $section;
        }

        usort($out, fn ($a, $b) => strcmp((string) $a['account']->code, (string) $b['account']->code));

        return $out;
    }

    /**
     * The minimal account shape the P&L view renders (id / code / name).
     */
    private function accountRow(object $leaf): object
    {
        return (object) [
            'id' => (int) $leaf->id,
            'code' => (string) $leaf->code,
            'name' => (string) $leaf->name,
        ];
    }
}
