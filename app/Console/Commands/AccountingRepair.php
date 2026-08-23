<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\SystemLog;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * accounting:repair — balance unbalanced posting batches, append-only.
 *
 * Companion (write side) to the read-only accounting:verify. For every
 * transaction whose journal entries don't sum to zero (debits != credits), it
 * posts a SINGLE balancing entry into that same transaction, to a per-company
 * "Suspense / Adjustments" equity account. This:
 *   - never edits or deletes existing history (append-only),
 *   - makes each batch balance so the trial balance nets to zero,
 *   - quarantines the unexplained imbalance in ONE visible account for human
 *     review (it is flagged, not hidden and not fabricated as revenue/cost).
 *
 * DRY-RUN by default. Pass --commit to write. Idempotent: a transaction that
 * already carries a suspense adjustment is skipped, and once balanced it no
 * longer appears as unbalanced. Locked entries/periods are skipped.
 *
 * Built 2026-07-07 as the "repair" half of the COA hardening plan.
 */
class AccountingRepair extends Command
{
    protected $signature = 'accounting:repair
        {--company= : Limit to one company_id}
        {--commit : Actually post the balancing entries (default: dry-run)}
        {--limit=0 : Max transactions to repair (0 = no limit)}
        {--json : Machine-readable JSON output}';

    protected $description = 'Balance unbalanced posting batches by parking the imbalance in a Suspense account (append-only). Dry-run unless --commit.';

    /** KWD rounding tolerance (3 decimals) */
    private const TOL = 0.001;

    /** cache of company_id => suspense account id */
    private array $suspenseCache = [];

