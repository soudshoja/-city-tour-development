<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use App\Services\Onboarding\LegacyStagingCast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP4 check 4 -- document counts per SubType per month.
 *
 * Two sides:
 *
 *   LEGACY  stg_acc_header, in-window (DocDt in the compared year) and
 *           Posted only. PLAN.md §5.2 O11 (RATIFIED) excludes the 37
 *           unposted headers: their own TB definitions are Posted=1-only,
 *           so including them would guarantee a mismatch.
 *
 *   AKEED   map_document, grouped by legacy_sub_type x month(legacy_doc_dt)
 *           x status.
 *
 * The pass identity is MAPPING-RULES §9.4 check 4:
 *
 *   replayed + skipped(no lines) + skipped(all-zero) + excluded(unposted)
 *     + refused  ==  census        (per SubType per month)
 *   and refused == 0               (PLAN.md §5.2 O5)
 *
 * i.e. every legacy document must be ACCOUNTED FOR, not merely matched in
 * total. A run that posts one document twice and drops another has the
 * right total and the wrong books.
 *
 * map_document is read as a PLAIN TABLE. LP3 owns that table's writer and
 * is built on another branch; LP4 must not import its classes (a parity
 * harness that cannot run without the replay engine's constructor is not a
 * harness). When the table is absent -- LP3 has not landed yet -- the check
 * reports `skipped` with an explicit reason and does NOT count as a pass:
 * a skipped check is visible in the report and in the run's check counts,
 * never folded into "all green".
 */
final class DocumentCensus
{
    /**
     * LP3's map_document status tokens that mean "this legacy document was
     * deliberately left out of the POSTED population because the legacy
     * header itself was Posted=0".
     *
     * These sit on the opposite side of the identity from every other
     * status: the legacy side of this census is Posted-only (O11), so
     * folding an unposted-status row into `accounted_for` over-counts our
     * side by exactly the unposted population and fails a correct replay.
     * They are compared against the legacy UNPOSTED count instead, which is
     * what O11's "exclude, COUNT, report" actually requires -- a document
     * LP3 never recorded at all still has to surface.
     */
    private const UNPOSTED_STATUSES = ['skipped_unposted', 'excluded_unposted'];

    /**
     * The column pair holding the LEGACY SubType token and the legacy
     * document date on LP3's map_document, most-preferred first.
     *
     * LP3's migration (feat/lp3-legacy-replay,
     * 2026_09_08_000001_create_legacy_replay_tables.php) names them
     * `sub_type` / `doc_dt`; MAPPING-RULES §8.2's sketch called them
     * `legacy_sub_type` / `legacy_doc_dt`. Both are accepted because either
     * could be what a given pilot database was migrated with, and getting
     * this wrong is not a soft failure: selecting a column that is not
     * there throws SQLSTATE[42S22] out of the middle of the run and takes
     * the OTHER SIX checks down with it.
     *
     * `sub_type` and NOT `engine_sub_type` is deliberate. LP3 stores the
     * legacy token ('INV') in the former and the Akeed value ('LEGACY_INV')
     * in the latter; stg_acc_header.SubType is the legacy token, so
     * grouping on `engine_sub_type` would put every bucket key on a
     * different axis from the census it is compared against and fail every
     * month at once.
     */
    private const SUB_TYPE_COLUMNS = ['sub_type', 'legacy_sub_type'];

    private const DOC_DATE_COLUMNS = ['doc_dt', 'legacy_doc_dt'];

