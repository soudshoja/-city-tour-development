<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Scope;

use Illuminate\Support\Facades\DB;

/**
 * CD-PORT — keeps every id this pipeline mints inside the reserved band, and proves it.
 *
 * ── The mechanism, and why it is the AUTO_INCREMENT counter and not an interceptor ──────────────
 * Every write in the ported pipeline goes through Eloquent or
 * {@see \App\Services\Accounting\PostingSeam::post()}, which mint ids by letting MySQL allocate
 * them. There is therefore exactly one place to put the floor: the table's own `AUTO_INCREMENT`
 * counter. {@see self::arm()} raises it to {@see LegacyLoadScope::$idFloor} before the first write,
 * and from then on every id the application mints is inside the band by construction, with no
 * per-insert interception, no model events to forget to register, and nothing that a raw
 * `DB::table()->insert()` somewhere in the pipeline could slip past.
 *
 * The alternative — intercepting inserts and rewriting ids — was rejected: it would have to be
 * wired into every writer individually (the pipeline has three: Eloquent models, PostingSeam, and
 * the loader's own `DB::connection('legacy_pilot')` writes), a missed writer fails SILENTLY, and
 * it would put this lane's code inside PostingService, which the phase is explicitly forbidden to
 * weaken or reshape.
 *
 * ── R-CO5, applied without exception: an ALTER's exit code is not evidence ──────────────────────
 * CD0 measured this on MariaDB 10.11.19 (the server's exact build). `ALTER TABLE t AUTO_INCREMENT
 * = n` where `n <= MAX(id)` returns **exit 0 with an empty SHOW WARNINGS and changes nothing**. A
 * run that issued the statement and trusted its exit code would report success on every table and
 * be wrong on every table — CD0 reproduced exactly that on 14 tables at once. So {@see self::arm()}
 * re-reads `information_schema.TABLES` after every `ALTER` and compares the VALUE; a counter that
 * did not move is a refusal by name, never a warning.
 *
 * That asymmetry only bites when RAISING a counter if the table is somehow already above the
 * ceiling — raising below `MAX(id)+1` is exactly the case InnoDB silently ignores. Which is why
 * {@see self::arm()} refuses a table whose `MAX(id)` already sits above the floor rather than
 * trying to move the counter under it: that table's ids are not this run's to reserve.
 */
final class LegacyIdBandGuard
{
    /**
     * Raise every declared write-set table's `AUTO_INCREMENT` to the band floor, and PROVE each
     * one moved by re-reading it.
     *
     * Idempotent: a table already at or above the floor (because a previous run of this same load
     * already armed it, or already wrote rows in the band) is left alone and reported as
     * `already`. A table whose counter is above the CEILING is a refusal — the band is exhausted
     * or someone else is minting in it, and either way this run must not add to it.
     *
     * @return array<string, array{before:int, after:int, action:string}>
     *
     * @throws LegacyScopeRefused when a counter cannot be moved into the band
     */
    public function arm(LegacyLoadScope $scope, bool $apply = true): array
    {
        $report = [];

        foreach ($scope->tables as $table) {
            if (! $this->tableExists($table)) {
                throw new LegacyScopeRefused(
                    "Refused: declared write-set table '{$table}' does not exist in database '".
                    $this->databaseName()."'. A write set that names a table the schema does not ".
                    'have cannot be checked, so nothing here may run.'
                );
            }

            $before = $this->autoIncrement($table);
            $maxId = (int) DB::table($table)->max('id');

            if ($maxId > $scope->idCeiling) {
                throw new LegacyScopeRefused(
                    "Refused: table '{$table}' already holds id {$maxId}, above the band ceiling ".
                    number_format($scope->idCeiling).'. This run will not mint into a band it does '.
                    'not own.'
                );
            }

            if ($before >= $scope->idFloor) {
                if ($before > $scope->idCeiling) {
                    throw new LegacyScopeRefused(
                        "Refused: table '{$table}' AUTO_INCREMENT is {$before}, above the band ".
                        'ceiling '.number_format($scope->idCeiling).'. The reserved band is '.
                        'exhausted; the next id this table mints would land outside it.'
                    );
                }

                $report[$table] = ['before' => $before, 'after' => $before, 'action' => 'already'];

                continue;
            }

            if (! $apply) {
                $report[$table] = ['before' => $before, 'after' => $before, 'action' => 'would_raise'];

                continue;
            }

            DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.$scope->idFloor);

            // R-CO5. The ALTER above returns exit 0 whether or not it did anything.
            $after = $this->autoIncrement($table);

            if ($after < $scope->idFloor) {
                throw new LegacyScopeRefused(
                    "Refused: ALTER TABLE `{$table}` AUTO_INCREMENT = ".number_format($scope->idFloor).
                    " reported success but the counter re-read as {$after}, still below the floor. ".
                    'InnoDB silently ignores a counter move it considers invalid; this run will not '.
                    'mint ids it cannot place inside the band.'
                );
            }

            $report[$table] = ['before' => $before, 'after' => $after, 'action' => 'raised'];
        }

