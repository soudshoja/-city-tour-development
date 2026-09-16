<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Scope;

/**
 * CD-PORT — the immutable description of WHERE one legacy load is allowed to write.
 *
 * ── Why this class exists at all (it has no counterpart in the Akeed-Ai pilot) ──────────────────
 * The legacy-ledger-pilot ran on a throwaway Akeed instance whose application database contained
 * nothing else: company 1 WAS the legacy company, the `accounts` table started empty, and every id
 * in the database belonged to the pilot by construction. None of that holds here. Owner decision
 * O-1 (2026-09-16) puts Como INSIDE `citycomm_city-tour-test` — the database behind
 * development.citycommerce.group and test.citycommerce.group — as a NEW company alongside City
 * Travelers' live-mirrored companies 1, 2 and 3. So every write this pipeline makes now needs two
 * properties the pilot got for free:
 *
 *   1. **It belongs to the target company** — ruling R-CO4: no row belonging to company 1, 2 or 3
 *      is inserted, updated or deleted by anything in this phase.
 *   2. **Its id is inside a reserved band** — because two of the tables in the write set have NO
 *      `company_id` column at all (`suppliers` has none; `invoices` is scoped only indirectly via
 *      `client_id`/`agent_id` — CD0-REVERSAL-MANIFEST §2). For those tables the id band is not a
 *      belt-and-braces extra, it is the ENTIRE scoping mechanism, and it is what makes the
 *      reversal manifest's `DELETE … WHERE id BETWEEN` correct rather than hopeful.
 *
 * ── The band, and why it is 10,000,000 and not something cleverer ───────────────────────────────
 * CD0 measured, on the live database, every `MAX(id)` in the write set and applied the brief's rule
 * ("floor >= 10x live MAX(id), never below 1,000,000 for a table live writes to daily"). The
 * largest `10 x MAX(id)` in the whole set is `journal_entries` at 843,480, so ONE uniform floor of
 * 10,000,000 satisfies the rule on every table simultaneously. Every `id` and FK column in the set
 * is `bigint(20) unsigned`, so the band costs ~9x10^11 of headroom out of ~1.8x10^19 — no width
 * blocker. Coordinator ruling S-A (2026-09-16) keeps this floor.
 *
 * The floor is load-bearing in one direction people get backwards, so it is written down here:
 * `accounts` is `insert_only` in the LIVE->DEV sync
 * (`… LEFT JOIN dev d ON d.id = p.id WHERE d.id IS NULL`). With Como's 1,351-account chart landing
 * at dev's natural next id (1,739) the chart would occupy ids 1,739-3,090, and production's own
 * next ~1,351 accounts would find their ids taken and **silently stop arriving in dev**. At
 * 10,000,001+ every id from 1,662 to 10,000,000 stays free. The floor is what makes the
 * shared-database decision survivable for the chart; it is not cosmetic.
 *
 * ── What this class deliberately does NOT promise ───────────────────────────────────────────────
 * It does not promise to restore each table's `AUTO_INCREMENT` afterwards. CD0 proved on MariaDB
 * 10.11.19 — the server's exact build — that `ALTER TABLE t AUTO_INCREMENT = <lower>` with rows
 * present returns **exit 0, zero warnings, and moves nothing**; InnoDB will not lower the counter
 * below `MAX(id) + 1` and does not say so. Coordinator ruling S-A waives that clause. The counter
 * comes back exactly when the rows go — which is what {@see \App\Console\Commands\Legacy\
 * LegacyUnloadCommand} does, and what CD0-REVERSAL-MANIFEST §5 proved on a fence.
 *
 * Consequently {@see LegacyIdBandGuard} NEVER reports "restored" from an exit code: every counter
 * assertion in this namespace re-reads `information_schema.TABLES` and compares the VALUE
 * (ruling R-CO5 — read output, not exit codes).
 */
