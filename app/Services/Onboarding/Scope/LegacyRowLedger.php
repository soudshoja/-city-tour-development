<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Scope;

use Illuminate\Support\Facades\DB;

/**
 * CD-PORT round 2 — WHICH ROWS THIS LOAD OWNS. The single source of truth for the reversal.
 *
 * ── The defect this replaces ────────────────────────────────────────────────────────────────────
 * Round 1 answered "is this row ours?" with `id BETWEEN <floor> AND <ceiling>`. Adversarial
 * verification showed that answer is wrong for the four declared tables with no `company_id`
 * column — `users`, `agents`, `suppliers` (and the transient `jobs`, now removed from the write
 * set entirely) — and wrong in the worst direction: `legacy:unload` deleted City Travelers rows
 * and reported success, including for a company id that had never been loaded at all. The band
 * had become a trap the port itself set, because `legacy:scope --apply` raises those tables'
 * `AUTO_INCREMENT` to the floor, so every row the dev application mints afterwards falls inside it.
 *
 * ── The replacement: an explicit attribution rule per table, recorded, then obeyed ──────────────
 * Ownership is DERIVED from the data model and then WRITTEN DOWN. {@see self::claim()} walks every
 * declared table, applies that table's rule, and upserts the resulting ids into
 * `legacy_pilot.ct_scope_row` together with the name of the rule that claimed each one.
 * {@see \App\Console\Commands\Legacy\LegacyUnloadCommand} then deletes **by that list and nothing
 * else**.
 *
 * The rules, in the order they must run (each later one may consume an earlier one's output):
 *
 *   | table                        | owned when                                                  |
 *   |------------------------------|-------------------------------------------------------------|
 *   | `companies`                  | `id = <target>`                                              |
 *   | every table with `company_id` | `company_id = <target>`                                      |
 *   | `users`                      | it is the target company's `user_id`, or an owned branch's, or an owned agent's |
 *   | `agents`                     | its `branch_id` is an owned branch, or its `account_id` / `profit_account_id` / `loss_account_id` is an owned account |
 *   | `suppliers`                  | an owned `supplier_companies` row points at it               |
 *
 * Three properties matter about that table, and all three are the point:
 *
 *   1. **No rule mentions the id band.** A City Travelers user minted into the band after arming
 *      satisfies none of them, so it is never claimed and therefore never deleted.
 *   2. **Every rule is a join the schema already enforces**, not a heuristic on a name or a date.
 *      `suppliers` is reached through `supplier_companies.company_id`; `agents` through
 *      `branches.company_id` and `accounts.company_id`. There is no "probably ours".
 *   3. **It is derivational, so it is idempotent and self-healing.** Running `claim()` again after
 *      more of the load has happened adds the new rows and changes nothing else, which is why
 *      every write command can call it on completion without bookkeeping.
 *
 * ── What is deliberately NOT claimable, and what happens to it ──────────────────────────────────
 * `jobs` has nothing but a serialised payload to attribute a row by. Rather than parse a payload
 * to decide whether a DELETE is safe, `jobs` is out of the declared write set altogether (it is in
 * `ct_scope.ignored_growth_tables`, so its growth is still not mistaken for an undeclared write).
 * A queued job is transient application state that the worker consumes; it is not ledger data, and
 * a reversal has no business deleting one.
 */
final class LegacyRowLedger
{
    private const TABLE = 'ct_scope_row';

    /**
     * Derive and persist ownership for every declared table. Idempotent.
     *
     * @return array<string,int> table => number of rows this load owns, after the claim
     */
    public function claim(LegacyLoadScope $scope): array
    {
        $database = DB::connection()->getDatabaseName();
        $summary = [];

        foreach ($this->attribute($scope) as $table => $claims) {
            foreach ($claims as $rowId => $rule) {
                DB::connection('legacy_pilot')->table(self::TABLE)->updateOrInsert(
                    [
                        'company_id' => $scope->companyId,
                        'database_name' => $database,
                        'table_name' => $table,
                        'row_id' => $rowId,
                    ],
                    ['claimed_by' => $rule, 'claimed_at' => now()]
                );
            }

            $summary[$table] = count($claims);
        }

        return $summary;
    }

