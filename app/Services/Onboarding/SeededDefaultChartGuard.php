<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\SystemAccount;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * legacy-ledger-pilot LP1.1 pre-flight — LP0 fact: "Akeed 2" staging is NOT
 * an empty database. The stock DatabaseSeeder's EntitySeeder already ran
 * CoaSeeder (184 default accounts) and SystemAccountsSeeder (purpose
 * mappings pointed at them). Prod, by contrast, has an empty `accounts`
 * table. `legacy:import-coa` must never silently pile the legacy 1,351-node
 * tree on top of that default chart, and must never delete real, in-use
 * accounts either.
 *
 * ── COORDINATOR RULING R1 (2026-09-07, LP1c) ────────────────────────────
 * The first version of this guard identified "seeder-created" accounts from
 * a positive allow-list (CoaSeeder's own literal codes + the documented
 * EnsureSystemLeaves codes + a structural match on the 12 per-service
 * "X Booking Revenue" leaves) and REFUSED the whole replace on anything it
 * could not name. Staging run #3 proved that rule too narrow: the stock
 * DatabaseSeeder chain mints 9 MORE accounts that CoaSeeder never declares —
 *
 *   - 3 supplier payable leaves (Amadeus / Magic Holiday / TBO Holiday),
 *     created by SupplierCompanyController::activateSupplierProcess(), which
 *     EntitySeeder calls;
 *   - 5 payment-gateway leaves (Tap / MyFatoorah / Hesabe, then Knet /
 *     uPayment) under the 'Payment Gateway' parent;
 *   - 1 agent/party leaf under the branch receivable group.
 *
 * — so a freshly seeded staging company could never be replaced at all.
 *
 * The rule is now: with --replace-seeded, APP_ENV in local/testing/staging,
 * and PROVABLY ZERO ledger activity for the company, the ENTIRE chart is
 * treated as seeder-born and removed. "Provably zero" is the load-bearing
 * half — see assertNoLedgerActivity(): zero journal_entries, zero
 * transactions, and zero rows in any configured activity table
 * (config('legacy_pilot.import.activity_tables')) either scoped to the
 * company or referencing one of its accounts through a real FK. If ANY
 * ledger activity exists the guard refuses exactly as before; nothing about
 * that refusal is relaxed.
 *
 * Deleting more rows demands a BETTER audit trail, not a worse one:
 *
 *   1. Every Account row removed is first recorded in
 *      legacy_pilot.seeded_chart_removed (id, code, name, type, level and
 *      the owner FKs the row carried), including a `recognised_default`
 *      flag saying whether the OLD narrow allow-list would have known it.
 *   2. Every dependent foreign key pointing at a removed account —
 *      suppliers / agents / gateways / users / charges, discovered from
 *      information_schema rather than a hardcoded list, so a column added
 *      later cannot be missed — is NULLED and recorded in
 *      legacy_pilot.seeded_chart_fk_nulled. A dangling FK is never left
 *      behind, and a non-nullable dependent FK refuses the whole replace
 *      rather than being force-deleted.
 *   3. Those nulled rows are the "needs re-linking at go-live" report.
 *      Re-pointing them automatically at the imported legacy leaf is only
 *      permitted where a map_party row POSITIVELY identifies the dependent
 *      row (see relinkNulledReferences()). Matching by NAME is FORBIDDEN:
 *      the legacy export is pseudonymised, so a name match would be a
 *      fabricated mapping, which is precisely what this phase's "never
 *      invent a mapping" rule exists to prevent.
 *
 * ── LP1d (2026-09-07): two defects staging run #4a found here ───────────
 * 1. DELETE ORDER. The removal used to iterate `sortByDesc('level')`, which
 *    keeps children ahead of parents only while every row's `level` agrees
 *    with its own parent chain. On staging one auto-provisioned account is
 *    stored at its PARENT's level, so the order could put the parent first
 *    and the raw delete hit accounts_parent_id_foreign (SQLSTATE 23000,
 *    error 1451). The order is now computed from `parent_id` itself
 *    (topologicalDeleteOrder()) and never consults `level`. The wrong levels
 *    are still a data defect in whatever wrote those rows, so they are
 *    REPORTED — lastLevelInconsistencies(), also persisted on the run row —
 *    rather than silently tolerated or silently repaired.
 * 2. AUDIT-TRAIL COHERENCE. seeded_chart_removed / seeded_chart_fk_nulled
 *    live on the `legacy_pilot` connection while the deletions live on the
 *    default one, and no transaction can span both. When run #4a's first
 *    attempt crashed, its account deletions rolled back but its audit rows
 *    did not, so the table ended up holding 372 rows describing 186
 *    accounts. Now: the audit rows are written only AFTER the default
 *    connection commits, every row of one attempt carries the same
 *    `run_key`, the write purges that key first (so a retry under the same
 *    key replaces rather than doubles), and `seeded_chart_removal_run`
 *    records the attempt — with `completed_at` NULL for one that never
 *    finished, so an orphan trail is identifiable and purgeable
 *    (incompleteRemovalRuns(), purgeRemovalAudit()).
 *
 * KNOWN, DELIBERATELY UNFIXED (R1): the three supplier payable leaves all
 * carry the SAME code (2131). SupplierCompanyController::
 * activateSupplierProcess() computes a new leaf's code as
 * `(int) $parent->code + 1` — from the PARENT's code, not from the existing
 * siblings — so every supplier activated under one payable group collides.
 * That is a pre-existing seeder/controller defect. This guard REPORTS it
 * (duplicateRemovedCodes()) and does not fix it here; fixing it belongs to
 * whoever owns that controller, not to the legacy-ledger pilot.
 */
