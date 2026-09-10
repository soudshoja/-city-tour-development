<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Services\TrialBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CT-A6-4 — Balance Sheet ("Assets = Liabilities + Equity") as of a single date. Ported from
 * Akeed's `App\Services\BalanceSheetService` (LP6a; PR #76), adapted to this codebase's absence
 * of a standalone `ProfitLossService`: rather than re-deriving the level-3-account-tree grouping
 * `ReportController::profitLoss()` uses (a different, unrelated shape — see that method's own
 * P&L-2 comments), the current-period net-profit line below is computed by reusing
 * {@see TrialBalanceService::getAccountBalances()} scoped to the Income/Expenses roots for the
 * fiscal year to date — the SAME "one derivation, many readers" principle
 * {@see DeferredRevenueScheduleReport}'s own docblock already establishes for this codebase, and
 * the one that also gives this report {@see LedgerSource} restriction and the YEC exclusion for
 * free, rather than a third, independently-written (and independently bug-prone) net-income query.
 *
 * WHY THIS DEFINITION (mirrors the Akeed reference's own reasoning, adapted)
 * ---------------------------------------------------------------------------
 * - **Population: leaf accounts under the POSITIONAL roots `Assets` / `Liabilities` / `Equity`** —
 *   same `accounts.root_id -> root.name` join every other report in this codebase uses, never
 *   `report_type`/`level`. `Income`/`Expenses` leaves are excluded from the listing — they are
 *   temporary accounts that never appear as balance-sheet line items on their own, only their
 *   swept-or-unswept net profit does, as a single synthetic Equity line.
 * - **Per-account balance "as of $asOf": {@see TrialBalanceService::getOpeningBalances()} called
 *   with `dateFrom = $asOf + 1 day`.** That method sums every journal line strictly BEFORE its
 *   `dateFrom` argument with NO year-end-close exclusion — i.e. "all history through $asOf
 *   inclusive", YEC sweeps included — restricted to this company's current ledger source (engine
 *   XOR legacy; see {@see LedgerSource}). Reusing it means this report can never disagree with the
 *   trial balance about what a leaf's life-to-date balance is. Deliberately NOT
 *   `TrialBalanceService::generate()` with an epoch `dateFrom` — that method's MOVEMENT query
 *   excludes whole `doc_type='YEC'` documents, which is correct for a bounded period but would
 *   silently drop a year-end close's real, balance-affecting sweep from a balance-sheet figure
 *   spanning across it.
 * - **Un-closed-period net profit, from the day after the LAST year-end close on or before $asOf
 *   (else the beginning of the ledger) to $asOf**, shown as one synthetic Equity line — without
 *   it, Equity would be short by exactly the Income/Expense leaves' un-swept net for every period
 *   `YearEndCloseService` has not yet closed. CT-A56 R3-6 corrected this from the ported
 *   calendar-year window, which silently omitted every prior UNCLOSED year — and this ledger has
 *   no year-end close at all. See {@see self::unclosedPeriodStart()}.
 * - **Grouped by immediate parent, with subtotals** — same shape as
 *   {@see TrialBalanceService::groupByRootCategory()} one level deeper: it groups by root, this
 *   groups by the leaf's immediate parent, so multi-account sections ("Current Assets" / "Fixed
 *   Assets") roll up before the root total.
 *
 * Read-only: this class never writes to `journal_entries`, `transactions` or `accounts`.
 */
final class BalanceSheetService
{
    public const BALANCE_SHEET_ROOTS = ['Assets', 'Liabilities', 'Equity'];

    public function __construct(
        private readonly ?TrialBalanceService $trialBalance = null,
        private readonly ?LedgerSource $ledgerSource = null,
    ) {
    }

    private function trialBalance(): TrialBalanceService
    {
        return $this->trialBalance ?? app(TrialBalanceService::class);
    }

    private function ledgerSource(): LedgerSource
    {
        return $this->ledgerSource ?? app(LedgerSource::class);
    }