final class LegacyLoadScope
{
    /**
     * @param  int  $companyId  the ONE company every row written by this run belongs to
     * @param  int  $idFloor  lowest id this run may mint (inclusive)
     * @param  int  $idCeiling  highest id this run may mint (inclusive)
     * @param  list<string>  $tables  the declared write set — the tables the pipeline may create
     *                                rows in. A row appearing in ANY table outside this list is a
     *                                finding, not a detail: see
     *                                {@see LegacyIdBandGuard::assertNoUndeclaredTableGrew()}.
     * @param  list<string>  $globalTables  the subset of $tables that has NO `company_id` column,
     *                                      and is therefore scoped by the id band ALONE
     * @param  array<string, array{column:string, where:array<string,string>}>  $pivotTables
     *         tables with NO `id` column at all (the Spatie permission pivots). The reversal
     *         manifest's `DELETE … WHERE id BETWEEN` cannot reach them, so each names the FK
     *         column that DOES point into the band, plus any discriminator the delete must carry.
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $idFloor,
        public readonly int $idCeiling,
        public readonly array $tables,
        public readonly array $globalTables,
        public readonly array $pivotTables = [],
    ) {}

    /**
     * Builds the scope from config + an explicit company id.
     *
     * The company id is ALWAYS an explicit argument and is never defaulted from
     * `legacy_pilot.default_company_id`: that key defaults to `1`, and on the City Travelers
     * development database company 1 is City Travelers itself, with 497 accounts, 43,183
     * transactions and 112,662 journal lines. A scope that silently defaulted to it would point
     * the whole pipeline at exactly the rows ruling R-CO4 forbids it to touch. A caller that has
     * no company id gets a refusal, not a default.
     */
    public static function forCompany(int $companyId): self
    {
        if ($companyId <= 0) {
            throw new LegacyScopeRefused(
                'Refused: a legacy load scope needs an explicit positive company id; got '.$companyId.
                '. There is deliberately no default — legacy_pilot.default_company_id is 1, and on '.
                'the City Travelers development database company 1 is City Travelers itself.'
            );
        }

        $floor = (int) config('legacy_pilot.ct_scope.id_floor');
        $ceiling = (int) config('legacy_pilot.ct_scope.id_ceiling');

        if ($floor <= 0 || $ceiling <= $floor) {
            throw new LegacyScopeRefused(
                "Refused: legacy_pilot.ct_scope id band is not configured sanely (floor={$floor}, ".
                "ceiling={$ceiling}). Refusing to run with an unbounded or inverted band."
            );
        }

        /** @var list<string> $tables */
        $tables = array_values(array_map('strval', (array) config('legacy_pilot.ct_scope.tables', [])));
        /** @var list<string> $global */
        $global = array_values(array_map('strval', (array) config('legacy_pilot.ct_scope.global_tables', [])));

        if ($tables === []) {
            throw new LegacyScopeRefused(
                'Refused: legacy_pilot.ct_scope.tables is empty. An empty declared write set would '.
                'make every undeclared-growth check vacuously pass, which is worse than no check.'
            );
        }

        foreach ($global as $g) {
            if (! in_array($g, $tables, true)) {
                throw new LegacyScopeRefused(
                    "Refused: legacy_pilot.ct_scope.global_tables lists '{$g}', which is not in ".
                    'ct_scope.tables. A table scoped by the id band alone must still be inside the '.
                    'declared write set, or nothing checks its ids.'
                );
            }
        }

        /** @var array<string, array{column:string, where:array<string,string>}> $pivots */
        $pivots = (array) config('legacy_pilot.ct_scope.pivot_tables', []);

        foreach ($pivots as $name => $spec) {
            if (! is_array($spec) || ! isset($spec['column']) || ! is_string($spec['column']) || $spec['column'] === '') {
                throw new LegacyScopeRefused(
                    "Refused: legacy_pilot.ct_scope.pivot_tables['{$name}'] has no 'column'. A table ".
                    'with no `id` is unreachable by the id band unless it names the FK that is in '.
                    'the band, so an unnamed one would silently survive the unload.'
                );
            }
        }

        return new self($companyId, $floor, $ceiling, $tables, $global, $pivots);
    }

    /** Every table this run is allowed to change, id-bearing and pivot alike. */
    public function declaredTables(): array
    {
        return array_values(array_unique(array_merge($this->tables, array_keys($this->pivotTables))));
    }

    public function contains(int $id): bool
    {
        return $id >= $this->idFloor && $id <= $this->idCeiling;
    }

    /** True when $table is scoped by the id band alone because it carries no `company_id`. */
    public function isGlobalTable(string $table): bool
    {
        return in_array($table, $this->globalTables, true);
    }

    public function bandDescription(): string
    {
        return number_format($this->idFloor).' .. '.number_format($this->idCeiling);
    }
}
