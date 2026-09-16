<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Scope;

use Illuminate\Support\Facades\DB;

/**
 * CD-PORT — ruling R-CO4 made executable: "no row belonging to company 1, 2 or 3 is inserted,
 * updated or deleted by anything in this plan."
 *
 * Two halves, and they answer different questions:
 *
 *   • {@see self::assertTargetCompany()} — a PRE-WRITE refusal. Runs before the first write of
 *     every legacy:* write command. Refuses if the target company is one of the protected ids, if
 *     it does not exist, or if it already carries rows below the band floor (which would mean it
 *     is not a fresh Como company at all but some real company someone pointed the load at).
 *
 *   • {@see self::fingerprintOutsideBand()} / {@see self::assertOutsideBandUnchanged()} — a
 *     POST-CONDITION. Takes a content fingerprint of every row in the declared write set that is
 *     NOT in the reserved band, and proves, after the load, that those rows are byte-identical.
 *
 * ── Why the fingerprint is BIT_XOR(CRC32(CONCAT_WS(...))) and not COUNT(*) or CHECKSUM TABLE ────
 * A row count proves nothing about an UPDATE: ruling R-CO4 forbids updates and deletes as much as
 * inserts, and all three are invisible to a count that happens to net out. MySQL's own
 * `CHECKSUM TABLE` is no good either — it checksums the WHOLE table, including this run's own new
 * rows, so it would change on every successful load and could never distinguish "we added our
 * rows" from "we also edited theirs".
 *
 * So the fingerprint is computed per table over `WHERE id NOT BETWEEN floor AND ceiling` — i.e.
 * over exactly the rows this run must not have touched — and folds every column of every such row
 * in. `BIT_XOR` is used rather than `SUM` because it is order-independent (no ORDER BY needed, and
 * no dependence on the optimiser's chosen scan order) and because it cannot be fooled by the
 * commonest silent corruption a SUM can absorb, two rows swapping values. Columns are enumerated
 * from `information_schema.COLUMNS` at fingerprint time rather than hard-coded, so a schema change
 * cannot quietly drop a column out of the comparison. NULLs are mapped to a sentinel so that
 * "NULL" and the literal string "NULL" do not collide, and `CONCAT_WS` separates fields with a
 * byte (0x1f, ASCII unit separator) that cannot appear in a normal decimal, date or id, so
 * `('1','23')` and `('12','3')` do not fingerprint alike.
 *
 * The fingerprint is a CRC, so it is not cryptographic and a determined adversary could construct
 * a collision. That is not the threat here: the thing being detected is an accidental write by our
 * own pipeline, and for that a per-row 32-bit fold over a few hundred thousand rows is a far
 * stronger instrument than the row count the brief would otherwise have got.
 */
final class LegacyCompanyGuard
{
    /** The byte used to separate concatenated column values — ASCII unit separator. */
    private const FIELD_SEPARATOR = "\x1f";

    /** The sentinel a NULL column becomes, chosen so it cannot collide with a real value. */
    private const NULL_SENTINEL = "\x00NULL\x00";

    /**
     * The pre-write company gate.
     *
     * @throws LegacyScopeRefused
     */
    public function assertTargetCompany(LegacyLoadScope $scope): void
    {
        /** @var list<int> $protected */
        $protected = array_map('intval', (array) config('legacy_pilot.ct_scope.protected_company_ids', []));

        if (in_array($scope->companyId, $protected, true)) {
            throw new LegacyScopeRefused(
                'Refused before the first write: company '.$scope->companyId.' is on the protected '.
                'list ('.implode(', ', $protected).'). Ruling R-CO4 makes City Travelers\' own '.
                'companies read-only for the whole phase; the legacy load may only target a company '.
                'created for it.'
            );
        }

        $company = DB::table('companies')->where('id', $scope->companyId)->first();

        if ($company === null) {
            throw new LegacyScopeRefused(
                'Refused before the first write: company '.$scope->companyId.' does not exist in '.
                'database `'.DB::connection()->getDatabaseName().'`. Create it inside the reserved '.
                'band first (`php artisan legacy:scope --company='.$scope->companyId.' --apply`, '.
                'then the provisioning step) — this command will not create a company as a '.
                'side-effect of being pointed at a missing one.'
            );
        }

        if (! $scope->contains((int) $company->id)) {
            throw new LegacyScopeRefused(
                'Refused before the first write: company '.$scope->companyId.' exists but its own id '.
                'is outside the reserved band '.$scope->bandDescription().'. A company row that the '.
                'reversal manifest\'s `DELETE … WHERE id BETWEEN` cannot reach is, by definition, '.
                'not a company this load created — so it is not one this load may write to.'
            );
        }
    }

