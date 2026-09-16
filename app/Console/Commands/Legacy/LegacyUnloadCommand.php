<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\Scope\LegacyIdBandGuard;
use App\Services\Onboarding\Scope\LegacyLoadScope;
use App\Services\Onboarding\Scope\LegacyScopeRefused;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CD-PORT — the exact undo. `CD0-REVERSAL-MANIFEST-2026-09-16.md` §3, executable.
 *
 * The manifest is fifteen hand-written `DELETE … WHERE id BETWEEN` statements plus fifteen
 * `ALTER … AUTO_INCREMENT` statements, and CD0 proved it correct on a local fence. This command is
 * the same procedure with four things the hand-written version could not have:
 *
 *   1. **It cannot drift from the write set.** The delete order and the table list are derived from
 *      `legacy_pilot.ct_scope.tables`, which is the same list the guards enforce during the load.
 *      A table added to the load is a table the unload deletes from, in the same commit.
 *   2. **It reaches the tables the manifest could not.** `model_has_roles`,
 *      `model_has_permissions` and `role_has_permissions` have NO `id` column, so the manifest's
 *      `WHERE id BETWEEN` cannot touch them and the rows the provisioner writes there would have
 *      survived the documented reversal. Each is deleted here through a FK that IS in the band.
 *   3. **It restores each counter to the value recorded before the load**, not to `MAX(id) + 1` —
 *      see the `ct_scope_counter` migration for why those are different numbers and why the
 *      difference matters for `accounts`.
 *   4. **It re-reads every counter after the ALTER.** CD0's central finding is that
 *      `ALTER TABLE t AUTO_INCREMENT = n` returns exit 0 while doing nothing; a reversal that
 *      trusted the exit code would report fifteen restored counters and be wrong about all
 *      fifteen. Ruling R-CO5.
 *
 * `--dry-run` is the DEFAULT. `--apply` must be typed.
 *
 * ── The one thing this command will not do ──────────────────────────────────────────────────────
 * It never deletes a row outside the band, and it never deletes a row of a protected company, even
 * if asked. The band predicate is on every statement, and the company gate runs first. A caller
 * who wants City Travelers' rows gone is asking the wrong tool.
 */