final class SeededDefaultChartGuard
{
    /** @var array<int, string> */
    private const SUPPLEMENTAL_ENSURE_SYSTEM_LEAVES_CODES = [
        '1215', '2215', '4132', '5127', '5144', '5145', '5222', '2201',
    ];

    /**
     * Columns on `accounts` that reference `accounts` itself. They are never
     * "dependent FKs to null" — the whole subtree is being removed and the
     * delete order (children before parents, computed from parent_id by
     * topologicalDeleteOrder()) already keeps them consistent.
     */
    private const SELF_REFERENCING_ACCOUNT_COLUMNS = ['parent_id', 'root_id', 'reference_id'];

    /** @var array<int, array{0:string,1:string}>|null memoized information_schema lookup */
    private ?array $accountReferencingColumns = null;

    /** LP1d — the run key the most recent replaceSeededChart() stamped on its audit rows. */
    private ?string $lastRunKey = null;

    /** @var array<int, string> LP1d — accounts.level rows contradicting their own parent_id. */
    private array $lastLevelInconsistencies = [];

    public function hasExistingAccounts(int $companyId): bool
    {
        return Account::where('company_id', $companyId)->exists();
    }

    public function hasLedgerActivity(int $companyId): bool
    {
        return $this->ledgerActivityReasons($companyId) !== [];
    }

    /**
     * Every reason this company's chart counts as IN USE. Empty means the
     * chart is provably unused and R1's whole-chart removal is permitted.
     *
     * @return array<int, string>
     */
    public function ledgerActivityReasons(int $companyId): array
    {
        $reasons = [];

        if (JournalEntry::where('company_id', $companyId)->exists()) {
            $reasons[] = 'journal_entries rows exist for this company';
        }

        if (Transaction::where('company_id', $companyId)->exists()) {
            $reasons[] = 'transactions rows exist for this company';
        }

        $accountIds = Account::where('company_id', $companyId)->pluck('id');
        $activityTables = (array) config('legacy_pilot.import.activity_tables', []);

        foreach ($activityTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'company_id')
                && DB::table($table)->where('company_id', $companyId)->exists()) {
                $reasons[] = "{$table} rows exist for this company";

                continue;
            }

