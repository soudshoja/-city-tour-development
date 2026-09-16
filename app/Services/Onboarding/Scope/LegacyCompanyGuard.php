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
