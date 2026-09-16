<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot legacy:audit-masters — the LP0.4/LP1.4 master-data
 * sanity sweep over staged legacy_pilot.stg_* tables. Never transforms
 * anything; only reads and reports. A run writes one row per check to
 * `legacy_masters_audit` (pass/fail/info + JSON detail) so a re-run can be
 * diffed, in addition to printing.
 */
final class MastersAuditor
{
    /**
     * @return array<string, array<string, mixed>> check_key => result
     */
    public function run(): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $results = [];
        $results['currency_poison'] = $this->auditCurrencyPoison();
        $results['acctranstype_dirt'] = $this->auditAccTransTypeDirt();
        $results['cost_centre_single_row'] = $this->auditCostCentreSingleRow();
        $results['unposted_headers'] = $this->auditUnpostedHeaders();
        $results['ojv_inventory'] = $this->auditOjvInventory();
        $results['census_2025'] = $this->auditCensus2025();
        $results['frozen_account_activity'] = $this->auditFrozenAccountActivity();

        foreach ($results as $key => $result) {
            $this->recordResult($key, $result);
        }

        return $results;
    }

    /**
     * Flags known-poison currency codes AND proves no in-window
     * (2025) line references one — a hit here must halt the phase
     * (R3 / LP1.4).
     */
    private function auditCurrencyPoison(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_currency')) {
            return ['status' => 'info', 'detail' => ['message' => 'stg_currency not loaded']];
        }

        $poisonCodes = (array) config('legacy_pilot.currency_poison_codes', []);

        $currencies = DB::connection('legacy_pilot')->table('stg_currency')->get();

        $poisonIds = [];
        $poisonRows = [];

        foreach ($currencies as $row) {
            $code = (string) ($row->currcode ?? '');

            if (in_array($code, $poisonCodes, true) || trim($code) === '') {
                $poisonIds[] = (int) $row->curr_id;
                $poisonRows[] = ['curr_id' => (int) $row->curr_id, 'currcode' => $code, 'exchangerate' => $row->exchangerate ?? null];
            }
        }

        $inWindowHits = [];
        $lineCurrIds = [];
        $keySpacesJoin = null;

        if (Schema::connection('legacy_pilot')->hasTable('stg_acc_detail')) {
            $lineCurrIds = DB::connection('legacy_pilot')->table('stg_acc_detail')
                ->whereNotNull('fccurrid_fk')
                ->distinct()
                ->pluck('fccurrid_fk')
                ->map(fn ($v) => (int) $v)
                ->all();

            $masterIds = $currencies->map(fn ($r) => (int) $r->curr_id)->all();

            // THE HONEST PART. tblCurrency.Curr_ID and
            // tblAccDetail.FcCurrID_FK are NOT the same key space in this
            // export: the staged currency file is a RATE-HISTORY table
            // whose Curr_ID values are single digits, while the lines
            // reference a currency master (tblMaster) the export
            // deliberately excludes ("tblMaster general lookups beyond the
            // CREDITCARD view", README-MANIFEST.md). Comparing them
            // produces a PASS that proves nothing -- the exact "vacuous
            // green" PLAN.md §5.1 R9 forbids -- and could equally produce a
            // false FAIL, since Curr_ID 5 carries BOTH a KWD row and a
            // poison code-"2" row. So: if the two key spaces do not
            // intersect at all, this check reports its own inability to
            // answer rather than a clean bill of health, and the currency
            // authority stays where PLAN.md §1.1 puts it -- the per-line
            // FcExchRate, never a master table.
            $keySpacesJoin = count(array_intersect($lineCurrIds, $masterIds));

            if (! empty($poisonIds) && $keySpacesJoin > 0) {
                $inWindowHits = DB::connection('legacy_pilot')->table('stg_acc_detail')
                    ->whereIn('fccurrid_fk', $poisonIds)
                    ->limit(50)
                    ->get(['accdetailid', 'docid_fk', 'fccurrid_fk'])
                    ->toArray();
            }
        }

        $status = 'pass';
        $note = 'no staged line references a poison currency id';

        if (! empty($inWindowHits)) {
            $status = 'fail';
            $note = 'a staged line references a poison currency id — halt the phase (PLAN.md §5.1 R3)';
        } elseif ($lineCurrIds !== [] && $keySpacesJoin === 0) {
            $status = 'info';
            $note = 'INCONCLUSIVE: stg_currency.curr_id and stg_acc_detail.fccurrid_fk do not intersect at all, '.
                'so this check cannot prove anything about in-window lines. The currency master those line FKs '.
                'point at (tblMaster) is not in the export. Rates come from the per-line FcExchRate regardless '.
                '(PLAN.md §1.1); do NOT read this as a clean bill of health.';
        }

        return [
            'status' => $status,
            'detail' => [
                'poison_rows' => $poisonRows,
                'poison_count' => count($poisonRows),
                'in_window_hits' => $inWindowHits,
                'distinct_line_currency_ids' => count($lineCurrIds),
                'key_space_overlap' => $keySpacesJoin,
                'note' => $note,
            ],
        ];
    }

    /**
     * tblAccount.IsFreeze is preserved on import (accounts.disabled), and
     * the posting engine REFUSES to post to a disabled account
     * (FrozenAccountException). A frozen legacy account that nonetheless
     * carries in-window activity is therefore a replay blocker that must be
     * surfaced now, by owner decision — not discovered halfway through LP3,
     * and never resolved by quietly unfreezing accounts during import.
     */
    private function auditFrozenAccountActivity(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_account')
            || ! Schema::connection('legacy_pilot')->hasTable('stg_acc_detail')) {
            return ['status' => 'info', 'detail' => ['message' => 'stg_account/stg_acc_detail not loaded']];
        }

        $frozenQuery = DB::connection('legacy_pilot')->table('stg_account');
        LegacyStagingCast::whereTruthy($frozenQuery, 'isfreeze');
        $frozenIds = $frozenQuery->pluck('acc_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($frozenIds === []) {
            return ['status' => 'pass', 'detail' => ['frozen_accounts' => 0, 'with_in_window_activity' => 0]];
        }

        $hits = DB::connection('legacy_pilot')->table('stg_acc_detail')
            ->whereIn('accid_fk', $frozenIds)
            ->where('docdt', 'like', '2025%')
            ->selectRaw('accid_fk, count(*) as line_count')
            ->groupBy('accid_fk')
            ->get()
            ->toArray();

        // RULING (coordinator, engineering call, LP1b): IsFreeze in the
        // legacy system is a forward-looking posting lock applied at
        // export time, not a historical invalidation -- 2025 lines already
        // posted against an account later frozen are legitimate history
        // and MUST replay through the LP3 seam. This check is therefore
        // INFO-only: it reports the counts and the affected account ids
        // for the owner's record, it never fails the phase. The importer
        // still preserves the frozen/disabled status on the account
        // itself (see LegacyCoaImporter), and LP3's replay path is the one
        // that consults config('legacy_pilot.replay.ignore_frozen_for_legacy_docs')
        // to tell an in-window legacy replay apart from a genuinely new
        // posting attempt -- nothing changes here in the posting engine.
        return [
            'status' => 'info',
            'detail' => [
                'frozen_accounts' => count($frozenIds),
                'with_in_window_activity' => count($hits),
                'frozen_account_ids_with_activity' => array_map(fn ($row) => (int) $row->accid_fk, $hits),
                'hits' => $hits,
                'note' => 'INFO only (LP1b ruling): in-window activity on a frozen account is legitimate legacy '.
                    'history and replays via the LP3 seam under legacy_pilot.replay.ignore_frozen_for_legacy_docs; '.
                    'it no longer halts this audit.',
            ],
        ];
    }

    /**
     * AccTransType is documented dirty (ref 14 Q9): a leaf flagged "bank"
     * whose COA position says otherwise. Never used for classification —
     * this check exists only to evidence and quantify the dirt.
     */
    private function auditAccTransTypeDirt(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_account')) {
            return ['status' => 'info', 'detail' => ['message' => 'stg_account not loaded']];
        }

        $payablePrefixes = (array) config('legacy_pilot.payable_group_prefixes', []);
        $customerPrefixes = (array) config('legacy_pilot.customer_group_prefixes', []);

        $bankFlagged = DB::connection('legacy_pilot')->table('stg_account')
            ->where('acctranstype', 'like', '%bank%')
            ->get(['acc_id', 'accgroup', 'acctranstype']);

        $dirty = [];

        foreach ($bankFlagged as $row) {
            $group = (string) ($row->accgroup ?? '');
            $isParty = false;

            foreach (array_merge($payablePrefixes, $customerPrefixes) as $prefix) {
                if ($prefix !== '' && str_starts_with($group, (string) $prefix)) {
                    $isParty = true;

                    break;
                }
            }

            if ($isParty) {
                $dirty[] = ['acc_id' => (int) $row->acc_id, 'accgroup' => $group, 'acctranstype' => $row->acctranstype];
            }
        }

        return [
            'status' => 'info',
            'detail' => ['bank_flagged_total' => $bankFlagged->count(), 'dirty_party_leaves' => $dirty, 'dirty_count' => count($dirty)],
        ];
    }

    private function auditCostCentreSingleRow(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_cost_center')) {
            return ['status' => 'info', 'detail' => ['message' => 'stg_cost_center not loaded']];
        }

        $count = DB::connection('legacy_pilot')->table('stg_cost_center')->count();

        return [
            'status' => $count === 1 ? 'pass' : 'info',
            'detail' => ['row_count' => $count, 'note' => 'no cost-centre dimension is migrated regardless of this count'],
        ];
    }

    private function auditUnpostedHeaders(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_acc_header')) {
            return ['status' => 'info', 'detail' => ['message' => 'stg_acc_header not loaded']];
        }

        // `Posted` is a staging TEXT column ('True'/'False' in the real
        // export, not '1'/'0') -- coerce it via LegacyStagingCast rather
        // than a literal-equality filter, which silently matched nothing
        // against real data (37,134 "unposted" reported for every row,
        // instead of the true count of 37).
        $unpostedQuery = DB::connection('legacy_pilot')->table('stg_acc_header');
        LegacyStagingCast::whereFalsy($unpostedQuery, 'posted');
        $unposted = $unpostedQuery->count();

        $posted = DB::connection('legacy_pilot')->table('stg_acc_header')->count() - $unposted;

        $expected = config('legacy_pilot.posted_false_count');

        return [
            'status' => 'info',
            'detail' => ['unposted_count' => $unposted, 'posted_count' => $posted, 'expected_unposted' => $expected],
        ];
    }

    private function auditOjvInventory(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_acc_header')) {
            return ['status' => 'info', 'detail' => ['message' => 'stg_acc_header not loaded']];
        }

        $rows = DB::connection('legacy_pilot')->table('stg_acc_header')
            ->where('subtype', 'OJV')
            ->get(['docyear', 'docdt', 'docid']);

        $byYear = [];

        foreach ($rows as $row) {
            $year = $row->docyear ?? substr((string) $row->docdt, 0, 4);
            $byYear[(string) $year] = ($byYear[(string) $year] ?? 0) + 1;
        }

        $notExactlyOne = array_filter($byYear, fn ($c) => $c !== 1);

        return [
            'status' => empty($notExactlyOne) ? 'pass' : 'info',
            'detail' => ['by_year' => $byYear, 'years_not_exactly_one' => $notExactlyOne],
        ];
    }

    private function auditCensus2025(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_acc_header')) {
            return ['status' => 'info', 'detail' => ['message' => 'stg_acc_header not loaded']];
        }

        // Same TEXT-coercion bug as auditUnpostedHeaders(): filtering
        // `posted` with a literal '1' equality matched nothing against the
        // real export's 'True'/'False' text, so this reported 0 actual
        // documents for every SubType regardless of the census expected
        // counts. Coerce with LegacyStagingCast::toBool() per row instead.
        //
        // COORDINATOR RULING R3 (2026-09-07, LP1c). This check used to count
        // POSTED headers only and compare that to config('legacy_pilot.
        // census_2025'), which produced a permanent, wrong-looking shortfall
        // on exactly two document types: FRV 4,298 vs 4,325 and ADM 301 vs
        // 302. Staging run #3 diagnosed it exactly — the manifest counts ALL
        // 2025 headers, posted or not, and 2025 holds 27 unposted FRVs and 1
        // unposted ADM. 4,298 + 27 = 4,325 and 301 + 1 = 302, with no
        // residual. So the data was complete and the CHECK was wrong.
        // The check now reports posted and unposted SEPARATELY (the posted
        // split still matters — it is what LP3 replays) and PASSES when
        // posted + unposted equals the manifest count for every SubType.
        $rows = DB::connection('legacy_pilot')->table('stg_acc_header')
            ->get(['subtype', 'docdt', 'posted']);

        $posted = [];
        $unposted = [];

        foreach ($rows as $row) {
            if (LegacyStagingCast::toYear($row->docdt) !== 2025) {
                continue;
            }

            $subtype = (string) $row->subtype;

            if (LegacyStagingCast::toBool($row->posted)) {
                $posted[$subtype] = ($posted[$subtype] ?? 0) + 1;
            } else {
                $unposted[$subtype] = ($unposted[$subtype] ?? 0) + 1;
            }
        }

        $expected = (array) config('legacy_pilot.census_2025', []);
        $diffs = [];
        $totals = [];

        foreach ($expected as $subtype => $expectedCount) {
            $postedCount = $posted[$subtype] ?? 0;
            $unpostedCount = $unposted[$subtype] ?? 0;
            $total = $postedCount + $unpostedCount;
            $totals[$subtype] = $total;

            if ($total !== $expectedCount) {
                $diffs[$subtype] = [
                    'expected' => $expectedCount,
                    'posted' => $postedCount,
                    'unposted' => $unpostedCount,
                    'posted_plus_unposted' => $total,
                ];
            }
        }

        return [
            'status' => empty($diffs) ? 'pass' : 'info',
            'detail' => [
                'posted_counts' => $posted,
                'unposted_counts' => $unposted,
                'posted_plus_unposted' => $totals,
                'diffs_vs_expected' => $diffs,
            ],
        ];
    }

    private function recordResult(string $key, array $result): void
    {
        DB::connection('legacy_pilot')->table('legacy_masters_audit')->insert([
            'check_key' => $key,
            'status' => $result['status'],
            'detail_json' => json_encode($result['detail'] ?? []),
            'run_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