    public function handle(): int
    {
        $companyId = $this->option('company');
        $commit = (bool) $this->option('commit');
        $limit = (int) $this->option('limit');

        $companyFilter = $companyId ? ' AND company_id = ' . (int) $companyId . ' ' : '';

        // find unbalanced transactions (same definition as accounting:verify)
        $unbalanced = DB::select("
            SELECT transaction_id,
                   MAX(company_id)       AS company_id,
                   MAX(branch_id)        AS branch_id,
                   MIN(transaction_date) AS txdate,
                   MAX(is_locked)        AS any_locked,
                   ROUND(SUM(debit) - SUM(credit), 3) AS delta
            FROM journal_entries
            WHERE deleted_at IS NULL AND transaction_id IS NOT NULL {$companyFilter}
            GROUP BY transaction_id
            HAVING ABS(ROUND(SUM(debit) - SUM(credit), 3)) > " . self::TOL . "
            ORDER BY ABS(ROUND(SUM(debit) - SUM(credit), 3)) DESC");

        $report = [
            'generated_at' => now()->toDateTimeString(),
            'mode' => $commit ? 'COMMIT' : 'DRY-RUN',
            'scope' => $companyId ? "company {$companyId}" : 'all companies',
            'unbalanced_transactions' => count($unbalanced),
            'total_imbalance_kwd' => round(array_sum(array_map(fn ($r) => abs((float) $r->delta), $unbalanced)), 3),
            'repaired' => 0,
            'skipped_locked' => 0,
            'skipped_already' => 0,
            'errors' => 0,
            'by_company' => [],
        ];

        $processed = 0;
        foreach ($unbalanced as $row) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            $processed++;
            $cid = (int) $row->company_id;
            $delta = (float) $row->delta;
            $report['by_company'][$cid] = ($report['by_company'][$cid] ?? 0) + 1;

            if ((int) $row->any_locked === 1) {
                $report['skipped_locked']++;
                continue;
            }
            if (JournalEntry::where('transaction_id', $row->transaction_id)->where('type', 'suspense_adjustment')->exists()) {
                $report['skipped_already']++;
                continue;
            }

            if (!$commit) {
                $report['repaired']++; // would-repair count in dry-run
                continue;
            }

            try {
                DB::transaction(function () use ($row, $cid, $delta) {
                    $suspenseId = $this->suspenseAccountId($cid);
                    // delta = debits - credits. To balance, post the opposite:
                    //   delta > 0 (too much debit) -> post a CREDIT of delta
                    //   delta < 0 (too much credit) -> post a DEBIT of |delta|
                    $debit = $delta < 0 ? abs($delta) : 0;
                    $credit = $delta > 0 ? $delta : 0;
                    JournalEntry::create([
                        'transaction_id' => $row->transaction_id,
                        'company_id' => $cid,
                        'branch_id' => $row->branch_id,
                        'account_id' => $suspenseId,
                        'transaction_date' => $row->txdate,
                        'description' => 'accounting:repair — balancing adjustment (imbalance parked in Suspense for review)',
                        'name' => 'Suspense / Adjustments',
                        'debit' => $debit,
                        'credit' => $credit,
                        'balance' => 0,
                        'type' => 'suspense_adjustment',
                    ]);
                    try {
                        SystemLog::create([
                            'user_id' => null,
                            'model' => 'journal_entry',
                            'current_value' => 'unbalanced by ' . $delta,
                            'new_value' => 'balanced via suspense',
                            'remarks' => "accounting:repair balanced transaction #{$row->transaction_id} (delta {$delta}) into Suspense",
                        ]);
                    } catch (Throwable $ignore) {
                        // audit log is best-effort; never let it block the balancing entry
                    }
                });
                $report['repaired']++;
            } catch (Throwable $e) {
                $report['errors']++;
                Log::error('[accounting:repair] failed on transaction ' . $row->transaction_id . ': ' . $e->getMessage());
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->renderHuman($report);
        }

        Log::info('[accounting:repair] ' . $report['mode'], [
            'unbalanced' => $report['unbalanced_transactions'],
            'repaired' => $report['repaired'],
            'scope' => $report['scope'],
        ]);

        return self::SUCCESS;
    }

    /** Resolve (create once) the per-company Suspense / Adjustments equity account. */
    private function suspenseAccountId(int $companyId): int
    {
        if (isset($this->suspenseCache[$companyId])) {
            return $this->suspenseCache[$companyId];
        }
        $acc = Account::where('company_id', $companyId)->where('code', '3900')->first();
        if (!$acc) {
            $equityRoot = Account::where('company_id', $companyId)
                ->whereRaw('LOWER(name) LIKE ?', ['%equity%'])
                ->where(function ($q) {
                    $q->whereNull('parent_id')->orWhere('level', 1);
                })->first();
            $acc = Account::create([
                'name' => 'Suspense / Adjustments',
                'code' => '3900',
                'parent_id' => $equityRoot?->id,
                'root_id' => $equityRoot?->root_id ?? $equityRoot?->id,
                'company_id' => $companyId,
                'branch_id' => $equityRoot?->branch_id,
                'level' => $equityRoot ? ($equityRoot->level + 1) : 2,
                'account_type' => 'equity',
                'report_type' => 'balance sheet',
                'balance_must_be' => 'credit',
                'is_group' => 0,
                'disabled' => 0,
                'actual_balance' => 0.00,
                'opening_balance' => 0.00,
                'budget_balance' => 0.00,
                'variance' => 0.00,
                'currency' => 'KWD',
            ]);
        }
        return $this->suspenseCache[$companyId] = $acc->id;
    }

    private function renderHuman(array $r): void
    {
        $this->newLine();
        $this->line('==================================================');
        $this->line('   ACCOUNTING REPAIR — ' . $r['mode'] . '  (' . $r['scope'] . ')');
        $this->line('   ' . $r['generated_at']);
        $this->line('==================================================');
        $this->line("   Unbalanced batches found : {$r['unbalanced_transactions']}");
        $this->line("   Total imbalance          : {$r['total_imbalance_kwd']} KWD");
        $verb = $r['mode'] === 'COMMIT' ? 'Repaired (balanced)     ' : 'Would repair            ';
        $this->line("   {$verb} : {$r['repaired']}");
        $this->line("   Skipped (locked)         : {$r['skipped_locked']}");
        $this->line("   Skipped (already done)   : {$r['skipped_already']}");
        $this->line("   Errors                   : {$r['errors']}");
        foreach ($r['by_company'] as $cid => $n) {
            $this->line("      - company {$cid}: {$n} batches");
        }
        $this->newLine();
        if ($r['mode'] === 'DRY-RUN') {
            $this->warn('   DRY-RUN — nothing was written. Re-run with --commit to post balancing entries.');
        } else {
            $this->info('   Balancing entries posted to the Suspense / Adjustments account (append-only).');
            $this->line('   Review that account to investigate the parked imbalances.');
        }
        $this->line('==================================================');
    }
}
