<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\Scope\LegacyCompanyGuard;
use App\Services\Onboarding\Scope\LegacyIdBandGuard;
use App\Services\Onboarding\Scope\LegacyLoadScope;
use App\Services\Onboarding\Scope\LegacyRowLedger;
use App\Services\Onboarding\Scope\LegacySandboxGuard;
use App\Services\Onboarding\Scope\LegacyScopeRefused;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CD-PORT — the reversal. `CD0-REVERSAL-MANIFEST-2026-09-16.md` §3, executable and corrected.
 *
 * ── ROUND 2: this command used to delete City Travelers rows and report success ─────────────────
 * Round 1 implemented the manifest literally: `DELETE … WHERE id BETWEEN <floor> AND <ceiling>`,
 * with a `company_id` predicate added only where the table had that column. Adversarial
 * verification proved that unsound and reproduced it twice on a fence:
 *
 *     legacy:unload --company=10000001 --apply
 *       -> "5 row(s) deleted across 5 table(s); 0 residual rows in the band."
 *          FOUR of the five were City Travelers' — a user, a supplier, an agent, a queued job.
 *
 *     legacy:unload --company=99999999 --apply       (a company that never existed, never loaded)
 *       -> "1 row(s) deleted … 0 residual" — a City Travelers supplier, gone.
 *
 * Three separate defects produced that, and all three are fixed here:
 *
 *   1. **The band is not ownership, and the port itself made it a trap.** `legacy:scope --apply`
 *      raises `AUTO_INCREMENT` to the floor on `users`, `agents` and `suppliers`, so from that
 *      moment every row the dev application (and `test.citycommerce.group`, the second app on the
 *      same schema) mints there lands INSIDE the band — and it fills continuously across the days
 *      the deploy plan leaves between arming and unloading. **Deletion is now driven entirely by
 *      {@see LegacyRowLedger}**, which records per-table ownership from explicit attribution rules
 *      (`companies.user_id`, `branches.company_id`, `supplier_companies.company_id`, …). No
 *      statement in this command carries an id-range predicate any more.
 *   2. **There was no company gate.** Only `assertNotProtected()` against 1/2/3, which any typo
 *      clears. It now calls {@see LegacyCompanyGuard::assertTargetCompany()} — exists, in band,
 *      not protected — AND refuses a company with no `ct_scope_run` row on this database. A
 *      company that never loaded can never reach a DELETE.
 *   3. **Rows in the band that are not ours were invisible.** `fingerprintOutsideBand()` computes
 *      over `id NOT BETWEEN`, so a City Travelers row *inside* the band was excluded from the
 *      "untouched" proof by construction — the evidence could not see the defect. Those rows are
 *      now enumerated by name and id, and refused by default.
 *
 * `--dry-run` is the DEFAULT; `--apply` must be typed.
 */