    /**
     * @return array{status: string, reason?: string, rows: list<array<string, mixed>>, legacy_total: int, akeed_total: int, unposted_total: int, refused_total: int, mismatched: int}
     */
    public function compare(int $companyId, int $year): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_acc_header')) {
            return $this->skipped('stg_acc_header is not staged — run `php artisan legacy:load` before comparing document counts.');
        }

        if (! Schema::connection('legacy_pilot')->hasTable('map_document')) {
            return $this->skipped('legacy_pilot.map_document does not exist — LP3 (the replay engine) has not run against this database, so there is no Akeed-side document census to compare. This check is SKIPPED, not passed.');
        }

        $subTypeColumn = $this->resolveColumn(self::SUB_TYPE_COLUMNS);
        $dateColumn = $this->resolveColumn(self::DOC_DATE_COLUMNS);

        if ($subTypeColumn === null || $dateColumn === null) {
            return $this->skipped(sprintf(
                'legacy_pilot.map_document exists but carries neither naming this harness reads: a SubType column (%s) and a document-date column (%s). LP3 writes `sub_type` / `doc_dt`. This check is SKIPPED, not passed — and deliberately not thrown, which would abort the whole parity run.',
                implode(' or ', self::SUB_TYPE_COLUMNS),
                implode(' or ', self::DOC_DATE_COLUMNS),
            ));
        }

        $legacy = $this->legacyCensus($year);
        $ours = $this->akeedCensus($companyId, $year, $subTypeColumn, $dateColumn);

        $keys = array_unique(array_merge(array_keys($legacy), array_keys($ours)));
        sort($keys);

        $rows = [];
        $legacyTotal = 0;
        $akeedTotal = 0;
        $unpostedTotal = 0;
        $refusedTotal = 0;
        $mismatched = 0;

        foreach ($keys as $key) {
            [$subType, $month] = explode('|', $key, 2);

            $legacyCount = $legacy[$key]['posted'] ?? 0;
            $legacyUnposted = $legacy[$key]['unposted'] ?? 0;
            $buckets = $ours[$key] ?? [];

            $ourUnposted = 0;

            foreach (self::UNPOSTED_STATUSES as $status) {
                $ourUnposted += $buckets[$status] ?? 0;
            }

            $accountedFor = array_sum($buckets) - $ourUnposted;
            $refused = $buckets['refused'] ?? 0;

            $legacyTotal += $legacyCount;
            $akeedTotal += $accountedFor;
            $unpostedTotal += $ourUnposted;
            $refusedTotal += $refused;

            $matches = $accountedFor === $legacyCount
                && $ourUnposted === $legacyUnposted
                && $refused === 0;

            if (! $matches) {
                $mismatched++;
            }

            $rows[] = [
                'sub_type' => $subType,
                'month' => $month,
                'legacy_posted' => $legacyCount,
                'legacy_unposted' => $legacyUnposted,
                'akeed_accounted_for' => $accountedFor,
                'akeed_unposted' => $ourUnposted,
                'by_status' => $buckets,
                'refused' => $refused,
                'delta' => $accountedFor - $legacyCount,
                'unposted_delta' => $ourUnposted - $legacyUnposted,
                'matches' => $matches,
            ];
        }

        return [
            'status' => $mismatched === 0 ? 'pass' : 'fail',
            'rows' => $rows,
            'legacy_total' => $legacyTotal,
            'akeed_total' => $akeedTotal,
            'unposted_total' => $unpostedTotal,
            'refused_total' => $refusedTotal,
            'mismatched' => $mismatched,
        ];
    }

    /**
     * @param  list<string>  $candidates
     */
    private function resolveColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::connection('legacy_pilot')->hasColumn('map_document', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{posted: int, unposted: int}> "SUBTYPE|YYYY-MM"
     */
    private function legacyCensus(int $year): array
    {
        // `posted` is a nullable TEXT column holding the literal strings
        // 'True'/'False' in the real export (LegacyCsvLoader types nothing).
        // Filtering it with `->where('posted', '1')` matches NOTHING and
        // silently reports a census of zero -- exactly the LP1b defect
        // LegacyStagingCast was extracted to prevent. toBool() applies the
        // same lower(trim()) token test in PHP, which is what lets BOTH
        // sides of the Posted split be counted in one pass: O11 excludes
        // the unposted headers from the posted census but still requires
        // them counted and reported, and a WHERE-filtered query can only
        // ever return one of the two populations.
        $rows = DB::connection('legacy_pilot')
            ->table('stg_acc_header')
            ->get(['subtype', 'docdt', 'posted']);

        $out = [];

        foreach ($rows as $row) {
            // Year filtering in PHP via toYear(), not a SQL `LIKE '2025%'`:
            // docdt is TEXT and this export family writes both
            // '2025-03-01' and '2025-03-01 00:00:00'. toYear() reads the
            // leading four digits and returns null for anything that is not
            // a year, which is then skipped rather than counted into an
            // arbitrary month.
            if (LegacyStagingCast::toYear($row->docdt ?? null) !== $year) {
                continue;
            }

            $month = substr(trim((string) $row->docdt), 0, 7);
            $key = strtoupper(trim((string) $row->subtype)).'|'.$month;

            $out[$key] ??= ['posted' => 0, 'unposted' => 0];
            $bucket = LegacyStagingCast::toBool($row->posted ?? null) ? 'posted' : 'unposted';
            $out[$key][$bucket]++;
        }

        return $out;
    }

    /**
     * @return array<string, array<string, int>> "SUBTYPE|YYYY-MM" => [status => count]
     */
    private function akeedCensus(int $companyId, int $year, string $subTypeColumn, string $dateColumn): array
    {
        $query = DB::connection('legacy_pilot')->table('map_document');

        if (Schema::connection('legacy_pilot')->hasColumn('map_document', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $rows = $query->get([$subTypeColumn, $dateColumn, 'status']);

        $out = [];

        foreach ($rows as $row) {
            if (LegacyStagingCast::toYear($row->{$dateColumn} ?? null) !== $year) {
                continue;
            }

            $month = substr(trim((string) $row->{$dateColumn}), 0, 7);
            $key = strtoupper(trim((string) $row->{$subTypeColumn})).'|'.$month;
            $status = (string) $row->status;
            $out[$key][$status] = ($out[$key][$status] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * @return array{status: string, reason: string, rows: list<array<string, mixed>>, legacy_total: int, akeed_total: int, unposted_total: int, refused_total: int, mismatched: int}
     */
    private function skipped(string $reason): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'rows' => [],
            'legacy_total' => 0,
            'akeed_total' => 0,
            'unposted_total' => 0,
            'refused_total' => 0,
            'mismatched' => 0,
        ];
    }
}