    /**
     * The attribution rules, evaluated. Returns `table => [rowId => ruleName]`.
     *
     * Kept separate from {@see self::claim()} so a dry run, a test and the unload's own
     * "is this row really ours?" check can all ask the same question without writing anything.
     *
     * @return array<string, array<int,string>>
     */
    public function attribute(LegacyLoadScope $scope): array
    {
        $out = [];
        $companyId = $scope->companyId;

        // 1. companies — its `id` IS the company. (Round 1 treated this as a "global" table with no
        //    company predicate at all, which is how `DELETE FROM companies WHERE id BETWEEN ...`
        //    came to target somebody else's company.)
        if ($this->tableExists('companies') && in_array('companies', $scope->tables, true)) {
            if (DB::table('companies')->where('id', $companyId)->exists()) {
                $out['companies'] = [$companyId => 'companies.id'];
            } else {
                $out['companies'] = [];
            }
        }

        // 2. every declared table that carries company_id — unambiguous.
        foreach ($scope->tables as $table) {
            if ($table === 'companies' || ! $this->tableExists($table) || ! $this->hasColumn($table, 'company_id')) {
                continue;
            }

            $ids = DB::table($table)->where('company_id', $companyId)->pluck('id')->all();
            $out[$table] = [];

            foreach ($ids as $id) {
                $out[$table][(int) $id] = 'company_id';
            }
        }

        $ownedBranches = array_keys($out['branches'] ?? []);
        $ownedAccounts = array_keys($out['accounts'] ?? []);

        // 3. agents — no company_id. Reached through the branch and the account, both of which DO
        //    carry one. An agent belonging to no owned branch and pointing at no owned account is
        //    not ours, whatever its id.
        if ($this->tableExists('agents') && in_array('agents', $scope->tables, true)) {
            $out['agents'] = [];

            if ($ownedBranches !== [] && $this->hasColumn('agents', 'branch_id')) {
                foreach (DB::table('agents')->whereIn('branch_id', $ownedBranches)->pluck('id') as $id) {
                    $out['agents'][(int) $id] = 'agents.branch_id -> branches.company_id';
                }
            }

            if ($ownedAccounts !== []) {
                foreach (['account_id', 'profit_account_id', 'loss_account_id'] as $column) {
                    if (! $this->hasColumn('agents', $column)) {
                        continue;
                    }

                    foreach (DB::table('agents')->whereIn($column, $ownedAccounts)->pluck('id') as $id) {
                        $out['agents'][(int) $id] ??= 'agents.'.$column.' -> accounts.company_id';
                    }
                }
            }
        }

        // 4. suppliers — no company_id either. `supplier_companies` is the join table that carries
        //    one, and it is itself claimed by rule 2 above.
        if ($this->tableExists('suppliers') && in_array('suppliers', $scope->tables, true)) {
            $out['suppliers'] = [];

            if ($this->tableExists('supplier_companies')) {
                $supplierIds = DB::table('supplier_companies')
                    ->where('company_id', $companyId)
                    ->pluck('supplier_id');

                foreach ($supplierIds as $id) {
                    if ($id !== null) {
                        $out['suppliers'][(int) $id] = 'supplier_companies.company_id';
                    }
                }
            }
        }

        // 5. users — the company's own owner, plus whoever owns an owned branch or agent.
        if ($this->tableExists('users') && in_array('users', $scope->tables, true)) {
            $out['users'] = [];

            $ownerId = DB::table('companies')->where('id', $companyId)->value('user_id');

            if ($ownerId !== null) {
                $out['users'][(int) $ownerId] = 'companies.user_id';
            }

            if ($ownedBranches !== []) {
                foreach (DB::table('branches')->whereIn('id', $ownedBranches)->pluck('user_id') as $id) {
                    if ($id !== null) {
                        $out['users'][(int) $id] ??= 'branches.user_id';
                    }
                }
            }

            $ownedAgents = array_keys($out['agents'] ?? []);

            if ($ownedAgents !== [] && $this->hasColumn('agents', 'user_id')) {
                foreach (DB::table('agents')->whereIn('id', $ownedAgents)->pluck('user_id') as $id) {
                    if ($id !== null) {
                        $out['users'][(int) $id] ??= 'agents.user_id';
                    }
                }
            }
        }

        return $out;
    }

    /**
     * The persisted ledger for one table.
     *
     * @return list<int>
     */
    public function ownedIds(LegacyLoadScope $scope, string $table): array
    {
        return array_map('intval', DB::connection('legacy_pilot')->table(self::TABLE)
            ->where('company_id', $scope->companyId)
            ->where('database_name', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->pluck('row_id')
            ->all());
    }

    /** @return array<string, list<int>> */
    public function allOwned(LegacyLoadScope $scope): array
    {
        $rows = DB::connection('legacy_pilot')->table(self::TABLE)
            ->where('company_id', $scope->companyId)
            ->where('database_name', DB::connection()->getDatabaseName())
            ->get(['table_name', 'row_id']);

        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row->table_name][] = (int) $row->row_id;
        }

        return $out;
    }

    public function hasAnything(LegacyLoadScope $scope): bool
    {
        return DB::connection('legacy_pilot')->table(self::TABLE)
            ->where('company_id', $scope->companyId)
            ->where('database_name', DB::connection()->getDatabaseName())
            ->exists();
    }

    /**
     * Every row sitting inside the reserved band that this load does NOT own.
     *
     * On a live City Travelers database this is not hypothetical and not necessarily an error: the
     * dev application mints into `users`/`agents`/`suppliers` continuously, and once
     * `legacy:scope --apply` has raised those counters, everything it mints lands in the band.
     * These are exactly the rows round 1 deleted. They are named so the operator sees them, and
     * `legacy:unload` refuses by default rather than reasoning about them.
     *
     * @return array<string, list<int>>
     */
    public function unownedInBand(LegacyLoadScope $scope, ?array $owned = null): array
    {
        // $owned may be supplied by a caller that has just DERIVED ownership but not yet persisted
        // it — `legacy:adopt --dry-run` is the case. Reading the persisted ledger there would
        // report every row provisioning had just created as "not claimed", which is both alarming
        // and false.
        $owned ??= $this->allOwned($scope);
        $out = [];

        foreach ($scope->tables as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            $inBand = DB::table($table)
                ->whereBetween('id', [$scope->idFloor, $scope->idCeiling])
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            $strays = array_values(array_diff($inBand, $owned[$table] ?? []));

            if ($strays !== []) {
                $out[$table] = $strays;
            }
        }

        return $out;
    }

    public function forget(LegacyLoadScope $scope): int
    {
        return (int) DB::connection('legacy_pilot')->table(self::TABLE)
            ->where('company_id', $scope->companyId)
            ->where('database_name', DB::connection()->getDatabaseName())
            ->delete();
    }

    private function tableExists(string $table): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [DB::connection()->getDatabaseName(), $table]
        ) !== null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::connection()->getDatabaseName(), $table, $column]
        ) !== null;
    }
}
