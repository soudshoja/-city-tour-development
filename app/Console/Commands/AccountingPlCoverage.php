<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * READ-ONLY diagnostic for the "P&L-2" gap.
 *
 * The Profit & Loss report (ReportController::profitLoss) only aggregates
 * accounts reached by this walk:
 *
 *     start:  accounts WHERE report_type = 'profit loss' AND level = 3
 *     plus:   every descendant of those level-3 nodes (via parent_id)
 *
 * Any income/expense journal entry posted to an account OUTSIDE that walk is
 * silently dropped from the P&L — even though it still sits in the trial
 * balance. That is why the P&L can fail to reconcile to the ledger.
 *
 * This command replicates the exact walk, then reports every P&L-nature
 * account that carries journal-entry activity but is NOT reached — with the
 * money involved and the reason it is missed — so the P&L-2 fix can be
 * designed from real data instead of guesswork.
 *
 * It runs only SELECTs (via Eloquent). It never writes, edits, or deletes.
 * Safe to run anytime, on any environment.
 *
 * Examples:
 *   php artisan accounting:pl-coverage
 *   php artisan accounting:pl-coverage --company=1
 *   php artisan accounting:pl-coverage --company=1 --from=2026-01-01 --to=2026-12-31
 *   php artisan accounting:pl-coverage --json
 */
class AccountingPlCoverage extends Command
{
    protected $signature = 'accounting:pl-coverage
        {--company= : Limit to one company_id (default: every company that has accounts)}
        {--from= : Start date YYYY-MM-DD for transaction_date (default: all time)}
        {--to= : End date YYYY-MM-DD for transaction_date (default: all time)}
        {--json : Output machine-readable JSON only}';

    protected $description = 'READ-ONLY: find income/expense journal entries the P&L report silently drops (P&L-2 diagnostic)';

    /** KWD rounding tolerance (3 decimals) */
    private const TOL = 0.001;

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $only = $this->option('company');

        $companyIds = Account::query()
            ->when($only, fn ($q) => $q->where('company_id', (int) $only))
            ->select('company_id')
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id')
            ->all();

        $report = [
            'generated_at' => now()->toDateTimeString(),
            'date_range' => ['from' => $from ?: 'all time', 'to' => $to ?: 'all time'],
            'companies' => [],
        ];

        foreach ($companyIds as $cid) {
            try {
                $report['companies'][] = $this->analyzeCompany((int) $cid, $from, $to);
            } catch (Throwable $e) {
                $report['companies'][] = ['company_id' => (int) $cid, 'error' => $e->getMessage()];
            }
        }