            foreach ($this->accountReferencingColumnsFor($table) as $column) {
                if ($accountIds->isNotEmpty()
                    && DB::table($table)->whereIn($column, $accountIds)->exists()) {
                    $reasons[] = "{$table}.{$column} references an account of this company";

                    break;
                }
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * The OLD narrow allow-list. It no longer DECIDES anything under R1 — it
     * survives purely so seeded_chart_removed.recognised_default can say, per
     * row, whether the removal was of a documented default or of one of the
     * 9 party/gateway accounts that motivated the ruling.
     *
     * @return Collection<int, Account>
     */
    public function seededAccounts(int $companyId): Collection
    {
        $codes = $this->seederCodes();

        return Account::where('company_id', $companyId)
            ->where(function ($q) use ($codes) {
                $q->whereIn('code', $codes)
                    ->orWhere(function ($q2) {
                        // Structural match for the 12 computed-code per-service
                        // revenue leaves: direct child of a 'Direct Income'
                        // parent, named "<Ucfirst Type> Booking Revenue".
                        $q2->where('name', 'like', '% Booking Revenue')
                            ->whereHas('parent', fn ($p) => $p->where('name', 'Direct Income'));
                    });
            })
            ->get();
    }

    /**
     * @return Collection<int, Account> accounts for the company that the old
     *                                  narrow allow-list did not recognise.
     *                                  Reporting only under R1.
     */
    public function unrecognisedAccounts(int $companyId): Collection
    {
        $seededIds = $this->seededAccounts($companyId)->pluck('id');

        return Account::where('company_id', $companyId)
            ->whereNotIn('id', $seededIds)
            ->get();
    }

    /**
     * R1: remove the WHOLE chart for a company whose chart is provably
     * unused, after recording every removed row and nulling (never
     * dangling) every dependent FK.
     *
     * @return int number of accounts removed
     *
     * @throws RuntimeException on any of the refusal conditions above.
     */
    public function replaceSeededChart(int $companyId, ?string $runKey = null): int
    {
        $this->assertNonProductionEnvironment();
        $this->assertNoLedgerActivity($companyId);

        $accounts = Account::where('company_id', $companyId)->get();

        if ($accounts->isEmpty()) {
            return 0;
        }

        // Fail fast: accounts are never deleted without somewhere to record
        // the deletion, and finding that out AFTER the delete would be the
        // worst possible moment.
        $this->assertRemovalAuditTablesExist();

        $this->lastRunKey = $runKey ?? $this->newRunKey();
        $this->lastLevelInconsistencies = $this->levelInconsistencies($accounts);

        $recognisedIds = $this->seededAccounts($companyId)->pluck('id')->all();
        $accountIds = $accounts->pluck('id')->all();

        $removedRows = [];
        $nulledRows = [];

        // LP1d. The default-connection work commits FIRST; the legacy_pilot
        // audit rows are written only afterwards. The two connections cannot
        // share a transaction, so the ordering is the coherence: a failed
        // attempt now leaves NO audit rows at all, instead of the orphan
        // 186-row trail staging run #4a's crashed first attempt left behind.
        DB::transaction(function () use ($accounts, $accountIds, $recognisedIds, $companyId, &$removedRows, &$nulledRows) {
            $removedRows = $this->removedAccountRows($companyId, $accounts, $recognisedIds);
            $nulledRows = $this->nullDependentReferences($companyId, $accounts);

            SystemAccount::where('company_id', $companyId)->whereIn('account_id', $accountIds)->delete();

            foreach ($this->topologicalDeleteOrder($accounts) as $account) {
                $account->delete();
            }
        });

        $this->writeRemovalAudit($companyId, $this->lastRunKey, $removedRows, $nulledRows);

        return $accounts->count();
    }

    /**
     * The run key the last replaceSeededChart() call stamped on its audit
     * rows — the handle for purgeRemovalAudit() and for reporting.
     */
    public function lastRunKey(): ?string
    {
        return $this->lastRunKey;
    }

    /**
     * LP1d. `accounts.level` rows that contradict their own parent_id, as
     * human-readable strings. REPORTED, never repaired: the delete order no
     * longer trusts `level` (see topologicalDeleteOrder()), but a level that
     * disagrees with the tree is still a defect in whatever wrote the row and
     * the operator should see it.
     *
     * On staging this is account code 1361 ("City Travelers HQ"'s child),
     * stored at level 3 — the same level as its own parent — by the same
     * auto-provisioning path that also gave three supplier leaves one shared
     * code.
     *
     * @return array<int, string>
     */
    public function lastLevelInconsistencies(): array
    {
        return $this->lastLevelInconsistencies;
    }

    /**
     * LP1d. Remove every audit row a given attempt wrote. Idempotency's other
     * half: re-running under the same run_key purges first, and an attempt
     * whose `seeded_chart_removal_run.completed_at` is NULL can be cleaned
     * without touching real history.
     */
    public function purgeRemovalAudit(int $companyId, string $runKey): void
    {
        foreach (['seeded_chart_removed', 'seeded_chart_fk_nulled'] as $table) {
            if (Schema::connection('legacy_pilot')->hasTable($table)) {
                DB::connection('legacy_pilot')->table($table)
                    ->where('company_id', $companyId)->where('run_key', $runKey)->delete();
            }
        }

        if (Schema::connection('legacy_pilot')->hasTable('seeded_chart_removal_run')) {
            DB::connection('legacy_pilot')->table('seeded_chart_removal_run')
                ->where('company_id', $companyId)->where('run_key', $runKey)->delete();
        }
    }

    /**
     * LP1d. Attempts that started and never recorded a completion — their
     * rows in the two audit tables describe work that may never have
     * happened, so they are listed for the operator (and are safe to
     * purgeRemovalAudit()).
     *
     * @return array<int, object>
     */
    public function incompleteRemovalRuns(int $companyId): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('seeded_chart_removal_run')) {
            return [];
        }

