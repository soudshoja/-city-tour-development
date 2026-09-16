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
 * CD-PORT — arm the reserved id band before a legacy load, and record what the counters were.
 *
 * This is the ONE command in the pipeline that deliberately changes something about the City
 * Travelers application database without inserting a row: it raises each declared write-set
 * table's `AUTO_INCREMENT` to the band floor. Every other legacy:* command only ASSERTS the band
 * and refuses if it is not armed, so that "the counters moved" is an explicit operator action with
 * its own audit row rather than a side effect of running an import.
 *
 * `--dry-run` is the default and writes nothing anywhere — not the counters, not the audit row.
 * You have to type `--apply`. That asymmetry is deliberate: CD0's finding F0 was a wrapper script
 * that silently ignored a `--dry-run` flag and ran a real full sync of the dev database, and the
 * lesson recorded from it was that a dry run must be the DEFAULT, not an option the caller has to
 * remember to pass through.
 */
class LegacyScopeCommand extends Command
{
    protected $signature = 'legacy:scope
                            {--company= : The company id the load targets (required — there is deliberately no default)}
                            {--apply : Actually raise the counters. Without this the command reports what it would do and writes nothing}';

    protected $description = 'CD-PORT — arm the reserved id band (10,000,001..19,999,999) for one company and record each table\'s pre-load AUTO_INCREMENT, so the unload can restore it exactly.';

    public function handle(LegacyIdBandGuard $band): int
    {
        $companyOption = $this->option('company');

        if ($companyOption === null || $companyOption === '') {
            $this->error(
                'Refused: --company is required. There is no default — legacy_pilot.default_company_id '.
                'is 1, and on the City Travelers development database company 1 is City Travelers '.
                'itself (497 accounts, 43,183 transactions, 112,662 journal lines).'
            );

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        try {
            LegacyPathGuard::assertQuarantinedConnection();

            $scope = LegacyLoadScope::forCompany((int) $companyOption);

            // Record the counters BEFORE arming anything: once arm() has run, the pre-load value is
            // gone and cannot be recovered from the database.
            $recorded = $apply ? $this->recordCounters($scope, $band) : $this->previewCounters($scope, $band);

            $report = $band->arm($scope, $apply);
        } catch (LegacyScopeRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($report as $table => $r) {
            $rows[] = [
                $table,
                number_format($r['before']),
                number_format($r['after']),
                $r['action'],
                number_format($recorded[$table] ?? 0),
            ];
        }

        $this->table(['table', 'AUTO_INCREMENT before', 'after', 'action', 'MAX(id) before'], $rows);

        $raised = count(array_filter($report, static fn ($r) => $r['action'] === 'raised'));
        $already = count(array_filter($report, static fn ($r) => $r['action'] === 'already'));
        $would = count(array_filter($report, static fn ($r) => $r['action'] === 'would_raise'));

        if (! $apply) {
            $this->warn(
                "DRY RUN — nothing was written. {$would} table(s) would be raised to ".
                number_format($scope->idFloor).", {$already} already inside the band. ".
                'Re-run with --apply to arm.'
            );

            return self::SUCCESS;
        }

        $this->recordRun($scope, 'arm', $report);

        $this->info(
            "Band armed for company {$scope->companyId}: {$raised} counter(s) raised to ".
            number_format($scope->idFloor).", {$already} already inside the band ".
            $scope->bandDescription().'. Each raise was re-read from information_schema.TABLES and '.
            'verified to have moved (R-CO5).'
        );

        return self::SUCCESS;
    }

    /**
     * Persist each table's pre-load AUTO_INCREMENT (and its MAX(id), so a reviewer can see the
     * gap) into the quarantined schema. `updateOrInsert` keyed on (company, database, table) makes
     * a re-run idempotent — but note it does NOT overwrite an existing row's
     * `auto_increment_before`: once a load has raised a counter, the value recorded on the FIRST
     * arming is the only true pre-load value, and a second arming would otherwise overwrite it
     * with the already-raised one and silently destroy the unload's restore target.
     *
     * @return array<string,int> table => MAX(id) before
     */
    private function recordCounters(LegacyLoadScope $scope, LegacyIdBandGuard $band): array
    {
        $database = $band->databaseName();
        $maxIds = [];

        foreach ($scope->tables as $table) {
            if (! $band->tableExists($table)) {
                continue;
            }

            $maxId = (int) DB::table($table)->max('id');
            $maxIds[$table] = $maxId;

            $existing = DB::connection('legacy_pilot')->table('ct_scope_counter')
                ->where('company_id', $scope->companyId)
                ->where('database_name', $database)
                ->where('table_name', $table)
                ->first();

            if ($existing !== null) {
                continue;
            }

            DB::connection('legacy_pilot')->table('ct_scope_counter')->insert([
                'company_id' => $scope->companyId,
                'database_name' => $database,
                'table_name' => $table,
                'auto_increment_before' => $band->autoIncrement($table),
                'max_id_before' => $maxId,
                'id_floor' => $scope->idFloor,
                'id_ceiling' => $scope->idCeiling,
                'recorded_at' => now(),
            ]);
        }

        return $maxIds;
    }

    /** @return array<string,int> */
    private function previewCounters(LegacyLoadScope $scope, LegacyIdBandGuard $band): array
    {
        $maxIds = [];

        foreach ($scope->tables as $table) {
            if ($band->tableExists($table)) {
                $maxIds[$table] = (int) DB::table($table)->max('id');
            }
        }

        return $maxIds;
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
