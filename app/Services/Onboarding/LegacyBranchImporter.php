<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

// CD-PORT defect fix (D-1), and it is a defect in the Akeed-Ai original too, not something the
// port introduced: defaultUserId() below has always referenced `Company::query()` with no import
// for it, so PHP resolved the name in this file's own namespace —
// `App\Services\Onboarding\Company` — and fatal-errored. It never fired during the pilot because
// every LP1.4 run passed `--user=` explicitly, and defaultUserId() is reached only when that
// option is OMITTED. The first City Travelers run without `--user` hit it immediately. One-line
// import; no behaviour change to any path that already worked. Worth carrying back upstream.
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * legacy-ledger-pilot LP1e (ruling R-branch) — create the legacy branches as
 * real Akeed branches and populate `legacy_pilot.map_branch`.
 *
 * ── Why this class exists ───────────────────────────────────────────────────
 * {@see \App\Services\Onboarding\Replay\LegacyDocumentMapper::resolveBranch()}
 * refuses any document whose `BranchID_FK` has no `map_branch` row, and
 * staging run #5 proved that NOTHING in production code ever wrote that table
 * — only a test fixture did. Every 2025 header carries a branch FK, so 100% of
 * the in-window population refused on `legacy.branch_unmapped` and all 13
 * SubTypes type-stopped on their first document. This is the populator.
 *
 * ── R-branch, exactly as ruled ──────────────────────────────────────────────
 * The four legacy branches become FOUR real Akeed branches (not one shared
 * tag), keyed 1:1 in `map_branch`. Consolidated parity is unaffected either
 * way (O11-branch: branch is a tag, never a balancing dimension), but four
 * real branches keep `journal_entries.branch_id` meaningful for the by-branch
 * anchors LP4 reports against, and make the by-branch trial balances
 * comparable rather than degenerate.
 *
 * ── Names are STRUCTURAL, never the legacy BranchName ───────────────────────
 * The name is `sprintf(config('legacy_pilot.masters.branch_name_format'),
 * BranchCode)` — "Legacy Branch CO", not the export's own branch name. This
 * pilot republishes structure into the app database, never data.
 *
 * ── The control-account FKs ─────────────────────────────────────────────────
 * `tblBranch` carries five of them (BranchAccID_FK, CashAccID_FK,
 * CashControlAccID_FK, BankAccID_FK, DiscountAcc). R-branch says to attach
 * them "to mapped accounts where the app's branch model has such fields".
 * Akeed's `branches` table HAS NO SUCH FIELDS — the relationship runs the
 * other way (`accounts.branch_id`, `Branch::account()` hasOne) — so there is
 * nothing to attach to and inventing columns on a live table is not this
 * pilot's job. They are RESOLVED through `legacy_acc_map` and RECORDED on the
 * `map_branch` row instead, so the day `branches` grows those columns the
 * mapping is already there. NOT resolving them at all would have been the
 * silent option.
 *
 * Deliberately NOT done: minting a per-branch receivable leaf the way
 * {@see \App\Http\Controllers\BranchController::store()} does. That path looks
 * its parents up BY NAME (`Account::where('name', 'Assets')`, `like
 * '%Receivable%'`) — the exact anti-pattern LP1.2 exists to prevent — and it
 * would add four accounts to a chart whose 758-row shape is an LP4 parity
 * input. A branch here is a dimension, not a chart change.
 *
 * Idempotent: a second run updates the same four `map_branch` rows and the
 * same four `branches` rows in place. It never creates a fifth.
 */
final class LegacyBranchImporter
{
    /** Legacy tblBranch column names, resolved through LegacyColumn so this class cannot drift from the loader. */
    private const B = [
        'branch_id' => 'Branch_ID',
        'code' => 'BranchCode',
        'is_freeze' => 'IsFreeze',
        'branch_acc' => 'BranchAccID_FK',
        'cash_acc' => 'CashAccID_FK',
        'cash_control_acc' => 'CashControlAccID_FK',
        'bank_acc' => 'BankAccID_FK',
        'discount_acc' => 'DiscountAcc',
    ];

    /** map_branch column => self::B key, for the five recorded control FKs. */
    private const CONTROL_FKS = [
        'branch_acc' => ['legacy_branch_acc_id_fk', 'branch_account_id'],
        'cash_acc' => ['legacy_cash_acc_id_fk', 'cash_account_id'],
        'cash_control_acc' => ['legacy_cash_control_acc_id_fk', 'cash_control_account_id'],
        'bank_acc' => ['legacy_bank_acc_id_fk', 'bank_account_id'],
        'discount_acc' => ['legacy_discount_acc_id_fk', 'discount_account_id'],
    ];

    /**
     * @return array{staged:int,branches_created:int,branches_updated:int,mapped:int,control_fk_recorded:int,control_fk_resolved:int}
     *
     * @throws RuntimeException on anything the importer must not guess at
     */
    public function import(int $companyId, ?int $userId = null): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $legacy = DB::connection('legacy_pilot');

        if (! $legacy->getSchemaBuilder()->hasTable('stg_branch')) {
            throw new RuntimeException(
                'stg_branch is not staged — run `php artisan legacy:load` before `legacy:import-masters`.'
            );
        }

        $rows = $legacy->table('stg_branch')->get();

        if ($rows->isEmpty()) {
            throw new RuntimeException('stg_branch is staged but empty — nothing to import, and a branch map with no rows refuses every document.');
        }

        $ownerId = $userId ?? $this->defaultUserId($companyId);

        // Resolve every legacy Acc_ID the branch master points at, once.
        $accountByLegacyId = $this->importedAccountIds($companyId);