        return DB::connection('legacy_pilot')->table('seeded_chart_removal_run')
            ->where('company_id', $companyId)
            ->whereNull('completed_at')
            ->orderBy('started_at')
            ->get()
            ->all();
    }

    /**
     * LP1d. The delete order, computed from `parent_id` — NEVER from
     * `accounts.level`.
     *
     * The old order was `sortByDesc('level')`, which is correct only while
     * every row's `level` agrees with its own parent chain. Staging run #4a
     * proved it does not: an auto-provisioned account (code 1361) is stored
     * at level 3, the SAME level as its parent, so "deepest first" could
     * order the parent before the child and the raw delete hit
     * `accounts_parent_id_foreign` with SQLSTATE 23000 / error 1451. The
     * level column is a cached denormalisation; parent_id is the real edge,
     * so the order is derived from the edges: repeatedly emit every remaining
     * account that no remaining account still names as its parent.
     *
     * A cycle (or a parent outside the removal set that is itself in the set —
     * impossible for a whole-chart removal, but not for a future partial one)
     * cannot be ordered safely, so it REFUSES rather than emitting an order
     * the FK will reject halfway through.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, Account>
     */
    public function topologicalDeleteOrder(Collection $accounts): array
    {
        /** @var array<int, Account> $remaining */
        $remaining = [];

        foreach ($accounts as $account) {
            $remaining[(int) $account->id] = $account;
        }

        $ordered = [];

        while ($remaining !== []) {
            $childCounts = [];

            foreach ($remaining as $account) {
                $parentId = $account->parent_id === null ? null : (int) $account->parent_id;

                if ($parentId !== null && isset($remaining[$parentId])) {
                    $childCounts[$parentId] = ($childCounts[$parentId] ?? 0) + 1;
                }
            }

            $batch = [];

            foreach ($remaining as $id => $account) {
                if (! isset($childCounts[$id])) {
                    $batch[] = $account;
                }
            }

            if ($batch === []) {
                throw new RuntimeException(
                    'Refusing --replace-seeded: the accounts to remove contain a parent_id cycle — '.
                    count($remaining).' account(s) (ids '.implode(', ', array_slice(array_keys($remaining), 0, 10)).
                    ') can never be deleted child-first. Resolve the cycle before replacing the chart.'
                );
            }

            foreach ($batch as $account) {
                $ordered[] = $account;
                unset($remaining[(int) $account->id]);
            }
        }

        return $ordered;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, string>
     */
    private function levelInconsistencies(Collection $accounts): array
    {
        $byId = $accounts->keyBy('id');
        $problems = [];

        foreach ($accounts as $account) {
            $parentId = $account->parent_id === null ? null : (int) $account->parent_id;

            if ($parentId === null) {
                if ((int) $account->level !== 1) {
                    $problems[] = "account #{$account->id} (code {$account->code}) is a root but is stored at level {$account->level}, not 1";
                }

                continue;
            }

            $parent = $byId->get($parentId);

            if ($parent === null) {
                continue;
            }

            if ((int) $account->level !== ((int) $parent->level) + 1) {
                $problems[] = "account #{$account->id} (code {$account->code}) is at level {$account->level} but its parent ".
                    "#{$parent->id} (code {$parent->code}) is at level {$parent->level}";
            }
        }

        return $problems;
    }

    private function newRunKey(): string
    {
        return 'rsc-'.now()->format('Ymd-His').'-'.substr(bin2hex(random_bytes(6)), 0, 12);
    }

    /**
     * The "needs re-linking at go-live" list — every dependent FK the
     * removal nulled and has not since re-pointed.
     *
     * @return array<int, object>
     */
    public function pendingRelinks(int $companyId): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('seeded_chart_fk_nulled')) {
            return [];
        }

        return DB::connection('legacy_pilot')->table('seeded_chart_fk_nulled')
            ->where('company_id', $companyId)
            ->where('status', 'nulled')
            ->orderBy('table_name')
            ->orderBy('row_id')
            ->get()
            ->all();
    }

    /**
     * R1's post-import re-link step. A nulled dependent FK is re-pointed at
     * the imported legacy leaf ONLY when a legacy_pilot.map_party row
     * positively identifies the dependent row — i.e. the dependent row
     * itself carries the legacy partner id the registry is keyed on.
     *
     * Matching by NAME is forbidden (the export is pseudonymised, so a name
     * match is a fabricated mapping). No Akeed-side supplier / agent /
     * gateway row in this codebase carries a legacy partner id, so in
     * practice every entry stays 'nulled' and is reported for a human to
     * re-link at go-live. That is the honest outcome, not a gap: this method
     * exists so the moment such an identity column DOES exist, the re-link
     * is mechanical rather than a name guess.
     *
     * @return array{relinked:int,pending:int}
     */
    public function relinkNulledReferences(int $companyId): array
    {
        $pending = $this->pendingRelinks($companyId);
        $relinked = 0;

        foreach ($pending as $row) {
            $partyId = $this->legacyPartnerIdFor($row->table_name, (int) $row->row_id);

            if ($partyId === null) {
                continue;
            }

            $accountId = $this->mappedLeafForParty($companyId, $partyId);

            if ($accountId === null) {
                continue;
            }

            DB::table($row->table_name)->where('id', $row->row_id)->update([$row->column_name => $accountId]);

            DB::connection('legacy_pilot')->table('seeded_chart_fk_nulled')
                ->where('id', $row->id)
                ->update([
                    'status' => 'relinked',
                    'relinked_account_id' => $accountId,
                    'note' => "re-pointed via legacy_pilot.map_party partner_id_fk={$partyId}",
                    'updated_at' => now(),
                ]);

            $relinked++;
        }

        return ['relinked' => $relinked, 'pending' => count($pending) - $relinked];
    }

    /**
     * R1 reporting: codes that more than one REMOVED account shared. On real
     * staging this is the three supplier leaves all coded 2131 — a
     * pre-existing SupplierCompanyController defect, reported here, not
     * fixed here.
     *
     * @return array<string, int> code => how many removed rows carried it
     */
    public function duplicateRemovedCodes(int $companyId): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('seeded_chart_removed')) {
            return [];
        }

        return DB::connection('legacy_pilot')->table('seeded_chart_removed')
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->selectRaw('code, count(*) as c')
            ->groupBy('code')
            ->havingRaw('count(*) > 1')
            ->pluck('c', 'code')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    private function assertNoLedgerActivity(int $companyId): void
    {
        $reasons = $this->ledgerActivityReasons($companyId);

        if ($reasons === []) {
            return;
        }

        throw new RuntimeException(
            "Refusing --replace-seeded for company {$companyId}: the chart is in use — ".
            implode('; ', $reasons).
            '. Replacing the chart under live ledger activity would orphan real rows.'
        );
    }

    private function assertRemovalAuditTablesExist(): void
    {
        foreach (['seeded_chart_removed', 'seeded_chart_fk_nulled', 'seeded_chart_removal_run'] as $table) {
            if (Schema::connection('legacy_pilot')->hasTable($table)) {
                continue;
            }

            throw new RuntimeException(
                "Refusing --replace-seeded: legacy_pilot.{$table} does not exist. Run ".
                '`php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot` first — '.
                'accounts are never deleted without an audit trail to delete them into.'
            );
        }
    }

    /**
     * LP1d. BUILDS the seeded_chart_removed payload; it no longer writes it.
     * The write happens in writeRemovalAudit(), after the default connection
     * has committed — see replaceSeededChart().
     *
     * @param  Collection<int, Account>  $accounts
     * @param  array<int, int>  $recognisedIds
     * @return array<int, array<string, mixed>>
     */
    private function removedAccountRows(int $companyId, Collection $accounts, array $recognisedIds): array
    {
        $now = now();
        $rows = [];

        foreach ($accounts as $account) {
            $rows[] = [
                'company_id' => $companyId,
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'report_type' => $account->report_type,
                'level' => $account->level,
                'is_group' => (bool) $account->is_group,
                'parent_id' => $account->parent_id,
                'root_id' => $account->root_id,
                'branch_id' => $this->accountColumn($account, 'branch_id'),
                'agent_id' => $this->accountColumn($account, 'agent_id'),
                'supplier_company_id' => $this->accountColumn($account, 'supplier_company_id'),
                'reference_id' => $this->accountColumn($account, 'reference_id'),
                'recognised_default' => in_array($account->id, $recognisedIds, true),
                'removed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * LP1d. Write the whole audit trail for one attempt, under one run_key,
     * AFTER the default connection has committed the deletions those rows
     * describe.
     *
     * Idempotent per (company_id, run_key): the key's rows are purged before
     * the insert, so re-running an attempt under the same key replaces its
     * trail rather than doubling it. The `seeded_chart_removal_run` row is
     * inserted with `completed_at` NULL and stamped only once both tables are
     * written, so a crash mid-write is still visible as an unfinished attempt.
     *
     * @param  array<int, array<string, mixed>>  $removedRows
     * @param  array<int, array<string, mixed>>  $nulledRows
     */
    private function writeRemovalAudit(int $companyId, string $runKey, array $removedRows, array $nulledRows): void
    {
        $this->purgeRemovalAudit($companyId, $runKey);

        $now = now();

        DB::connection('legacy_pilot')->table('seeded_chart_removal_run')->insert([
            'run_key' => $runKey,
            'company_id' => $companyId,
            'accounts_removed' => count($removedRows),
            'fks_nulled' => count($nulledRows),
            'level_inconsistencies' => $this->lastLevelInconsistencies === []
                ? null
                : implode("\n", $this->lastLevelInconsistencies),
            'started_at' => $now,
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (array_chunk(array_map(
            static fn (array $row): array => ['run_key' => $runKey] + $row,
            $removedRows
        ), 200) as $chunk) {
            DB::connection('legacy_pilot')->table('seeded_chart_removed')->insert($chunk);
        }

        foreach (array_chunk(array_map(
            static fn (array $row): array => ['run_key' => $runKey] + $row,
            $nulledRows
        ), 200) as $chunk) {
            DB::connection('legacy_pilot')->table('seeded_chart_fk_nulled')->insert($chunk);
        }

        DB::connection('legacy_pilot')->table('seeded_chart_removal_run')
            ->where('run_key', $runKey)
            ->update(['completed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Null every dependent FK that points at one of the accounts about to be
     * removed, and record each one. A NON-nullable dependent FK refuses the
     * whole replace — deleting the owner row to make the chart removable
     * would be destroying data this command was never asked to touch.
     *
     * LP1d: nulls the FKs (default connection, inside the caller's
     * transaction) and RETURNS the audit payload; the legacy_pilot write is
     * writeRemovalAudit()'s job, after that transaction commits.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function nullDependentReferences(int $companyId, Collection $accounts): array
    {
        $accountIds = $accounts->pluck('id')->all();
        $byId = $accounts->keyBy('id');
        $now = now();
        $records = [];

        foreach ($this->accountReferencingColumns() as [$table, $column]) {
            if ($table === 'accounts' && in_array($column, self::SELF_REFERENCING_ACCOUNT_COLUMNS, true)) {
                continue;
            }

            if ($table === 'system_accounts') {
                // Purpose MAPPINGS, not owner references: deleted wholesale by
                // replaceSeededChart() (a mapping to a removed account is
                // meaningless, and account_id there is NOT NULL).
                continue;
            }

            $rows = DB::table($table)->whereIn($column, $accountIds)->get(['id', $column]);

            if ($rows->isEmpty()) {
                continue;
            }

            if (! $this->columnIsNullable($table, $column)) {
                throw new RuntimeException(
                    "Refusing --replace-seeded for company {$companyId}: {$rows->count()} row(s) in ".
                    "{$table} reference an account of this company through NOT NULL column {$column}. ".
                    'The FK can neither be nulled nor left dangling, and this command never deletes '.
                    "rows outside `accounts` — resolve {$table} manually first."
                );
            }

            foreach ($rows as $row) {
                $account = $byId->get((int) $row->$column);

                $records[] = [
                    'company_id' => $companyId,
                    'table_name' => $table,
                    'column_name' => $column,
                    'row_id' => (int) $row->id,
                    'old_account_id' => (int) $row->$column,
                    'old_account_code' => $account?->code,
                    'old_account_name' => $account?->name,
                    'status' => 'nulled',
                    'relinked_account_id' => null,
                    'note' => 'needs re-linking at go-live — name matching is forbidden (pseudonymised export)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table($table)->whereIn($column, $accountIds)->update([$column => null]);
        }

        return $records;
    }

    /**
     * Every (table, column) in the app schema that has a real FK constraint
     * to accounts.id. Read from information_schema rather than hardcoded so
     * a column added by a later migration can never be silently missed.
     *
     * @return array<int, array{0:string,1:string}>
     */
    private function accountReferencingColumns(): array
    {
        if ($this->accountReferencingColumns !== null) {
            return $this->accountReferencingColumns;
        }

        $database = DB::connection()->getDatabaseName();

        $rows = DB::select(
            'select TABLE_NAME as t, COLUMN_NAME as c from information_schema.KEY_COLUMN_USAGE '.
            'where TABLE_SCHEMA = ? and REFERENCED_TABLE_NAME = ? and REFERENCED_COLUMN_NAME = ?',
            [$database, 'accounts', 'id']
        );

        $columns = [];

        foreach ($rows as $row) {
            $columns[] = [(string) $row->t, (string) $row->c];
        }

        return $this->accountReferencingColumns = $columns;
    }

    /**
     * @return array<int, string>
     */
    private function accountReferencingColumnsFor(string $table): array
    {
        $columns = [];

        foreach ($this->accountReferencingColumns() as [$t, $c]) {
            if ($t === $table) {
                $columns[] = $c;
            }
        }

        return $columns;
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $database = DB::connection()->getDatabaseName();

        $row = DB::selectOne(
            'select IS_NULLABLE as n from information_schema.COLUMNS '.
            'where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?',
            [$database, $table, $column]
        );

        return $row !== null && strtoupper((string) $row->n) === 'YES';
    }

    private function accountColumn(Account $account, string $column): ?int
    {
        $value = $account->getAttribute($column);

        return $value === null ? null : (int) $value;
    }

    /**
     * The legacy partner id a dependent row carries, if any. No table in this
     * codebase has such a column today (see relinkNulledReferences()'s
     * docblock) — this is the single, narrow hook a future identity column
     * plugs into, and it deliberately does NOT fall back to a name match.
     */
    private function legacyPartnerIdFor(string $table, int $rowId): ?int
    {
        if (! Schema::hasColumn($table, 'legacy_partner_id')) {
            return null;
        }

        $value = DB::table($table)->where('id', $rowId)->value('legacy_partner_id');

        return $value === null ? null : (int) $value;
    }

    private function mappedLeafForParty(int $companyId, int $partnerId): ?int
    {
        if (! Schema::connection('legacy_pilot')->hasTable('map_party')) {
            return null;
        }

        $party = DB::connection('legacy_pilot')->table('map_party')
            ->where('company_id', $companyId)
            ->where('partner_id_fk', $partnerId)
            ->first();

        if ($party === null) {
            return null;
        }

        $accId = $party->supp_acc_id_fk ?? $party->cust_acc_id_fk;

        if ($accId === null) {
            return null;
        }

        return DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->where('acc_id', (int) $accId)
            ->value('account_id');
    }

    /**
     * --replace-seeded DELETES accounts. It is a staging-only tool: the
     * legacy-ledger pilot is explicitly staging-only and permanent (PLAN.md
     * §4 LP6: "Stays staging-only, permanently"), and the prod cutover
     * sequence imports Akeed's OWN chart, never this one. A "delete the
     * chart" verb that runs under APP_ENV=production is a foot-gun with no
     * legitimate caller, so it refuses outside local/testing/staging rather
     * than trusting the operator to have pointed .env at the right box.
     */
    private function assertNonProductionEnvironment(): void
    {
        $env = (string) config('app.env');

        if (! in_array($env, ['local', 'testing', 'staging'], true)) {
            throw new RuntimeException(
                "Refusing --replace-seeded under APP_ENV='{$env}'. This verb deletes accounts and is ".
                'permitted only under APP_ENV local, testing or staging — the legacy-ledger pilot never runs on production.'
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function seederCodes(): array
    {
        $path = base_path('database/seeders/CoaSeeder.php');

        $codes = self::SUPPLEMENTAL_ENSURE_SYSTEM_LEAVES_CODES;

        if (is_file($path)) {
            $source = (string) file_get_contents($path);

            if (preg_match_all("/'code'\\s*=>\\s*'([0-9A-Za-z]+)'/", $source, $m)) {
                $codes = array_merge($codes, $m[1]);
            }
        }

        return array_values(array_unique($codes));
    }
}