        // roll-up verdict
        $totalMissedAccounts = 0;
        $totalMissedRows = 0;
        foreach ($report['companies'] as $c) {
            $totalMissedAccounts += $c['missed_pl_account_count'] ?? 0;
            $totalMissedRows += $c['missed_je_rows'] ?? 0;
        }
        $report['verdict'] = $totalMissedRows > 0 ? 'P&L DROPS ENTRIES' : 'P&L COVERS ALL ENTRIES';

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderHuman($report);
        }

        Log::info('[accounting:pl-coverage] ' . $report['verdict'], [
            'missed_accounts' => $totalMissedAccounts,
            'missed_je_rows' => $totalMissedRows,
            'scope' => $only ? "company {$only}" : 'all companies',
        ]);

        return self::SUCCESS;
    }

    /**
     * Analyze one company: build the P&L walk set, then find income/expense
     * activity that falls outside it.
     */
    private function analyzeCompany(int $companyId, ?string $from, ?string $to): array
    {
        $accounts = Account::where('company_id', $companyId)->get();
        $byId = $accounts->keyBy('id');

        // ---- replicate ReportController::profitLoss's "relevant" account set ----
        // covered = level-3 profit-loss nodes + all their descendants.
        // Equivalent test per account: walking self+ancestors, do we hit a
        // level-3 account whose report_type = 'profit loss'?
        $coveredIds = [];
        foreach ($accounts as $a) {
            if ($this->isCovered($a, $byId)) {
                $coveredIds[$a->id] = true;
            }
        }

        // ---- journal-entry activity per account, in range, by transaction_date ----
        $activity = JournalEntry::where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->selectRaw('account_id, COUNT(*) as je_rows, ROUND(SUM(credit) - SUM(debit), 3) as net')
            ->groupBy('account_id')
            ->get();

        $missed = [];
        $orphanRows = 0;         // JEs whose account_id has no accounts row
        $missedIncome = 0.0;     // net credit-debit on missed income (code 4) accounts
        $missedExpense = 0.0;    // net credit-debit on missed expense (code 5) accounts
        $coveredIncome = 0.0;
        $coveredExpense = 0.0;

        foreach ($activity as $row) {
            $acct = $byId->get($row->account_id);
            $net = (float) $row->net;

            if (!$acct) {
                $orphanRows += (int) $row->je_rows;
                continue;
            }

            $code = (string) ($acct->code ?? '');
            $isIncomeByCode = str_starts_with($code, '4');
            $isExpenseByCode = str_starts_with($code, '5');
            $isPlByType = $acct->report_type === Account::REPORT_TYPES['PROFIT_LOSS'];
            $isBalanceSheet = $acct->report_type === Account::REPORT_TYPES['BALANCE_SHEET'];

            $isPlNature = $isPlByType || $isIncomeByCode || $isExpenseByCode;

            // Covered P&L accounts: tally so we can show the magnitude of the gap.
            if (isset($coveredIds[$acct->id])) {
                if ($isIncomeByCode || ($isPlByType && !$isExpenseByCode)) {
                    $coveredIncome += $net;
                } elseif ($isExpenseByCode) {
                    $coveredExpense += $net;
                }
                continue;
            }

            // Not covered. If it is a balance-sheet account (or clearly not P&L),
            // it is CORRECTLY excluded from the P&L — not a problem.
            if ($isBalanceSheet || !$isPlNature) {
                continue;
            }

            // Not covered AND P&L-nature => the P&L is dropping this money.
            [$l3Name, $l3Type] = $this->levelThreeAncestor($acct, $byId);

            if ($isIncomeByCode) {
                $missedIncome += $net;
            } elseif ($isExpenseByCode) {
                $missedExpense += $net;
            } elseif ($isPlByType) {
                // report_type says P&L but code is ambiguous; bucket by sign.
                if ($net >= 0) {
                    $missedIncome += $net;
                } else {
                    $missedExpense += $net;
                }
            }

            $missed[] = [
                'account_id' => $acct->id,
                'code' => $code,
                'name' => $acct->name,
                'level' => $acct->level !== null ? (int) $acct->level : null,
                'report_type' => $acct->report_type ?: '(none)',
                'level3_ancestor' => $l3Name,
                'level3_ancestor_report_type' => $l3Type,
                'je_rows' => (int) $row->je_rows,
                'net_kwd' => round($net, 3),
                'reason' => $this->missReason($acct, $l3Name, $l3Type),
            ];
        }

        // sort worst-first by absolute money dropped
        usort($missed, fn ($a, $b) => abs($b['net_kwd']) <=> abs($a['net_kwd']));

        return [
            'company_id' => $companyId,
            'company_name' => $byId->first()?->company?->name,
            'covered_pl_income_kwd' => round($coveredIncome, 3),
            'covered_pl_expense_kwd' => round(abs($coveredExpense), 3),
            'missed_pl_income_kwd' => round($missedIncome, 3),
            'missed_pl_expense_kwd' => round(abs($missedExpense), 3),
            'missed_pl_account_count' => count($missed),
            'missed_je_rows' => array_sum(array_column($missed, 'je_rows')),
            'orphan_je_rows' => $orphanRows,
            'missed_accounts' => $missed,
        ];
    }

    /**
     * Does this account sit inside the P&L walk?
     * True if the account itself, or any ancestor, is a level-3 account whose
     * report_type = 'profit loss'.
     */
    private function isCovered(Account $acct, $byId): bool
    {
        $cur = $acct;
        $guard = 0;
        while ($cur && $guard++ < 50) {
            if ((int) $cur->level === 3 && $cur->report_type === Account::REPORT_TYPES['PROFIT_LOSS']) {
                return true;
            }
            $cur = $cur->parent_id ? $byId->get($cur->parent_id) : null;
        }
        return false;
    }

    /**
     * Return [name, report_type] of the level-3 ancestor (regardless of its
     * report_type), or [null, null] if none exists on the chain.
     */
    private function levelThreeAncestor(Account $acct, $byId): array
    {
        $cur = $acct;
        $guard = 0;
        while ($cur && $guard++ < 50) {
            if ((int) $cur->level === 3) {
                return [$cur->name, $cur->report_type ?: '(none)'];
            }
            $cur = $cur->parent_id ? $byId->get($cur->parent_id) : null;
        }
        return [null, null];
    }

    private function missReason(Account $acct, ?string $l3Name, ?string $l3Type): string
    {
        $level = $acct->level !== null ? (int) $acct->level : null;

        if ($level !== null && $level < 3) {
            return "account is above level 3 (level {$level}); the walk only starts at level 3";
        }
        if ($l3Name === null) {
            return 'no level-3 ancestor on the parent chain (broken/short hierarchy)';
        }
        if ($l3Type !== Account::REPORT_TYPES['PROFIT_LOSS']) {
            return "level-3 ancestor '{$l3Name}' is not marked 'profit loss' (report_type='{$l3Type}')";
        }
        // A level-3 profit-loss ancestor exists but the account is still uncovered:
        // should not normally happen — flag for manual inspection.
        return 'uncovered despite a profit-loss level-3 ancestor — inspect hierarchy';
    }

    private function renderHuman(array $r): void
    {
        $this->newLine();
        $this->line('======================================================');
        $this->line('   P&L COVERAGE DIAGNOSTIC (P&L-2)');
        $this->line('   ' . $r['generated_at'] . '  |  range: ' . $r['date_range']['from'] . ' -> ' . $r['date_range']['to']);
        $this->line('======================================================');

        if ($r['verdict'] === 'P&L COVERS ALL ENTRIES') {
            $this->info('   VERDICT: P&L covers all income/expense entries — no gap found.');
        } else {
            $this->warn('   VERDICT: the P&L is DROPPING income/expense entries.');
        }
        $this->newLine();

        foreach ($r['companies'] as $c) {
            if (isset($c['error'])) {
                $this->error("company {$c['company_id']}: check failed — {$c['error']}");
                continue;
            }

            $label = "company {$c['company_id']}" . ($c['company_name'] ? " ({$c['company_name']})" : '');
            $this->line("  --- {$label} ---");
            $this->line("    covered in P&L : income {$c['covered_pl_income_kwd']} KWD | expense {$c['covered_pl_expense_kwd']} KWD");

            if ($c['missed_pl_account_count'] === 0 && $c['orphan_je_rows'] === 0) {
                $this->info('    no dropped P&L accounts.');
                $this->newLine();
                continue;
            }

            $this->warn("    DROPPED by P&L : income {$c['missed_pl_income_kwd']} KWD | expense {$c['missed_pl_expense_kwd']} KWD"
                . "  ({$c['missed_pl_account_count']} accounts, {$c['missed_je_rows']} entries)");
            if ($c['orphan_je_rows'] > 0) {
                $this->warn("    orphan entries (account_id has no accounts row): {$c['orphan_je_rows']}");
            }
            $this->newLine();

            $rows = array_map(fn ($m) => [
                $m['code'] ?: '-',
                $this->truncate($m['name'], 26),
                $m['level'] ?? '-',
                $m['report_type'],
                $m['je_rows'],
                number_format($m['net_kwd'], 3),
                $this->truncate($m['reason'], 46),
            ], array_slice($c['missed_accounts'], 0, 25));

            $this->table(
                ['Code', 'Account', 'Lvl', 'ReportType', 'Rows', 'Net KWD', 'Why dropped'],
                $rows
            );

            if ($c['missed_pl_account_count'] > 25) {
                $this->line('    (' . ($c['missed_pl_account_count'] - 25) . ' more — use --json for the full list)');
            }
            $this->newLine();
        }

        $this->line('   (This diagnostic only LOOKED at the books — it changed nothing.)');
        $this->line('======================================================');
    }

    private function truncate(string $s, int $len): string
    {
        return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . '…' : $s;
    }
}