        return $report;
    }

    /**
     * The pre-write gate every legacy:* write command calls before it does anything at all.
     *
     * Refuses — BEFORE the first write — if any declared write-set table would mint its next id
     * outside the band. This is the check the brief's "a load that would write outside the band
     * must refuse before the first write" names, and it is deliberately a separate method from
     * {@see self::arm()} so that a command can assert the band without being the thing that
     * silently changes a counter: arming is an explicit operator action (`legacy:scope --apply`),
     * asserting is automatic.
     *
     * @throws LegacyScopeRefused
     */
    public function assertArmed(LegacyLoadScope $scope): void
    {
        $offenders = [];

        foreach ($scope->tables as $table) {
            if (! $this->tableExists($table)) {
                throw new LegacyScopeRefused(
                    "Refused: declared write-set table '{$table}' does not exist in database '".
                    $this->databaseName()."'."
                );
            }

            $counter = $this->autoIncrement($table);

            if ($counter < $scope->idFloor || $counter > $scope->idCeiling) {
                $offenders[$table] = $counter;
            }
        }

        if ($offenders !== []) {
            $detail = implode(', ', array_map(
                static fn ($t, $c) => "{$t}={$c}",
                array_keys($offenders),
                $offenders
            ));

            throw new LegacyScopeRefused(
                'Refused before the first write: '.count($offenders).' write-set table(s) would mint '.
                'an id outside the reserved band '.$scope->bandDescription().' — '.$detail.'. '.
                'Run `php artisan legacy:scope --company='.$scope->companyId.' --apply` first.'
            );
        }
    }

    /**
     * The post-condition twin of {@see self::assertArmed()}: every row that now belongs to the
     * target company, in every declared write-set table, has an id inside the band.
     *
     * For a table with a `company_id` column this reads that company's rows directly. For a
     * GLOBAL table (no `company_id` at all — `suppliers` is the one that matters here) there is
     * nothing to filter on, so the assertion is the reverse one and is stated against the run's
     * own record of what it created: see {@see self::assertNoUndeclaredTableGrew()}, which is what
     * actually catches a global-table write that landed below the floor.
     *
     * @return array<string, array{rows:int, min:?int, max:?int}>
     *
     * @throws LegacyScopeRefused
     */
    public function assertCompanyRowsInBand(LegacyLoadScope $scope): array
    {
        $report = [];
        $offenders = [];

        foreach ($scope->tables as $table) {
            if ($scope->isGlobalTable($table) || ! $this->hasColumn($table, 'company_id')) {
                continue;
            }

            $row = DB::table($table)
                ->where('company_id', $scope->companyId)
                ->selectRaw('COUNT(*) AS c, MIN(id) AS lo, MAX(id) AS hi')
                ->first();

            $count = (int) $row->c;
            $lo = $row->lo === null ? null : (int) $row->lo;
            $hi = $row->hi === null ? null : (int) $row->hi;

            $report[$table] = ['rows' => $count, 'min' => $lo, 'max' => $hi];

            if ($count === 0) {
                continue;
            }

            if (! $scope->contains($lo) || ! $scope->contains($hi)) {
                $offenders[] = "{$table} (rows={$count}, min={$lo}, max={$hi})";
            }
        }

        if ($offenders !== []) {
            throw new LegacyScopeRefused(
                'Refused: company '.$scope->companyId.' owns rows outside the reserved band '.
                $scope->bandDescription().' in '.count($offenders).' table(s) — '.
                implode('; ', $offenders).'. The reversal manifest deletes by id band, so a row '.
                'outside it would survive the unload.'
            );
        }

        return $report;
    }

    /**
     * Row counts for EVERY table in the application database — the "before" half of the
     * undeclared-growth check.
     *
     * Deliberately every table, not just the declared write set: the whole point is to catch a
     * table the write set does NOT name. Counting is done with `COUNT(*)`, not
     * `information_schema.TABLES.table_rows`, because the latter is an optimiser estimate on
     * InnoDB and is routinely wrong by tens of percent — an estimate cannot decide whether a table
     * grew by one row.
     *
     * @return array<string, int>
     */
    public function rowCensus(): array
    {
        $census = [];

        foreach ($this->allTables() as $table) {
            $census[$table] = (int) DB::table($table)->count();
        }

        return $census;
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return array<string, array{before:int, after:int, delta:int}> the declared-and-grown tables
     *
     * @throws LegacyScopeRefused when a table OUTSIDE the declared write set grew
     */
    public function assertNoUndeclaredTableGrew(LegacyLoadScope $scope, array $before, array $after): array
    {
        $declaredGrowth = [];
        $undeclared = [];

        foreach ($after as $table => $count) {
            $was = $before[$table] ?? 0;
            $delta = $count - $was;

            if ($delta === 0) {
                continue;
            }

            if (in_array($table, $scope->declaredTables(), true)) {
                $declaredGrowth[$table] = ['before' => $was, 'after' => $count, 'delta' => $delta];

                continue;
            }

            $undeclared[] = sprintf('%s (%d -> %d, %+d)', $table, $was, $count, $delta);
        }

        if ($undeclared !== []) {
            throw new LegacyScopeRefused(
                'Refused: '.count($undeclared).' table(s) outside the declared write set changed '.
                'row count during this run — '.implode('; ', $undeclared).'. Either the write set '.
                'in legacy_pilot.ct_scope.tables is incomplete (fix it, and re-derive the reversal '.
                'manifest from it) or something wrote where it was not supposed to.'
            );
        }

        return $declaredGrowth;
    }

    /** @return array<string,int> table => AUTO_INCREMENT, for the declared write set */
    public function counters(LegacyLoadScope $scope): array
    {
        $out = [];

        foreach ($scope->tables as $table) {
            $out[$table] = $this->autoIncrement($table);
        }

        return $out;
    }

    public function autoIncrement(string $table): int
    {
        $row = DB::selectOne(
            'SELECT AUTO_INCREMENT AS ai FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->databaseName(), $table]
        );

        return $row === null || $row->ai === null ? 0 : (int) $row->ai;
    }

    public function tableExists(string $table): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->databaseName(), $table]
        ) !== null;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->databaseName(), $table, $column]
        ) !== null;
    }

    /** @return list<string> */
    public function allTables(): array
    {
        $rows = DB::select(
            "SELECT TABLE_NAME AS t FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$this->databaseName()]
        );

        return array_map(static fn ($r) => (string) $r->t, $rows);
    }

    public function databaseName(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }
}