    /**
     * @return array{
     *     sections: array<string, array{root_name: string, groups: list<array{parent: object|null, accounts: list<object>, subtotal: float}>, total: float}>,
     *     net_profit: float,
     *     totals: array{assets: float, liabilities: float, equity: float, liabilities_and_equity: float, difference: float, is_balanced: bool},
     *     as_of: string,
     *     engine_on: bool
     * }
     */
    public function generate(int $companyId, Carbon $asOf): array
    {
        $asOf = $asOf->copy()->endOfDay();

        $accounts = $this->leafAccounts($companyId);
        $balances = $this->trialBalance()->getOpeningBalances($companyId, $asOf->copy()->addDay()->startOfDay());

        $parents = $this->parentNames($companyId);

        $sections = ['Assets' => [], 'Liabilities' => [], 'Equity' => []];
        $totals = ['Assets' => 0.0, 'Liabilities' => 0.0, 'Equity' => 0.0];

        foreach ($accounts as $account) {
            $rootName = (string) $account->root_name;

            if (! array_key_exists($rootName, $sections)) {
                continue;
            }

            $bal = $balances->get((int) $account->id, ['opening_debit' => 0.0, 'opening_credit' => 0.0]);
            $debit = (float) $bal['opening_debit'];
            $credit = (float) $bal['opening_credit'];

            // Assets are debit-normal; Liabilities/Equity are credit-normal — same rule
            // TrialBalanceService::getNormalBalance() uses.
            $balance = $rootName === 'Assets' ? $debit - $credit : $credit - $debit;

            if (abs($balance) < 0.0005) {
                continue;
            }

            $parentId = $account->parent_id !== null ? (int) $account->parent_id : null;
            $groupKey = $parentId ?? (int) $account->id;

            if (! isset($sections[$rootName][$groupKey])) {
                $sections[$rootName][$groupKey] = [
                    'parent' => $parentId !== null ? ($parents[$parentId] ?? null) : null,
                    'accounts' => [],
                    'subtotal' => 0.0,
                ];
            }

            $sections[$rootName][$groupKey]['accounts'][] = (object) [
                'id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'balance' => $balance,
            ];
            $sections[$rootName][$groupKey]['subtotal'] += $balance;
            $totals[$rootName] += $balance;
        }

        $netProfit = $this->netProfit($companyId, $this->unclosedPeriodStart($companyId, $asOf), $asOf);

        // Synthetic Equity line — no real account backs it, so it is never linkable to the
        // general ledger the way every other row on this screen is.
        $profitGroupKey = 'current_period_profit';
        $sections['Equity'][$profitGroupKey] = [
            'parent' => null,
            'accounts' => [(object) [
                'id' => null,
                'code' => '',
                'name' => 'Current Period Profit / (Loss)',
                'balance' => $netProfit,
            ]],
            'subtotal' => $netProfit,
        ];
        $totals['Equity'] += $netProfit;

        foreach ($sections as $rootName => $groups) {
            usort($sections[$rootName], fn ($a, $b) => strcmp(
                (string) ($a['parent']->code ?? ($a['accounts'][0]->code ?? '')),
                (string) ($b['parent']->code ?? ($b['accounts'][0]->code ?? ''))
            ));
            $sections[$rootName] = array_values($sections[$rootName]);

            foreach ($sections[$rootName] as &$group) {
                usort($group['accounts'], fn ($a, $b) => strcmp((string) $a->code, (string) $b->code));
            }
            unset($group);
        }

        $liabilitiesAndEquity = $totals['Liabilities'] + $totals['Equity'];
        $difference = round($totals['Assets'] - $liabilitiesAndEquity, 3);

        return [
            'sections' => [
                'Assets' => ['root_name' => 'Assets', 'groups' => $sections['Assets'], 'total' => $totals['Assets']],
                'Liabilities' => ['root_name' => 'Liabilities', 'groups' => $sections['Liabilities'], 'total' => $totals['Liabilities']],
                'Equity' => ['root_name' => 'Equity', 'groups' => $sections['Equity'], 'total' => $totals['Equity']],
            ],
            'net_profit' => $netProfit,
            'totals' => [
                'assets' => $totals['Assets'],
                'liabilities' => $totals['Liabilities'],
                'equity' => $totals['Equity'],
                'liabilities_and_equity' => $liabilitiesAndEquity,
                'difference' => $difference,
                'is_balanced' => abs($difference) < 0.001,
            ],
            'as_of' => $asOf->toDateString(),
            'engine_on' => $this->ledgerSource()->engineOn($companyId),
        ];
    }