    /**
     * ROUND 2 - capture the before-picture and PERSIST it.
     *
     * Round 1's fingerprint had exactly one caller: a test. Nothing on the deployed path ran it,
     * and nothing stored a "before" side, so after a load there was no recoverable baseline to
     * compare against - the R-CO4 post-condition could only ever be run by hand. That is how the
     * `coa_linkage_findings` / `settings` omission was found, and it is not a method anyone should
     * have to remember to call.
     *
     * Called by `legacy:scope --apply`, before the first row of the load exists. It never
     * overwrites an existing `pre_load` row: the first capture is the only true pre-load state, and
     * a second arming must not quietly replace it with a post-load one.
     *
     * @return array<string, array{rows:int, xor:string, max_id:int}>
     */
    public function captureBaseline(LegacyLoadScope $scope): array
    {
        $database = DB::connection()->getDatabaseName();
        $captured = [];

        foreach ($scope->tables as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            $maxId = (int) DB::table($table)->max('id');
            $fp = $this->fingerprintUpTo($table, $maxId);
            $captured[$table] = $fp + ['max_id' => $maxId];

            $existing = DB::connection('legacy_pilot')->table('ct_scope_fingerprint')
                ->where('company_id', $scope->companyId)
                ->where('database_name', $database)
                ->where('table_name', $table)
                ->where('stage', 'pre_load')
                ->exists();

            if ($existing) {
                continue;
            }

            DB::connection('legacy_pilot')->table('ct_scope_fingerprint')->insert([
                'company_id' => $scope->companyId,
                'database_name' => $database,
                'table_name' => $table,
                'stage' => 'pre_load',
                'max_id_at_capture' => $maxId,
                'row_count' => $fp['rows'],
                'content_xor' => $fp['xor'],
                'captured_at' => now(),
            ]);
        }

        return $captured;
    }

    public function hasBaseline(LegacyLoadScope $scope): bool
    {
        return DB::connection('legacy_pilot')->table('ct_scope_fingerprint')
            ->where('company_id', $scope->companyId)
            ->where('database_name', DB::connection()->getDatabaseName())
            ->where('stage', 'pre_load')
            ->exists();
    }

    /**
     * ROUND 2 - the R-CO4 post-condition, run on the DEPLOYED path by every write command.
     *
     * Compares each declared table, scoped to `id <= max_id_at_capture`, against the persisted
     * baseline. That scoping is the whole design, and it is what makes the check usable on a live
     * database:
     *
     *   - a row that EXISTED before the load and has since been updated, deleted or overwritten is
     *     caught. That is exactly what ruling R-CO4 forbids, and a row count alone cannot see an
     *     update;
     *   - a row that APPEARS afterwards is not a failure here, because City Travelers own dev
     *     application writes continuously and, once `legacy:scope --apply` has raised the counters,
     *     everything it writes lands inside the reserved band. Those rows are handled by
     *     {@see LegacyRowLedger::unownedInBand()}, which names them, and they are never deleted.
     *
     * Round 1 computed over `id NOT BETWEEN <band>` instead, which excluded a City Travelers row
     * sitting INSIDE the band from the "untouched" proof by construction - the evidence could not
     * see the very rows the reversal was about to delete.
     *
     * @throws LegacyScopeRefused
     */
    public function assertBaselineIntact(LegacyLoadScope $scope): void
    {
        $database = DB::connection()->getDatabaseName();

        $baseline = DB::connection('legacy_pilot')->table('ct_scope_fingerprint')
            ->where('company_id', $scope->companyId)
            ->where('database_name', $database)
            ->where('stage', 'pre_load')
            ->get();

        if ($baseline->isEmpty()) {
            throw new LegacyScopeRefused(
                'Refused: no pre-load baseline is recorded for company '.$scope->companyId.' on `'.
                $database.'`. `legacy:scope --apply` captures it; without it there is nothing to '.
                'prove City Travelers rows are untouched AGAINST, and an unprovable claim is not one '.
                'this pipeline makes.'
            );
        }

        $diffs = [];

        foreach ($baseline as $row) {
            $table = (string) $row->table_name;

            if (! $this->tableExists($table)) {
                $diffs[] = $table.' (table has disappeared)';

                continue;
            }

            $now = $this->fingerprintUpTo($table, (int) $row->max_id_at_capture);

            if ($now['rows'] !== (int) $row->row_count || $now['xor'] !== (string) $row->content_xor) {
                $diffs[] = sprintf(
                    '%s (rows %d -> %d, fingerprint %s -> %s, over id <= %d)',
                    $table,
                    (int) $row->row_count,
                    $now['rows'],
                    (string) $row->content_xor,
                    $now['xor'],
                    (int) $row->max_id_at_capture
                );
            }
        }

        if ($diffs !== []) {
            throw new LegacyScopeRefused(
                'Refused: '.count($diffs).' table(s) changed among the rows that existed BEFORE this '.
                'load - '.implode('; ', $diffs).'. Ruling R-CO4 forbids this pipeline from inserting, '.
                'updating or deleting a single row that is not its own.'
            );
        }
    }

