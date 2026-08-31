<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountingAuditLog;
use App\Models\Company;
use App\Models\Setting;
use App\Support\CsvSafe;
use Illuminate\Console\Command;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F): "retention: company option audit_log_retention_months default
 * null, archival job only when set." A company whose option is unset (the default) is skipped
 * entirely — this command never deletes rows for a company that has not explicitly opted in.
 *
 * "Archival", not silent deletion: rows past the retention window are exported to a per-company
 * CSV under storage/app/{company}/accounting-audit-log-archive/ before being removed, via the raw
 * query builder (bypassing {@see AccountingAuditLog}'s append-only model guard, which — correctly —
 * refuses every `delete()` call a normal caller could make; this command is the one, explicit,
 * documented exception to that rule, run only when a company has opted in).
 *
 * P2.5.F fix-round (2026-08-30): the table's own DB-level append-only trigger
 * (`database/migrations/..._add_append_only_triggers_to_accounting_audit_log.php`) blocks every
 * DELETE unconditionally EXCEPT when the session-scoped MySQL user variable
 * `@accounting_audit_log_allow_delete` is set to 1 on the current connection — a gate that trigger
 * exists for, and that only this command ever sets. Set to 1 immediately before the delete below
 * and back to 0 immediately after, so the exception window is as narrow as the single statement it
 * exists for, on this command's own connection only (a MySQL user variable is per-session; no other
 * connection is ever affected). Never widen this to "set once at command start" — that would leave
 * every other delete on this same connection unintentionally unlocked for the command's whole run.
 */
class AccountingAuditLogPurge extends Command
{
    protected $signature = 'accounting:audit-log:purge {--company=} {--dry-run}';

    protected $description = 'Archive and remove accounting_audit_log rows past a company\'s configured retention window (opt-in only).';

    public function handle(): int
    {
        $companies = $this->option('company')
            ? Company::where('id', $this->option('company'))->get()
            : Company::all();

        $dryRun = (bool) $this->option('dry-run');
        $totalRemoved = 0;

        foreach ($companies as $company) {
            $months = Setting::getByKey((int) $company->id, 'accounting.audit_log_retention_months', null);

            if ($months === null || (int) $months <= 0) {
                continue; // default: keep forever, this company never opted in.
            }

            $cutoff = now()->subMonths((int) $months);

            $query = AccountingAuditLog::where('company_id', $company->id)->where('created_at', '<', $cutoff);
            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            if ($dryRun) {
                $this->info("Company {$company->id}: {$count} row(s) older than {$cutoff->toDateString()} would be archived.");

                continue;
            }

            $path = storage_path("app/{$company->id}/accounting-audit-log-archive/".now()->format('Ymd-His').'.csv');
            @mkdir(dirname($path), 0755, true);

            $handle = fopen($path, 'w');
            fputcsv($handle, ['id', 'created_at', 'action', 'actor_id', 'subject_type', 'subject_id', 'transaction_id', 'before', 'after', 'reason']);
            $query->orderBy('id')->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    // SEC-1: same CsvSafe neutralization as the Log Center's own exportCsv() —
                    // this archive writer feeds the same accounting_audit_log.reason (and now
                    // before/after JSON) into a CSV a human can open in a spreadsheet app.
                    fputcsv($handle, CsvSafe::row([
                        $row->id, $row->created_at, $row->action, $row->actor_id, $row->subject_type,
                        $row->subject_id, $row->transaction_id, json_encode($row->before), json_encode($row->after), $row->reason,
                    ]));
                }
            });
            fclose($handle);

            // Bypasses the model's append-only guard by design — see class docblock. Raw query
            // builder delete(), never Eloquent ::destroy()/->delete() on the model. The DB trigger
            // itself still blocks this delete unless the session variable below is set to 1 first
            // — see class docblock for why the window is opened and closed around this one call.
            \Illuminate\Support\Facades\DB::statement('SET @accounting_audit_log_allow_delete = 1');
            try {
                \Illuminate\Support\Facades\DB::table('accounting_audit_log')
                    ->where('company_id', $company->id)
                    ->where('created_at', '<', $cutoff)
                    ->delete();
            } finally {
                \Illuminate\Support\Facades\DB::statement('SET @accounting_audit_log_allow_delete = 0');
            }

            $totalRemoved += $count;
            $this->info("Company {$company->id}: archived {$count} row(s) to {$path}.");
        }

        $this->info("Done. {$totalRemoved} row(s) archived and removed.");

        return self::SUCCESS;
    }
}