    /**
     * Net income for [$from, $to] = Σ(Income leaves' normal-side balances) − Σ(Expenses leaves'
     * normal-side balances), read off {@see TrialBalanceService::getAccountBalances()}'s own
     * per-root subtotal_debit/subtotal_credit rather than re-deriving a parallel query — see class
     * docblock for why. Inherits that method's YEC exclusion and {@see LedgerSource} restriction
     * automatically.
     */
    /**
     * CT-A56 R3-6 — the day after the LAST year-end close on or before $asOf, else the beginning
     * of this company's ledger.
     *
     * The ported version used `Carbon::create($asOf->year, 1, 1)` — the calendar-year start — which
     * is only correct when every year before $asOf's has actually been CLOSED. A `doc_type='YEC'`
     * document is what moves a year's Income/Expense net into Retained Earnings, and
     * {@see TrialBalanceService::getOpeningBalances()} (which produces every real line on this
     * report) deliberately includes YEC lines, so a closed year IS already represented in the
     * Equity section. An UNCLOSED one is not — Income/Expenses roots are excluded from the listing
     * altogether — and it is not in a calendar-year-scoped profit line either. It is therefore
     * represented nowhere, and the sheet fails to foot by exactly that amount.
     *
     * The City Travelers ledger has never had a year-end close (CT-A1; the replay posts INV/RV/PV/
     * JV/CRN/DBN/OJV/REV and never a YEC) and spans several years, so on the real chart this was
     * not an edge case — it was every render. Independent verification R3 measured it on a two-year
     * fixture: assets 400.000 vs liabilities+equity 100.000, difference 300.000, exactly the prior
     * year's unswept profit.
     *
     * Starting from the day after the newest YEC keeps the two halves disjoint: the YEC's own
     * Retained-Earnings line is inside `getOpeningBalances()`, and everything posted after it is
     * inside this profit line. Neither double-counts the other.
     */
    private function unclosedPeriodStart(int $companyId, Carbon $asOf): Carbon
    {
        $lastClose = DB::table('transactions')
            ->where('company_id', $companyId)
            ->where('doc_type', 'YEC')
            ->whereNull('deleted_at')
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $asOf)
            ->max(DB::raw('COALESCE(posting_date, transaction_date)'));

        if ($lastClose !== null) {
            return Carbon::parse((string) $lastClose)->addDay()->startOfDay();
        }

        $earliest = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->min(DB::raw('COALESCE(posting_date, transaction_date)'));

        return $earliest !== null
            ? Carbon::parse((string) $earliest)->startOfDay()
            : Carbon::create($asOf->year, 1, 1)->startOfDay();
    }

    private function netProfit(int $companyId, Carbon $from, Carbon $to): float
    {
        $grouped = $this->trialBalance()->generate($companyId, $from, $to, ['show_zero' => true])['grouped'];

        $income = $grouped['Income'] ?? ['subtotal_debit' => 0.0, 'subtotal_credit' => 0.0];
        $expenses = $grouped['Expenses'] ?? ['subtotal_debit' => 0.0, 'subtotal_credit' => 0.0];

        $incomeNet = (float) $income['subtotal_credit'] - (float) $income['subtotal_debit'];
        $expenseNet = (float) $expenses['subtotal_debit'] - (float) $expenses['subtotal_credit'];

        return round($incomeNet - $expenseNet, 3);
    }

    /**
     * Leaf accounts (no children) under the three balance-sheet roots, for one company. Same leaf
     * test as {@see TrialBalanceService::getAccountBalances()}.
     *
     * @return Collection<int, object>
     */
    private function leafAccounts(int $companyId): Collection
    {
        return DB::table('accounts as a')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $companyId)
            ->whereIn('root.name', self::BALANCE_SHEET_ROOTS)
            ->whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)')
            ->selectRaw('a.id, a.code, a.name, a.parent_id, root.name as root_name')
            ->orderBy('a.code')
            ->get();
    }

    /**
     * @return array<int, object>
     */
    private function parentNames(int $companyId): array
    {
        return DB::table('accounts')
            ->where('company_id', $companyId)
            ->select('id', 'code', 'name')
            ->get()
            ->keyBy('id')
            ->all();
    }
}