    /**
     * @return array{rows:int, xor:string}
     */
    private function fingerprintUpTo(string $table, int $maxId): array
    {
        $columns = $this->columnsOf($table);

        if ($columns === [] || $maxId <= 0) {
            return ['rows' => 0, 'xor' => '0'];
        }

        $expr = $this->rowExpression($columns);

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c, COALESCE(BIT_XOR(CRC32({$expr})), 0) AS x
               FROM `{$table}` WHERE `id` <= ?",
            [$maxId]
        );

        return ['rows' => (int) $row->c, 'xor' => (string) $row->x];
    }

    private function tableExists(string $table): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [DB::connection()->getDatabaseName(), $table]
        ) !== null;
    }

    /**
     * @return array<string, array{rows:int, xor:string}>
     */
    public function fingerprintOutsideBand(LegacyLoadScope $scope): array
    {
        $out = [];

        foreach ($scope->tables as $table) {
            $columns = $this->columnsOf($table);

            if ($columns === []) {
                continue;
            }

            $expr = $this->rowExpression($columns);

            $row = DB::selectOne(
                "SELECT COUNT(*) AS c, COALESCE(BIT_XOR(CRC32({$expr})), 0) AS x
                   FROM `{$table}` WHERE `id` NOT BETWEEN ? AND ?",
                [$scope->idFloor, $scope->idCeiling]
            );

            $out[$table] = ['rows' => (int) $row->c, 'xor' => (string) $row->x];
        }

        return $out;
    }

    /**
     * @param  array<string, array{rows:int, xor:string}>  $before
     * @param  array<string, array{rows:int, xor:string}>  $after
     *
     * @throws LegacyScopeRefused
     */
    public function assertOutsideBandUnchanged(array $before, array $after): void
    {
        $diffs = [];

        foreach ($before as $table => $was) {
            $now = $after[$table] ?? ['rows' => -1, 'xor' => 'missing'];

            if ($was['rows'] !== $now['rows'] || $was['xor'] !== $now['xor']) {
                $diffs[] = sprintf(
                    '%s (rows %d -> %d, fingerprint %s -> %s)',
                    $table,
                    $was['rows'],
                    $now['rows'],
                    $was['xor'],
                    $now['xor']
                );
            }
        }

        foreach ($after as $table => $now) {
            if (! array_key_exists($table, $before)) {
                $diffs[] = sprintf('%s (appeared, rows %d)', $table, $now['rows']);
            }
        }

        if ($diffs !== []) {
            throw new LegacyScopeRefused(
                'Refused: '.count($diffs).' table(s) changed OUTSIDE the reserved id band during '.
                'this run — '.implode('; ', $diffs).'. Ruling R-CO4 forbids this load from '.
                'inserting, updating or deleting a single row that is not its own.'
            );
        }
    }

    /**
     * Column names of $table, in a stable order, so a fingerprint taken before a run and one taken
     * after fold the same fields in the same sequence.
     *
     * @return list<string>
     */
    private function columnsOf(string $table): array
    {
        $rows = DB::select(
            'SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [DB::connection()->getDatabaseName(), $table]
        );

        return array_map(static fn ($r) => (string) $r->c, $rows);
    }

    /** @param  list<string>  $columns */
    private function rowExpression(array $columns): string
    {
        $sep = '0x'.bin2hex(self::FIELD_SEPARATOR);
        $null = '0x'.bin2hex(self::NULL_SENTINEL);

        $parts = array_map(
            static fn (string $c): string => "COALESCE(CAST(`{$c}` AS CHAR), {$null})",
            $columns
        );

        return 'CONCAT_WS('.$sep.', '.implode(', ', $parts).')';
    }
}