class LegacyUnloadCommand extends Command
{
    protected $signature = 'legacy:unload
                            {--company= : The company whose legacy load is being reversed (required)}
                            {--apply : Actually delete. Without this the command counts and reports, and writes nothing}
                            {--keep-counters : Delete the rows but leave every AUTO_INCREMENT where it is}
                            {--purge-audit-log : Also remove this load\'s accounting_audit_log rows by setting the append-only table\'s own documented escape-hatch session variable. Off by default — the append-only invariant belongs to City Travelers, not to this lane}
                            {--allow-unowned-in-band : Proceed although rows this load does NOT own are sitting inside the reserved band. They are still never deleted; this only downgrades the refusal to a warning, for a live database where the dev application mints into those tables continuously}';

    protected $description = 'CD-PORT — reverse a legacy load: delete exactly the rows the load owns (per the ct_scope_row ledger, never by id range), restore each table\'s recorded pre-load AUTO_INCREMENT, and report the counts.';

    /**
     * Delete order: children before parents, so no FK is ever left dangling and
     * `FOREIGN_KEY_CHECKS` never has to be turned off. Derived from the FK graph measured on the
     * fence. `accounts` is self-referencing via `parent_id` and is handled in
     * {@see self::deleteAccountsTree()}.
     */
    private const EXPLICIT_ORDER_HEAD = [
        'journal_entries',
        'transactions',
        'coa_linkage_changes',
        'coa_linkage_findings',
        'system_accounts',
        'serial_schemas',
        'accounting_periods',
        'cost_centers',
        'charges',
        'company_gds_pccs',
        'settings',
        'sequences',
        'clients',
        'supplier_companies',
        'suppliers',
        'agents',
    ];

    /** Deleted last, in this order, because everything else points at them. */
    private const EXPLICIT_ORDER_TAIL = [
        'accounts',
        'roles',
        'branches',
        'companies',
        'users',
    ];

    public function handle(LegacyIdBandGuard $band, LegacyRowLedger $ledger, LegacyCompanyGuard $company): int
    {
        $companyOption = $this->option('company');

        if ($companyOption === null || $companyOption === '') {
            $this->error('Refused: --company is required. There is deliberately no default.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        try {
            // ── THE SANDBOX GATE ─────────────────────────────────────────────────────────────
            // This is the only command in the pipeline that deletes anything, so it is the one
            // the gate matters most for. Read LegacySandboxGuard's class docblock: this command's
            // row attribution is NOT safe on a database shared with another live company, and the
            // gate is what enforces that rather than a comment nobody reads.
            app(LegacySandboxGuard::class)->assertSandbox();

            LegacyPathGuard::assertQuarantinedConnection();
            $scope = LegacyLoadScope::forCompany((int) $companyOption);

            // Gate 1 — the same gate every write command passes: not protected, exists, and its
            // own id is inside the band. Round 1 had none of this and would delete for a company
            // id that had never existed.
            $company->assertTargetCompany($scope);

            // Gate 2 — this database must actually carry a load for this company. `ct_scope_run`
            // is written by `legacy:scope --apply`; without a row there, nothing armed a band for
            // this company here and there is nothing of ours to reverse.
            $this->assertLoadExists($scope);
        } catch (LegacyScopeRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Re-derive ownership before reading it, so a load that added rows after the last write
        // command is still fully covered. Derivational and idempotent — see LegacyRowLedger.
        $ledger->claim($scope);

        $owned = $ledger->allOwned($scope);
        $unowned = $ledger->unownedInBand($scope);

        $rows = [];

        foreach ($this->deleteOrder($scope) as $table) {
            $rows[] = [$table, count($owned[$table] ?? []), 'ct_scope_row ledger'];
        }

        foreach ($this->pivotCounts($scope, $ledger) as $table => [$n, $predicate]) {
            $rows[] = [$table, $n, $predicate];
        }

        $this->table(['table', 'rows OWNED by this load', 'how ownership was decided'], $rows);

        if ($unowned !== []) {
            $total = array_sum(array_map('count', $unowned));

            $lines = [];

            foreach ($unowned as $table => $ids) {
                $shown = array_slice($ids, 0, 10);
                $lines[] = $table.' ['.implode(', ', $shown).(count($ids) > 10 ? ', … '.(count($ids) - 10).' more' : '').']';
            }

            $message =
                'There are '.$total.' row(s) inside the reserved band '.$scope->bandDescription().
                ' that this load does NOT own: '.implode('; ', $lines).'. These are almost certainly '.
                "City Travelers' own rows — the dev application mints into `users`, `agents` and ".
                '`suppliers` continuously, and `legacy:scope --apply` raised those counters into the '.
                'band. They are NEVER deleted by this command. ';

            if (! $this->option('allow-unowned-in-band')) {
                $this->error(
                    'Refused: '.$message.'Re-run with --allow-unowned-in-band to proceed; the '.
                    'reversal will still touch only the rows in the ct_scope_row ledger.'
                );

                return self::FAILURE;
            }

            $this->warn($message.'--allow-unowned-in-band was given; proceeding.');
        }

        $totalOwned = array_sum(array_map('count', $owned));

        if (! $apply) {
            $this->warn(
                "DRY RUN — nothing was deleted. {$totalOwned} row(s) are owned by company ".
                "{$scope->companyId} on this database. Re-run with --apply."
            );

            return self::SUCCESS;
        }

        try {
            $deleted = $this->deleteAll($scope, $band, $ledger, $owned);
        } catch (LegacyScopeRefused $e) {
            // Thrown from INSIDE the delete transaction by the post-condition check below, so
            // everything this reversal did has already been rolled back by the time this runs.
            $this->error('POST-CONDITION FAILED, REVERSAL ROLLED BACK: '.$e->getMessage());

            return self::FAILURE;
        }

        // Post-condition: not one owned row may remain. Deliberately re-derived from the LEDGER,
        // not from the band — "0 residual rows in the band" was round 1's headline and it was true
        // while the command was deleting somebody else's rows.
        $residual = [];

        foreach ($owned as $table => $ids) {
            if ($this->isExempt($table) || ! $band->tableExists($table) || $ids === []) {
                continue;
            }

            $left = (int) DB::table($table)->whereIn('id', $ids)->count();

            if ($left > 0) {
                $residual[$table] = $left;
            }
        }

        if ($residual !== []) {
            $this->error(
                'Refused to report success: '.count($residual).' table(s) still hold rows this load '.
                'owns — '.implode(', ', array_map(
                    static fn ($t, $n) => "{$t}={$n}",
                    array_keys($residual),
                    $residual
                )).'. The reversal is INCOMPLETE.'
            );

            return self::FAILURE;
        }

        foreach ($owned as $table => $ids) {
            if (! $this->isExempt($table) || $ids === [] || ! $band->tableExists($table)) {
                continue;
            }

            $left = (int) DB::table($table)->whereIn('id', $ids)->count();

            if ($left > 0) {
                $this->warn(sprintf(
                    'EXEMPT RESIDUE: %d row(s) remain in `%s` and were NOT deleted — %s',
                    $left,
                    $table,
                    (string) (config('legacy_pilot.ct_scope.unload_exempt_tables')[$table] ?? 'exempt')
                ));
            }
        }

        $counters = $this->option('keep-counters')
            ? []
            : $this->restoreCounters($scope, $band, $owned);

        $ledger->forget($scope);

        $this->recordRun($scope, 'unload', ['deleted' => $deleted, 'counters' => $counters]);

        $this->info(
            'Unload complete for company '.$scope->companyId.': '.array_sum($deleted).
            ' row(s) deleted across '.count(array_filter($deleted)).' table(s), every one of them '.
            'from the ct_scope_row ledger; 0 residual owned rows. '.
            count(array_filter($counters, static fn ($c) => $c['restored'])).
            ' counter(s) restored and re-read from information_schema.TABLES.'
        );

        foreach ($counters as $table => $c) {
            if (! $c['restored']) {
                $this->warn(
                    "AUTO_INCREMENT on `{$table}` did NOT return to its recorded pre-load value ".
                    "({$c['target']}); it re-read as {$c['after']}. This is reported, never assumed ".
                    'away — see R-CO5.'
                );
            }
        }

        return self::SUCCESS;
    }

    /**
     * @throws LegacyScopeRefused
     */
    private function assertLoadExists(LegacyLoadScope $scope): void
    {
        $exists = DB::connection('legacy_pilot')->table('ct_scope_run')
            ->where('company_id', $scope->companyId)
            ->where('database_name', DB::connection()->getDatabaseName())
            ->where('action', 'arm')
            ->exists();

        if (! $exists) {
            throw new LegacyScopeRefused(
                'Refused: no legacy load is recorded for company '.$scope->companyId.' on database `'.
                DB::connection()->getDatabaseName().'` — `legacy_pilot.ct_scope_run` has no `arm` row '.
                'for it. A company this pipeline never loaded is a company this command will not '.
                'delete from, whatever its id happens to be.'
            );
        }
    }

    private function assertNotProtected(LegacyLoadScope $scope): void
    {
        /** @var list<int> $protected */
        $protected = array_map('intval', (array) config('legacy_pilot.ct_scope.protected_company_ids', []));

        if (in_array($scope->companyId, $protected, true)) {
            throw new LegacyScopeRefused(
                'Refused: company '.$scope->companyId.' is on the protected list ('.
                implode(', ', $protected).'). This command deletes rows; it will not be pointed at '.
                'a City Travelers company under any flag.'
            );
        }
    }

    /**
     * A table the unload must not delete from — today only `accounting_audit_log`, whose
     * append-only trigger refuses the statement outright. Kept as config rather than a constant so
     * the reason travels with the entry and shows up in the operator's own output.
     */
    private function isExempt(string $table): bool
    {
        return array_key_exists($table, (array) config('legacy_pilot.ct_scope.unload_exempt_tables', []));
    }

    /**
     * Pivot rows this load owns: those whose FK points at a row in the ledger. See
     * `legacy_pilot.ct_scope.pivot_tables` for why the delete is the UNION of the clauses.
     *
     * @return array<string, array{0:int, 1:string}>
     */
    private function pivotCounts(LegacyLoadScope $scope, LegacyRowLedger $ledger): array
    {
        $out = [];

        foreach ($this->pivotClauses($scope, $ledger) as $table => [$clauses, $predicate]) {
            $q = DB::table($table);
            $this->applyPivotClauses($q, $clauses);
            $out[$table] = [(int) $q->count(), $predicate];
        }

        return $out;
    }

    /**
     * @return array<string, array{0: list<array{column:string, ids:list<int>, where:array<string,string>}>, 1: string}>
     */
    private function pivotClauses(LegacyLoadScope $scope, LegacyRowLedger $ledger): array
    {
        $out = [];

        /** @var array<string, list<array{column:string, owner:string, where?:array<string,string>}>> $config */
        $config = (array) config('legacy_pilot.ct_scope.pivot_tables', []);

        foreach ($config as $table => $clauseSpecs) {
            if (! $this->tableExists($table)) {
                continue;
            }

            $clauses = [];
            $described = [];

            foreach ($clauseSpecs as $spec) {
                $ids = $ledger->ownedIds($scope, $spec['owner']);

                if ($ids === []) {
                    continue;
                }

                $clauses[] = [
                    'column' => $spec['column'],
                    'ids' => $ids,
                    'where' => $spec['where'] ?? [],
                ];

                $describe = $spec['column'].' IN owned '.$spec['owner'];

                foreach ($spec['where'] ?? [] as $col => $val) {
                    $describe .= " AND {$col}='{$val}'";
                }

                $described[] = $describe;
            }

            if ($clauses === []) {
                continue;
            }

            $out[$table] = [$clauses, implode(' OR ', $described)];
        }

        return $out;
    }

    /** @param  list<array{column:string, ids:list<int>, where:array<string,string>}>  $clauses */
    private function applyPivotClauses($query, array $clauses): void
    {
        $query->where(function ($outer) use ($clauses) {
            foreach ($clauses as $clause) {
                $outer->orWhere(function ($inner) use ($clause) {
                    $inner->whereIn($clause['column'], $clause['ids']);

                    foreach ($clause['where'] as $col => $val) {
                        $inner->where($col, $val);
                    }
                });
            }
        });
    }

    /** @return list<string> */
    private function deleteOrder(LegacyLoadScope $scope): array
    {
        $head = array_values(array_filter(
            self::EXPLICIT_ORDER_HEAD,
            static fn ($t) => in_array($t, $scope->tables, true)
        ));

        $tail = array_values(array_filter(
            self::EXPLICIT_ORDER_TAIL,
            static fn ($t) => in_array($t, $scope->tables, true)
        ));

        $rest = array_values(array_diff($scope->tables, $head, $tail));

        return array_merge($head, $rest, $tail);
    }

    /**
     * @param  array<string, list<int>>  $owned
     * @return array<string,int>
     */
    private function deleteAll(LegacyLoadScope $scope, LegacyIdBandGuard $band, LegacyRowLedger $ledger, array $owned): array
    {
        $deleted = [];

        return DB::transaction(function () use ($scope, $band, $ledger, $owned, &$deleted) {
            if ($this->option('purge-audit-log')) {
                // The escape hatch the append-only trigger itself defines — the identical
                // mechanism AccountingAuditLogPurge uses for retention. Set inside the transaction
                // so it cannot leak to an unrelated later statement on a pooled connection.
                DB::statement('SET @accounting_audit_log_allow_delete = 1');
            }

            // Pivots first: they point at roles and users, which are deleted below.
            foreach ($this->pivotClauses($scope, $ledger) as $table => [$clauses, $_predicate]) {
                $q = DB::table($table);
                $this->applyPivotClauses($q, $clauses);
                $deleted[$table] = (int) $q->delete();
            }

            foreach ($this->deleteOrder($scope) as $table) {
                if (! $band->tableExists($table) || $this->isExempt($table)) {
                    continue;
                }

                $ids = $owned[$table] ?? [];

                if ($ids === []) {
                    $deleted[$table] = 0;

                    continue;
                }

                $deleted[$table] = $table === 'accounts'
                    ? $this->deleteAccountsTree($ids)
                    : (int) DB::table($table)->whereIn('id', $ids)->delete();
            }

            // ── ROUND 3, item 4 — the R-CO4 post-conditions, INSIDE the transaction ──────────
            // `legacy:unload` was the only one of the seven commands that deletes anything and the
            // only one that never ran them. Verification proved they would have caught round 2's
            // damage: run by hand straight after that destructive unload they refused with
            // `suppliers (rows 1 -> 0 ... over id <= 5); supplier_companies (rows 1 -> 0 ... over
            // id <= 7)`. The instrument existed, worked, and was not pointed at the command that
            // needed it. Throwing here rolls the whole reversal back rather than leaving a
            // half-undone load behind.
            app(LegacyCompanyGuard::class)->assertBaselineIntact($scope);
            $band->assertNoUndeclaredTableChangedSinceCapture($scope);

            return $deleted;
        });
    }

    /**
     * `accounts.parent_id` is a self-FK, so one `DELETE … WHERE id IN (…)` fails on the first
     * parent whose children have not gone yet (MySQL evaluates the FK per row, and the delete
     * order within one statement is the optimiser's choice, not ours).
     *
     * Deleting leaves-first is the fix, and it is bounded: the loop stops the moment a pass
     * deletes nothing, so a cycle in the tree (a corrupt chart, not a normal one) terminates the
     * loop instead of spinning. Anything left is caught by the caller's residual check.
     *
     * @param  list<int>  $ids
     */
    private function deleteAccountsTree(array $ids): int
    {
        $total = 0;
        $remaining = (int) DB::table('accounts')->whereIn('id', $ids)->count();

        while ($remaining > 0) {
            $gone = (int) DB::table('accounts')
                ->whereIn('id', $ids)
                ->whereNotIn('id', function ($q) use ($ids) {
                    $q->select('parent_id')->from('accounts')
                        ->whereNotNull('parent_id')
                        ->whereIn('id', $ids);
                })
                ->delete();

            $total += $gone;

            if ($gone === 0) {
                break;
            }

            $remaining = (int) DB::table('accounts')->whereIn('id', $ids)->count();
        }

        return $total;
    }

    /**
     * @param  array<string, list<int>>  $owned
     * @return array<string, array{target:int, before:int, after:int, restored:bool}>
     */
    private function restoreCounters(LegacyLoadScope $scope, LegacyIdBandGuard $band, array $owned): array
    {
        $database = $band->databaseName();
        $out = [];

        $recorded = DB::connection('legacy_pilot')->table('ct_scope_counter')
            ->where('company_id', $scope->companyId)
            ->where('database_name', $database)
            ->pluck('auto_increment_before', 'table_name');

        foreach ($scope->tables as $table) {
            if (! $band->tableExists($table) || ! isset($recorded[$table])) {
                continue;
            }

            // A table whose rows were left in place cannot have its counter restored — CD0's whole
            // finding. Skipping it is the difference between "not attempted, and said so" and
            // "attempted, reported success from an exit code, and was wrong".
            if ($this->isExempt($table) && ($owned[$table] ?? []) !== []) {
                continue;
            }

            $target = (int) $recorded[$table];
            $before = $band->autoIncrement($table);

            DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.$target);

            // R-CO5 — the ALTER above returns exit 0 whether or not it moved anything.
            $after = $band->autoIncrement($table);

            $out[$table] = [
                'target' => $target,
                'before' => $before,
                'after' => $after,
                'restored' => $after === $target,
            ];
        }

        return $out;
    }

    private function tableExists(string $table): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [DB::connection()->getDatabaseName(), $table]
        ) !== null;
    }

    /** @param  array<string,mixed>  $detail */
    private function recordRun(LegacyLoadScope $scope, string $action, array $detail): void
    {
        DB::connection('legacy_pilot')->table('ct_scope_run')->insert([
            'company_id' => $scope->companyId,
            'database_name' => DB::connection()->getDatabaseName(),
            'action' => $action,
            'id_floor' => $scope->idFloor,
            'id_ceiling' => $scope->idCeiling,
            'detail' => json_encode($detail, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
