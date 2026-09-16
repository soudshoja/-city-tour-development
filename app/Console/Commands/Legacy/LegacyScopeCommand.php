<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\Scope\LegacyCompanyGuard;
use App\Services\Onboarding\Scope\LegacySandboxGuard;
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

    public function handle(LegacyIdBandGuard $band, LegacyCompanyGuard $company): int
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
            // The sandbox gate, before anything — see GuardsLegacyScope::assertLegacyScope().
            app(LegacySandboxGuard::class)->assertSandbox();

            LegacyPathGuard::assertQuarantinedConnection();

            $scope = LegacyLoadScope::forCompany((int) $companyOption);

            // ROUND 2, finding F3. This command used to have NO company gate at all:
            // `legacy:scope --company=2 --apply` happily reported "Band armed for company 2:
            // 21 counter(s) raised", arming the reversal trap under a PROTECTED City Travelers id.
            // It now passes the same gate every other write command does, with one deliberate
            // relaxation: the company does NOT have to exist yet. It cannot - the load order is
            // scope-then-provision, precisely so that `companies`' own AUTO_INCREMENT is recorded
            // BEFORE the company row is minted. If it does already exist, it must be in the band.
            $this->assertCompanyGate($scope);

            // Record the counters BEFORE arming anything: once arm() has run, the pre-load value is
            // gone and cannot be recovered from the database.
            $recorded = $apply ? $this->recordCounters($scope, $band) : $this->previewCounters($scope, $band);

            // ROUND 2, finding F2. Capture and PERSIST the before-picture while it still IS the
            // before-picture. Round 1 had these checks but no caller on the deployed path and no
            // stored baseline, so after a load there was nothing left to compare against.
            if ($apply) {
                $baseline = $company->captureBaseline($scope);
                $census = $band->captureCensus($scope);
            }

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

        $this->line(sprintf(
            'baseline captured: %d table fingerprint(s) + %d table row count(s), persisted to '.
            'legacy_pilot.ct_scope_fingerprint. Every legacy:* write command now compares against '.
            'them on completion and refuses on a difference.',
            count($baseline ?? []),
            count($census ?? [])
        ));

        $this->info(
            "Band armed for company {$scope->companyId}: {$raised} counter(s) raised to ".
            number_format($scope->idFloor).", {$already} already inside the band ".
            $scope->bandDescription().'. Each raise was re-read from information_schema.TABLES and '.
            'verified to have moved (R-CO5).'
        );

        return self::SUCCESS;
    }

    /**
     * The company half of the gate, finding F3.
     *
     * @throws LegacyScopeRefused
     */
    private function assertCompanyGate(LegacyLoadScope $scope): void
    {
        /** @var list<int> $protected */
        $protected = array_map('intval', (array) config('legacy_pilot.ct_scope.protected_company_ids', []));

        if (in_array($scope->companyId, $protected, true)) {
            throw new LegacyScopeRefused(
                'Refused: company '.$scope->companyId.' is on the protected list ('.
                implode(', ', $protected).'). Arming the reserved band under a City Travelers '.
                'company id is how a later reversal comes to be pointed at one.'
            );
        }

        $existing = DB::table('companies')->where('id', $scope->companyId)->first();

        // ── R4-4, the ordering trap, now enforced instead of merely documented ────────────────
        //
        // The baseline this command captures is anchored per table to `max_id_at_capture`, and
        // every later post-condition compares `id <= max_id_at_capture`. Capture it AFTER
        // provisioning and Como's own rows fall INSIDE the baseline — at which point every
        // legitimate deletion the reversal performs is itself a baseline violation, and
        // **the reversal becomes structurally impossible.** Verification hit this on its first
        // sibling run and nothing in the output explained it.
        //
        // The procedure's step order (scope, THEN provision) was always right; relying on an
        // operator following it was not. This refuses if the target company already owns rows and
        // no baseline has been captured yet.
        //
        // Scoped precisely: it fires only when there is no `pre_load` baseline. Re-running
        // `legacy:scope --apply` later is a legitimate no-op — captureBaseline() never overwrites
        // an existing pre_load row — and must not be blocked by this.
        if (! app(LegacyCompanyGuard::class)->hasBaseline($scope)) {
            $existingRows = $this->countExistingCompanyRows($scope);

            if ($existingRows !== []) {
                $detail = implode(', ', array_map(
                    static fn ($t, $n) => "{$t}={$n}",
                    array_keys($existingRows),
                    $existingRows
                ));

                throw new LegacyScopeRefused(
                    'Refused: company '.$scope->companyId.' already owns rows ('.$detail.') and no '.
                    'pre-load baseline has been captured yet. This command must run BEFORE the '.
                    'company is provisioned. The baseline is anchored per table to MAX(id) at '.
                    'capture time, so capturing it now would place the load\'s own rows INSIDE the '.
                    'baseline — every deletion the reversal later performs would then read as a '.
                    'baseline violation and the reversal would be structurally impossible. Start '.
                    'from a fresh sandbox, or accept that this load cannot be reversed and say so '.
                    'explicitly somewhere a reviewer will see it.'
                );
            }
        }

        if ($existing !== null && ! $scope->contains((int) $existing->id)) {
            throw new LegacyScopeRefused(
                'Refused: company '.$scope->companyId.' already exists and its id is OUTSIDE the '.
                'reserved band '.$scope->bandDescription().'. This band is for a company the '.
                'pipeline creates inside it; an existing company below the floor is somebody '.
                'else\'s.'
            );
        }
    }

    /**
     * Rows the target company already owns, per declared table. Used only by the R4-4 ordering
     * check above, and deliberately counting rather than sampling: "already has rows" is the
     * whole question.
     *
     * @return array<string,int>
     */
    private function countExistingCompanyRows(LegacyLoadScope $scope): array
    {
        $database = DB::connection()->getDatabaseName();
        $out = [];

        foreach ($scope->tables as $table) {
            $hasCompany = DB::selectOne(
                'SELECT 1 AS present FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$database, $table, 'company_id']
            );

            if ($hasCompany === null) {
                continue;
            }

            $n = (int) DB::table($table)->where('company_id', $scope->companyId)->count();

            if ($n > 0) {
                $out[$table] = $n;
            }
        }

        return $out;
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