        $stats = [
            'staged' => $rows->count(),
            'branches_created' => 0,
            'branches_updated' => 0,
            'mapped' => 0,
            'control_fk_recorded' => 0,
            'control_fk_resolved' => 0,
        ];

        $seen = [];

        foreach ($rows as $row) {
            $legacyBranchId = $this->strictId($this->b($row, 'branch_id'));

            if ($legacyBranchId === null) {
                throw new RuntimeException('stg_branch carries a row with no readable Branch_ID — refusing rather than mapping a branch to nothing.');
            }

            if (isset($seen[$legacyBranchId])) {
                throw new RuntimeException("stg_branch carries Branch_ID {$legacyBranchId} more than once — the map is keyed on it and cannot express a duplicate.");
            }

            $seen[$legacyBranchId] = true;

            $code = LegacyStagingCast::toString($this->b($row, 'code'));

            if ($code === null || $code === '') {
                throw new RuntimeException("stg_branch Branch_ID {$legacyBranchId} has no BranchCode — the structural name is derived from it, so there is nothing to name the branch.");
            }

            $code = strtoupper($code);
            $name = sprintf((string) config('legacy_pilot.masters.branch_name_format'), $code);

            $branch = $this->upsertBranch($companyId, $ownerId, $legacyBranchId, $name, $code, $stats);

            $mapRow = [
                'company_id' => $companyId,
                'branch_id_fk' => $legacyBranchId,
                'branch_code' => $code,
                'branch_name' => $name,
                'akeed_branch_id' => $branch->id,
                'legacy_is_freeze' => LegacyStagingCast::toBool($this->b($row, 'is_freeze')),
                'updated_at' => now(),
            ];

            foreach (self::CONTROL_FKS as $sourceKey => [$legacyColumn, $resolvedColumn]) {
                $legacyAccId = $this->strictId($this->b($row, $sourceKey));
                $mapRow[$legacyColumn] = $legacyAccId;
                $mapRow[$resolvedColumn] = $legacyAccId === null ? null : ($accountByLegacyId[$legacyAccId] ?? null);

                if ($legacyAccId !== null) {
                    $stats['control_fk_recorded']++;

                    if ($mapRow[$resolvedColumn] !== null) {
                        $stats['control_fk_resolved']++;
                    }
                }
            }

            $legacy->table('map_branch')->updateOrInsert(
                ['company_id' => $companyId, 'branch_id_fk' => $legacyBranchId],
                $mapRow + ['created_at' => now()],
            );

            $stats['mapped']++;
        }

        return $stats;
    }

    /**
     * The branch a `map_branch` row already names wins, so a rename of the
     * format string cannot orphan a branch documents were already posted
     * against. Otherwise match on the structural name within the company, and
     * only then create.
     *
     * @param  array<string, int>  $stats
     */
    private function upsertBranch(int $companyId, int $ownerId, int $legacyBranchId, string $name, string $code, array &$stats): Branch
    {
        $existingId = DB::connection('legacy_pilot')->table('map_branch')
            ->where('company_id', $companyId)
            ->where('branch_id_fk', $legacyBranchId)
            ->value('akeed_branch_id');

        $branch = $existingId === null
            ? null
            : Branch::where('company_id', $companyId)->find((int) $existingId);

        $branch ??= Branch::where('company_id', $companyId)->where('name', $name)->first();

        $domain = config('legacy_pilot.masters.branch_email_domain');
        $email = $domain === null ? null : strtolower($code).'@'.$domain;

        if ($branch === null) {
            $branch = Branch::create([
                'company_id' => $companyId,
                'user_id' => $ownerId,
                'name' => $name,
                'email' => $email,
            ]);

            $stats['branches_created']++;

            return $branch;
        }

        $branch->fill(['name' => $name, 'email' => $email]);

        if ($branch->isDirty()) {
            $branch->save();
        }

        $stats['branches_updated']++;

        return $branch;
    }

    /**
     * legacy Acc_ID => Akeed account id, for the `direct` and `synthetic` rows
     * only. A pooled or folded leaf has no account of its own, so a branch
     * control FK pointing at one resolves to NULL and is reported as such
     * rather than silently pointed at the control it pooled into.
     *
     * @return array<int, int>
     */
    private function importedAccountIds(int $companyId): array
    {
        $map = [];

        $rows = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->whereIn('resolution', ['direct', 'synthetic'])
            ->whereNotNull('account_id')
            ->get(['acc_id', 'account_id']);

        foreach ($rows as $row) {
            $map[(int) $row->acc_id] = (int) $row->account_id;
        }

        return $map;
    }

    /**
     * `branches.user_id` is a real, NON-NULL FK. There is no "system user" in
     * this schema and `users` carries no company_id column, so the branch
     * owner is the COMPANY'S OWN owner (`companies.user_id`) — and if that is
     * missing or points at a user that no longer exists, it is refused rather
     * than defaulted to user 1, which would attach four branches to whoever
     * happens to hold that id.
     */
    private function defaultUserId(int $companyId): int
    {
        $userId = Company::query()->whereKey($companyId)->value('user_id');

        if ($userId === null || ! User::query()->whereKey($userId)->exists()) {
            throw new RuntimeException(
                "Company {$companyId} has no resolvable owner (companies.user_id), and branches.user_id is a ".
                'non-null FK. Pass --user=<id> naming the branch owner explicitly.'
            );
        }

        return (int) $userId;
    }

    private function strictId(mixed $value): ?int
    {
        $token = LegacyStagingCast::toString($value);

        return $token !== null && preg_match('/^\d+$/', $token) === 1 && (int) $token > 0 ? (int) $token : null;
    }

    private function b(object $row, string $key): mixed
    {
        return $row->{LegacyColumn::name(self::B[$key])} ?? null;
    }
}