class LegacyUnloadCommand extends Command
{
    protected $signature = 'legacy:unload
                            {--company= : The company whose legacy load is being reversed (required)}
                            {--apply : Actually delete. Without this the command counts and reports, and writes nothing}
                            {--keep-counters : Delete the rows but leave every AUTO_INCREMENT where it is}
                            {--purge-audit-log : Also remove this load\'s accounting_audit_log rows by setting the append-only table\'s own documented escape-hatch session variable. Off by default — the append-only invariant belongs to City Travelers, not to this lane}';

    protected $description = 'CD-PORT — reverse a legacy load: delete every row in the reserved id band for one company, restore each table\'s recorded pre-load AUTO_INCREMENT, and report the counts.';

    /**
     * Delete order: children before parents, so no FK is ever left dangling and
     * `FOREIGN_KEY_CHECKS` never has to be turned off.
     *
     * Derived from the FK graph measured on the fence:
     *   journal_entries -> transactions, accounts, branches, companies
     *   transactions    -> companies, users
     *   accounts        -> accounts (self), companies, supplier_companies
     *   branches        -> companies, users
     *   companies       -> users
     *
     * `accounts` is self-referencing via `parent_id`, so its rows are deleted deepest-child-first
     * inside {@see self::deleteAccountsTree()} rather than in one statement.
     *
     * A table in `ct_scope.tables` that is NOT named here is deleted after this list, before
     * `accounts`/`branches`/`companies`/`users` — see {@see self::deleteOrder()}. That is a
     * deliberate fail-safe: a newly declared table still gets reversed even if nobody updated this
     * constant, it just gets a less considered position in the order.
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
        'jobs',
    ];

    /** Deleted last, in this order, because everything else points at them. */
    private const EXPLICIT_ORDER_TAIL = [
        'accounts',
        'roles',
        'branches',
        'companies',
        'users',
    ];

    public function handle(LegacyIdBandGuard $band): int
    {
        $companyOption = $this->option('company');

        if ($companyOption === null || $companyOption === '') {
            $this->error('Refused: --company is required. There is deliberately no default.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        try {
            LegacyPathGuard::assertQuarantinedConnection();
            $scope = LegacyLoadScope::forCompany((int) $companyOption);
            $this->assertNotProtected($scope);
            $this->assertBandBelongsSolelyToTarget($scope, $band);
        } catch (LegacyScopeRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $counts = $this->countBandRows($scope, $band);
        $pivotCounts = $this->countPivotRows($scope, $band);

        $rows = [];

        foreach ($counts as $table => $n) {
            $rows[] = [$table, $n, 'id BETWEEN band'];
        }

        foreach ($pivotCounts as $table => [$n, $predicate]) {
            $rows[] = [$table, $n, $predicate];
        }

        $this->table(['table', 'rows in band', 'predicate'], $rows);

        $total = array_sum($counts) + array_sum(array_map(static fn ($p) => $p[0], $pivotCounts));

        if (! $apply) {
            $this->warn(
                "DRY RUN — nothing was deleted. {$total} row(s) are inside the band ".
                $scope->bandDescription()." for company {$scope->companyId}. Re-run with --apply."
            );

            return self::SUCCESS;
        }

        $deleted = $this->deleteAll($scope, $band, $pivotCounts);

        $residual = array_filter(
            $this->countBandRows($scope, $band),
            fn ($n, $table) => $n > 0 && ! $this->isExempt($table),
            ARRAY_FILTER_USE_BOTH
        );

        $exemptResidue = array_filter(
            $this->countBandRows($scope, $band),
            fn ($n, $table) => $n > 0 && $this->isExempt($table),
            ARRAY_FILTER_USE_BOTH
        );
        $residualPivots = array_filter($this->countPivotRows($scope, $band), static fn ($p) => $p[0] > 0);

        if ($residual !== [] || $residualPivots !== []) {
            $names = array_merge(array_keys($residual), array_keys($residualPivots));

            $this->error(
                'Refused to report success: '.count($names).' table(s) still hold rows in the band '.
                'after the unload — '.implode(', ', $names).'. The reversal is INCOMPLETE.'
            );

            return self::FAILURE;
        }

        foreach ($exemptResidue as $table => $n) {
            $this->warn(sprintf(
                'EXEMPT RESIDUE: %d row(s) remain in `%s` inside the band and were NOT deleted — %s',
                $n,
                $table,
                (string) (config('legacy_pilot.ct_scope.unload_exempt_tables')[$table] ?? 'exempt')
            ));
        }

        $counters = $this->option('keep-counters')
            ? []
            : $this->restoreCounters($scope, $band);

        $this->recordRun($scope, 'unload', ['deleted' => $deleted, 'counters' => $counters]);

        $this->info(
            'Unload complete for company '.$scope->companyId.': '.array_sum($deleted).
            ' row(s) deleted across '.count(array_filter($deleted)).' table(s); 0 residual rows in '.
            'the band. '.count(array_filter($counters, static fn ($c) => $c['restored'])).
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
     * A table the unload must not delete from — today only `accounting_audit_log`, whose
     * append-only trigger refuses the statement outright. Kept as config rather than a constant so
     * the reason travels with the entry and shows up in the operator's own output.
     */
    private function isExempt(string $table): bool
    {
        return array_key_exists($table, (array) config('legacy_pilot.ct_scope.unload_exempt_tables', []));
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

    /** @return array<string,int> */
    private function countBandRows(LegacyLoadScope $scope, LegacyIdBandGuard $band): array
    {
        $out = [];

        foreach ($this->deleteOrder($scope) as $table) {
            if (! $band->tableExists($table)) {
                continue;
            }

            $out[$table] = (int) $this->bandQuery($table, $scope, $band)->count();
        }

        return $out;
    }

    /** @return array<string, array{0:int, 1:string}> */
    private function countPivotRows(LegacyLoadScope $scope, LegacyIdBandGuard $band): array
    {
        $out = [];

        foreach ($scope->pivotTables as $table => $spec) {
            if (! $band->tableExists($table)) {
                continue;
            }

            $q = DB::table($table)->whereBetween($spec['column'], [$scope->idFloor, $scope->idCeiling]);
            $predicate = $spec['column'].' BETWEEN band';

            foreach (($spec['where'] ?? []) as $col => $val) {
                $q->where($col, $val);
                $predicate .= " AND {$col} = '{$val}'";
            }

            $out[$table] = [(int) $q->count(), $predicate];
        }

        return $out;
    }

    /**
     * The band predicate, plus — for a table that HAS a `company_id` — the company predicate too.
     *
     * Both, not either. The band alone would delete another company's row if one ever landed in the
     * band (it should not, but "should not" is not a guarantee), and the company alone would delete
     * a row the load did not create. Requiring both means a row has to be BOTH in the band AND the
     * target company's before this command will remove it, which is the strictest reading of the
     * reversal manifest rather than the loosest.
     */
    private function bandQuery(string $table, LegacyLoadScope $scope, LegacyIdBandGuard $band)
    {
        $q = DB::table($table)->whereBetween('id', [$scope->idFloor, $scope->idCeiling]);

        if ($table === 'companies') {
            // `companies` is a "global" table only in the sense that it has no `company_id`
            // COLUMN — its `id` IS the company. Leaving it on the band predicate alone was a real
            // defect, caught by the scoping mutation proof: `legacy:unload --company=50` (a
            // company with nothing in the band) built `DELETE FROM companies WHERE id BETWEEN
            // 10000001 AND 19999999` and tried to delete the OTHER, unrelated legacy company that
            // was loaded there, failing on `accounts_company_id_foreign` rather than on anything
            // that would have told the operator what it had just attempted.
            return $q->where('id', $scope->companyId);
        }

        if (! $scope->isGlobalTable($table) && $band->hasColumn($table, 'company_id')) {
            $q->where('company_id', $scope->companyId);
        }

        return $q;
    }

    /**
     * The precondition that makes deleting from a `company_id`-less table safe at all.
     *
     * `users`, `agents`, `suppliers` and `jobs` carry no company column, so for them the id band
     * IS the scope — which is only sound if the band holds ONE load's rows. This asserts exactly
     * that, by asking every company-scoped table in the write set which companies own rows inside
     * the band, and refusing if the answer is anything other than "only the target, or nobody".
     *
     * Without it, two legacy companies sharing a band would each unload the other's global rows.
     * With it, that configuration is refused before the first DELETE, naming the companies
     * involved — and the operator's answer is to give the second load its own band, not to force
     * this one through.
     *
     * @throws LegacyScopeRefused
     */
    private function assertBandBelongsSolelyToTarget(LegacyLoadScope $scope, LegacyIdBandGuard $band): void
    {
        $intruders = [];

        foreach ($scope->tables as $table) {
            if ($table === 'companies' || $scope->isGlobalTable($table) || ! $band->tableExists($table)) {
                continue;
            }

            if (! $band->hasColumn($table, 'company_id')) {
                continue;
            }

            $others = DB::table($table)
                ->whereBetween('id', [$scope->idFloor, $scope->idCeiling])
                ->where('company_id', '<>', $scope->companyId)
                ->distinct()
                ->pluck('company_id')
                ->all();

            foreach ($others as $other) {
                $intruders[(int) $other][] = $table;
            }
        }

        // `companies` itself: another company row sitting in the band is the same problem.
        $otherCompanies = DB::table('companies')
            ->whereBetween('id', [$scope->idFloor, $scope->idCeiling])
            ->where('id', '<>', $scope->companyId)
            ->pluck('id')
            ->all();

        foreach ($otherCompanies as $other) {
            $intruders[(int) $other][] = 'companies';
        }

        if ($intruders !== []) {
            $detail = [];

            foreach ($intruders as $companyId => $tables) {
                $detail[] = 'company '.$companyId.' in '.implode('/', array_unique($tables));
            }

            throw new LegacyScopeRefused(
                'Refused: the reserved band '.$scope->bandDescription().' holds rows belonging to '.
                count($intruders).' company/companies other than the unload target '.
                $scope->companyId.' — '.implode('; ', $detail).'. Tables with no `company_id` '.
                'column (users, agents, suppliers, jobs) are scoped by the band ALONE, so deleting '.
                'from them here would take the other load\'s rows with them. Give each load its own '.
                'band before unloading either.'
            );
        }
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
     * @param  array<string, array{0:int, 1:string}>  $pivotCounts
     * @return array<string,int>
     */
    private function deleteAll(LegacyLoadScope $scope, LegacyIdBandGuard $band, array $pivotCounts): array
    {
        $deleted = [];

        return DB::transaction(function () use ($scope, $band, $pivotCounts, &$deleted) {
            // Pivots first: they point at roles/users, which are deleted below.
            foreach ($scope->pivotTables as $table => $spec) {
                if (! $band->tableExists($table) || ($pivotCounts[$table][0] ?? 0) === 0) {
                    continue;
                }

                $q = DB::table($table)->whereBetween($spec['column'], [$scope->idFloor, $scope->idCeiling]);

                foreach (($spec['where'] ?? []) as $col => $val) {
                    $q->where($col, $val);
                }

                $deleted[$table] = (int) $q->delete();
            }

            if ($this->option('purge-audit-log')) {
                // The escape hatch the append-only trigger itself defines — see
                // legacy_pilot.ct_scope.unload_exempt_tables and AccountingAuditLogPurge, which
                // uses the identical mechanism for retention. Set inside the transaction so it
                // cannot leak to an unrelated later statement on a pooled connection.
                DB::statement('SET @accounting_audit_log_allow_delete = 1');
            }

            foreach ($this->deleteOrder($scope) as $table) {
                if (! $band->tableExists($table)) {
                    continue;
                }

                if ($this->isExempt($table)) {
                    continue;
                }

                $deleted[$table] = $table === 'accounts'
                    ? $this->deleteAccountsTree($scope, $band)
                    : (int) $this->bandQuery($table, $scope, $band)->delete();
            }

            return $deleted;
        });
    }

    /**
     * `accounts.parent_id` is a self-FK, so a single `DELETE … WHERE id BETWEEN` fails on the first
     * parent whose children have not gone yet (MySQL evaluates the FK per row, and the delete order
     * within one statement is the optimiser's choice, not ours).
     *
     * Deleting leaves-first in `level` DESCENDING order is the fix, and it is bounded: the loop
     * runs at most once per distinct level and stops the moment a pass deletes nothing, so a cycle
     * in the tree (which would be a corrupt chart, not a normal one) terminates the loop instead of
     * spinning. Any rows left after that are reported by the caller's residual check rather than
     * silently tolerated.
     */
    private function deleteAccountsTree(LegacyLoadScope $scope, LegacyIdBandGuard $band): int
    {
        $total = 0;

        $levels = DB::table('accounts')
            ->whereBetween('id', [$scope->idFloor, $scope->idCeiling])
            ->where('company_id', $scope->companyId)
            ->distinct()
            ->orderByDesc('level')
            ->pluck('level');

        foreach ($levels as $level) {
            $total += (int) $this->bandQuery('accounts', $scope, $band)->where('level', $level)->delete();
        }

        // Anything left (a NULL level, or a row whose level ordering did not satisfy its own FK)
        // gets one more pass, deepest id first.
        $remaining = (int) $this->bandQuery('accounts', $scope, $band)->count();

        while ($remaining > 0) {
            $gone = (int) $this->bandQuery('accounts', $scope, $band)
                ->whereNotIn('id', function ($q) use ($scope) {
                    $q->select('parent_id')->from('accounts')
                        ->whereNotNull('parent_id')
                        ->whereBetween('id', [$scope->idFloor, $scope->idCeiling])
                        ->where('company_id', $scope->companyId);
                })
                ->delete();

            $total += $gone;

            if ($gone === 0) {
                break;
            }

            $remaining = (int) $this->bandQuery('accounts', $scope, $band)->count();
        }

        return $total;
    }

    /**
     * @return array<string, array{target:int, before:int, after:int, restored:bool}>
     */
    private function restoreCounters(LegacyLoadScope $scope, LegacyIdBandGuard $band): array
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
            // finding. Skipping it here is the difference between "not attempted, and said so" and
            // "attempted, reported success from an exit code, and was wrong".
            if ($this->isExempt($table) && (int) DB::table($table)->whereBetween('id', [$scope->idFloor, $scope->idCeiling])->count() > 0) {
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
