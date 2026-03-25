<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    public function generate(
        int $companyId,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $options = []
    ): array {
        $dateFrom = $dateFrom->startOfDay();
        $dateTo = $dateTo->endOfDay();

        $accounts = $this->getAccountBalances($companyId, $dateFrom, $dateTo, $options);
        $openingBalances = $this->getOpeningBalances($companyId, $dateFrom);

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $totalDebit += (float) $account->total_debit;
            $totalCredit += (float) $account->total_credit;

            if ($openingBalances->has($account->id)) {
                $account->opening_debit = (float) $openingBalances[$account->id]['opening_debit'];
                $account->opening_credit = (float) $openingBalances[$account->id]['opening_credit'];
            } else {
                $account->opening_debit = 0;
                $account->opening_credit = 0;
            }

            $normalBalance = $this->getNormalBalance($account);
            if ($normalBalance === 'debit') {
                $account->closing_balance = ($account->opening_debit - $account->opening_credit) + ($account->total_debit - $account->total_credit);
            } else {
                $account->closing_balance = ($account->opening_credit - $account->opening_debit) + ($account->total_credit - $account->total_debit);
            }
        }

        $grouped = $this->groupByRootCategory($accounts);

        $difference = abs($totalDebit - $totalCredit);

        return [
            'accounts' => $accounts,
            'grouped' => $grouped,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'difference' => $difference,
                'is_balanced' => $difference < 0.001,
            ],
            'opening_balances' => $openingBalances,
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
        ];
    }

    private function getAccountBalances(
        int $companyId,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $options
    ): Collection {
        $query = DB::table('accounts as a')
            ->selectRaw('
                a.id,
                a.code,
                a.name,
                a.root_id,
                a.parent_id,
                a.level,
                root.name AS root_name,
                COALESCE(SUM(je.debit), 0) AS total_debit,
                COALESCE(SUM(je.credit), 0) AS total_credit
            ')
            // Date filter inside JOIN so accounts with zero activity still appear
            ->leftJoin('journal_entries as je', function ($join) use ($dateFrom, $dateTo) {
                $join->on('je.account_id', '=', 'a.id')
                    ->whereNull('je.deleted_at');
                if ($dateFrom && $dateTo) {
                    $join->whereBetween('je.transaction_date', [$dateFrom, $dateTo]);
                }
            })
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $companyId)
            // Leaf accounts only (no children)
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM accounts child WHERE child.parent_id = a.id
            )');

        if (!empty($options['branch_id'])) {
            $query->where(function ($q) use ($options) {
                $q->where('a.branch_id', $options['branch_id'])
                    ->orWhereNull('a.branch_id');
            });
        }

        if (!empty($options['agent_id'])) {
            $query->where(function ($q) use ($options) {
                $q->where('a.agent_id', $options['agent_id'])
                    ->orWhereNull('a.agent_id');
            });
        }

        $accounts = $query->groupBy('a.id', 'a.code', 'a.name', 'a.root_id', 'a.parent_id', 'a.level', 'root.name')
            ->orderBy('a.code')
            ->get();

        if (empty($options['show_zero'])) {
            $accounts = $accounts->filter(function ($account) {
                return (float)$account->total_debit != 0 || (float)$account->total_credit != 0;
            })->values();
        }

        return $accounts;
    }

    /**
     * Sum all journal entries before $dateFrom for each leaf account.
     * Public so other services/reports can reuse this.
     */
    public function getOpeningBalances(
        int $companyId,
        Carbon $dateFrom
    ): Collection {
        $openingEntries = DB::table('accounts as a')
            ->selectRaw('
                a.id,
                COALESCE(SUM(je.debit), 0) AS opening_debit,
                COALESCE(SUM(je.credit), 0) AS opening_credit
            ')
            ->leftJoin('journal_entries as je', function ($join) use ($dateFrom) {
                $join->on('je.account_id', '=', 'a.id')
                    ->whereNull('je.deleted_at')
                    ->where('je.transaction_date', '<', $dateFrom);
            })
            ->where('a.company_id', $companyId)
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM accounts child WHERE child.parent_id = a.id
            )')
            ->groupBy('a.id')
            ->get();

        return $openingEntries->keyBy('id')->map(fn($item) => [
            'opening_debit' => (float) $item->opening_debit,
            'opening_credit' => (float) $item->opening_credit,
        ]);
    }

    private function groupByRootCategory(Collection $accounts): array
    {
        $grouped = [];

        foreach ($accounts as $account) {
            $rootName = $account->root_name;

            if (!isset($grouped[$rootName])) {
                $grouped[$rootName] = [
                    'root_name' => $rootName,
                    'accounts' => [],
                    'subtotal_debit' => 0,
                    'subtotal_credit' => 0,
                ];
            }

            $grouped[$rootName]['accounts'][] = $account;
            $grouped[$rootName]['subtotal_debit'] += (float) $account->total_debit;
            $grouped[$rootName]['subtotal_credit'] += (float) $account->total_credit;
        }

        $order = ['Assets', 'Liabilities', 'Equity', 'Income', 'Expenses'];
        $sorted = [];
        foreach ($order as $rootName) {
            if (isset($grouped[$rootName])) {
                $sorted[$rootName] = $grouped[$rootName];
            }
        }

        return $sorted;
    }

    public function findUnbalancedTransactions(
        int $companyId,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null
    ): Collection {
        $query = DB::table('transactions as t')
            ->selectRaw('
                t.id,
                t.name,
                t.reference_number,
                t.transaction_date,
                SUM(je.debit) as total_debit,
                SUM(je.credit) as total_credit,
                ABS(SUM(je.debit) - SUM(je.credit)) AS imbalance,
                (SUM(je.debit) - SUM(je.credit)) AS signed_imbalance
            ')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.transaction_id', '=', 't.id')
                    ->whereNull('je.deleted_at');
            })
            ->where('t.company_id', $companyId);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('t.transaction_date', [$dateFrom, $dateTo]);
        }

        return $query->groupBy('t.id', 't.name', 't.reference_number', 't.transaction_date')
            ->havingRaw('ABS(SUM(je.debit) - SUM(je.credit)) > 0.001')
            ->orderBy('t.transaction_date', 'desc')
            ->get();
    }

    /**
     * Assets & Expenses = 'debit'; Liabilities, Income, Equity = 'credit'
     */
    private function getNormalBalance(object $account): string
    {
        return in_array($account->root_name, ['Assets', 'Expenses']) ? 'debit' : 'credit';
    }

    public function formatCurrency(float $amount): string
    {
        return number_format($amount, 3, '.', ',');
    }
}
