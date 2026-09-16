<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * legacy-ledger-pilot LP1e (ruling R-currency) — populate
 * `legacy_pilot.map_currency` from LINE USAGE.
 *
 * ── Why derivation, and not a master lookup ─────────────────────────────────
 * `stg_acc_detail.FcCurrID_FK` and `stg_currency.Curr_ID` DO NOT SHARE A KEY
 * SPACE. `legacy:audit-masters`'s `currency_poison` check has reported
 * `key_space_overlap: 0` since run #1 and marks itself INCONCLUSIVE for that
 * reason: the master those line FKs point at is `tblMaster`, which was never
 * exported. So there is no join to write, and PLAN.md §1.1 says so up front —
 * "currencies derive from actual line usage, not a master". This class is
 * that derivation.
 *
 * ── The two rules, in order ─────────────────────────────────────────────────
 * 1. KWD BY IDENTITY. A line whose FC total equals its LC total (within the
 *    legacy kernel's own rule-3 tolerance) and whose FcExchRate is exactly 1
 *    IS a base-currency line, whatever FK it carries. An FK EVERY line of
 *    which has that shape resolves to KWD. This is not a guess: it is the
 *    same assertion {@see \App\Services\Onboarding\Replay\LegacyDocumentMapper}
 *    already makes on a mapped KWD line before normalising it. It is checked
 *    first, and one non-identity line disqualifies the FK.
 * 2. RATE HISTORY. Otherwise, each line's FcExchRate is compared — as an
 *    EXACT string at `masters.currency.rate_match_decimals` decimals, never a
 *    tolerance — against `stg_currency`'s (CurrCode, ExchangeRate, ValidFrom,
 *    ValidTill) history, restricted to the rows whose validity window covers
 *    that line's own date. One distinct code across every matched line → that
 *    code. Zero matches, or more than one candidate code, → `unresolved`.
 *
 * A ValidTill that is blank, unparseable, or NOT AFTER its own ValidFrom is
 * treated as OPEN. That is not leniency for its own sake: the export's KWD
 * rows carry ValidFrom 2016-09-26 and ValidTill 1900-01-02, a window that
 * cannot contain any row, and a literal reading would silently exclude the
 * only unambiguous code in the master. The identity rule reaches the same
 * answer for those lines independently, so the two rules corroborate rather
 * than one covering for the other.
 *
 * ── Unresolved is a ROW, not an absent row ──────────────────────────────────
 * The mapper cannot tell "this FK has no map row because derivation failed"
 * from "this FK was never seen" if the row is simply missing. So every
 * distinct FK gets a row; a failed derivation gets `status = 'unresolved'`
 * with the FK, the line count and the reason recorded. R-currency then rules
 * that an unresolved currency is METADATA, not a refusal: the posting is in
 * KWD (the LC columns), and the FC pair is descriptive.
 *
 * ── Poison ──────────────────────────────────────────────────────────────────
 * A derived code that is one of `config('legacy_pilot.currency_poison_codes')`
 * — the `tblCurrency` junk rows (XYZ 2500, xyz 3500, abc, a blank code, a code
 * literally "2") — is written with `is_poison = true` and REFUSES at replay.
 * That is the one currency condition that still stops a document.
 *
 * Idempotent: a second run updates the same rows in place.
 */
final class LegacyCurrencyMapper
{
    private const D = [
        'curr' => 'FcCurrID_FK',
        'rate' => 'FcExchRate',
        'fc_debit' => 'FCDebit',
        'fc_credit' => 'FCCredit',
        'debit' => 'Debit',
        'credit' => 'Credit',
        'doc_dt' => 'DocDt',
    ];

    private const C = [
        'code' => 'CurrCode',
        'rate' => 'ExchangeRate',
        'valid_from' => 'ValidFrom',
        'valid_till' => 'ValidTill',
    ];

    /**
     * @return array{distinct_fks:int,mapped:int,unresolved:int,poison:int,by_identity:int,by_rate_history:int,rows:array<int, array{curr_id_fk:int,curr_code:?string,status:string,derivation:string,is_poison:bool,line_count:int,notes:?string}>}
     */
    public function map(int $companyId): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $legacy = DB::connection('legacy_pilot');
        $schema = $legacy->getSchemaBuilder();

        foreach (['stg_acc_detail', 'stg_currency'] as $table) {
            if (! $schema->hasTable($table)) {
                throw new RuntimeException("{$table} is not staged — run `php artisan legacy:load` before `legacy:import-masters`.");
            }
        }

        $history = $this->rateHistory();
        $usage = $this->lineUsage();

        $decimals = (int) config('legacy_pilot.masters.currency.rate_match_decimals', 12);
        $identityRate = $this->normaliseRate((string) config('legacy_pilot.masters.currency.identity_rate', '1'), $decimals);
        $identityCode = strtoupper((string) config('legacy_pilot.masters.currency.identity_code', 'KWD'));
        $poisonCodes = (array) config('legacy_pilot.currency_poison_codes', []);

        $stats = [
            'distinct_fks' => count($usage),
            'mapped' => 0,
            'unresolved' => 0,
            'poison' => 0,
            'by_identity' => 0,
            'by_rate_history' => 0,
            'rows' => [],
        ];

        ksort($usage);

        foreach ($usage as $currId => $facts) {
            $resolved = $this->derive($currId, $facts, $history, $decimals, $identityRate, $identityCode);

            $isPoison = $resolved['code'] !== null && in_array($resolved['code'], $poisonCodes, true);

            $row = [
                'company_id' => $companyId,
                'curr_id_fk' => $currId,
                'curr_code' => $resolved['code'],
                'akeed_currency_id' => $resolved['code'] === null ? null : $this->akeedCurrencyId($resolved['code']),
                'is_poison' => $isPoison,
                'status' => $resolved['code'] === null ? 'unresolved' : 'mapped',
                'derivation' => $resolved['derivation'],
                'matched_rate' => $resolved['matched_rate'],
                'line_count' => $facts['line_count'],
                'notes' => $resolved['notes'],
                'updated_at' => now(),
            ];

            $legacy->table('map_currency')->updateOrInsert(
                ['company_id' => $companyId, 'curr_id_fk' => $currId],
                $row + ['created_at' => now()],
            );

            if ($resolved['code'] === null) {
                $stats['unresolved']++;
            } else {
                $stats['mapped']++;
                $stats[$resolved['derivation'] === 'kwd_identity' ? 'by_identity' : 'by_rate_history']++;
            }

            if ($isPoison) {
                $stats['poison']++;
            }

            $stats['rows'][] = [
                'curr_id_fk' => $currId,
                'curr_code' => $resolved['code'],
                'status' => $row['status'],
                'derivation' => (string) $resolved['derivation'],
                'is_poison' => $isPoison,
                'line_count' => $facts['line_count'],
                'notes' => $resolved['notes'],
            ];
        }

        return $stats;
    }

    /**
     * @param  array{line_count:int,identity_lines:int}  $facts
     * @param  array<int, array{code:string,rate:string,from:?string,till:?string}>  $history
     * @return array{code:?string,derivation:string,matched_rate:?string,notes:?string}
     */
    private function derive(int $currId, array $facts, array $history, int $decimals, string $identityRate, string $identityCode): array
    {
        // Rule 1 -- KWD by identity. EVERY line must have the shape.
        if ($facts['line_count'] > 0 && $facts['identity_lines'] === $facts['line_count']) {
            return [
                'code' => $identityCode,
                'derivation' => 'kwd_identity',
                'matched_rate' => $identityRate,
                'notes' => sprintf('all %d line(s) carry FC == LC at rate 1 — a base-currency line by the legacy kernel\'s own rule 3.', $facts['line_count']),
            ];
        }

        // Rule 2 -- rate history, restricted to each line's own date window.
        $codes = [];
        $rates = [];

        foreach ($this->rateDateCandidates($currId, $decimals) as $candidate) {
            foreach ($history as $entry) {
                if ($entry['rate'] !== $candidate['rate']) {
                    continue;
                }

                if (! $this->windowCovers($entry, $candidate['date'])) {
                    continue;
                }

                $codes[$entry['code']] = true;
                $rates[$entry['rate']] = true;
            }
        }

        if (count($codes) === 1) {
            return [
                'code' => (string) array_key_first($codes),
                'derivation' => 'rate_history',
                'matched_rate' => count($rates) === 1 ? (string) array_key_first($rates) : null,
                'notes' => 'derived from stg_currency rate history (code + ValidFrom/ValidTill) matched on the line\'s own FcExchRate.',
            ];
        }

        return [
            'code' => null,
            'derivation' => 'none',
            'matched_rate' => null,
            'notes' => $codes === []
                ? sprintf('no stg_currency rate-history row matches any of this FK\'s %d line rate(s) — the currency master (tblMaster) is not in the export.', $facts['line_count'])
                : 'ambiguous: the line rates match more than one CurrCode ('.implode(', ', array_keys($codes)).').',
        ];
    }

    /**
     * The distinct (rate, date) pairs one FK's lines actually carry. Distinct,
     * not per-line: 170,577 of the export's lines share a single FK, and
     * matching each one separately would be 170,577 identical decisions.
     *
     * @return iterable<array{rate:string,date:?string}>
     */
    private function rateDateCandidates(int $currId, int $decimals): iterable
    {
        $rows = DB::connection('legacy_pilot')->table('stg_acc_detail')
            ->select([LegacyColumn::name(self::D['rate']).' as rate', LegacyColumn::name(self::D['doc_dt']).' as doc_dt'])
            ->where(LegacyColumn::name(self::D['curr']), (string) $currId)
            ->distinct()
            ->cursor();

        foreach ($rows as $row) {
            $rate = LegacyStagingCast::toString($row->rate);

            if ($rate === null || ! is_numeric($rate)) {
                continue;
            }

            yield [
                'rate' => $this->normaliseRate($rate, $decimals),
                'date' => LegacyStagingCast::toDate($row->doc_dt)?->toDateString(),
            ];
        }
    }

    /**
     * @param  array{code:string,rate:string,from:?string,till:?string}  $entry
     */
    private function windowCovers(array $entry, ?string $date): bool
    {
        if ($date === null) {
            // A line with no readable date cannot be placed in a window; only
            // an open-ended history row can claim it.
            return $entry['till'] === null;
        }

        if ($entry['from'] !== null && $date < $entry['from']) {
            return false;
        }

        return $entry['till'] === null || $date < $entry['till'];
    }

    /**
     * `stg_currency` rows with a usable rate, normalised for exact string
     * comparison. A zero or negative rate is dropped: it can never be a real
     * exchange rate, and keeping it would make every rate-0 line "match".
     *
     * @return array<int, array{code:string,rate:string,from:?string,till:?string}>
     */
    private function rateHistory(): array
    {
        $decimals = (int) config('legacy_pilot.masters.currency.rate_match_decimals', 12);
        $history = [];

        foreach (DB::connection('legacy_pilot')->table('stg_currency')->cursor() as $row) {
            $code = LegacyStagingCast::toString($row->{LegacyColumn::name(self::C['code'])} ?? null);
            $rate = LegacyStagingCast::toString($row->{LegacyColumn::name(self::C['rate'])} ?? null);

            if ($code === null || $code === '' || $rate === null || ! is_numeric($rate) || (float) $rate <= 0.0) {
                continue;
            }

            $from = LegacyStagingCast::toDate($row->{LegacyColumn::name(self::C['valid_from'])} ?? null)?->toDateString();
            $till = LegacyStagingCast::toDate($row->{LegacyColumn::name(self::C['valid_till'])} ?? null)?->toDateString();

            // A ValidTill that is not AFTER its own ValidFrom describes an
            // empty window and is treated as open -- see the class docblock.
            if ($till !== null && $from !== null && $till <= $from) {
                $till = null;
            }

            $history[] = [
                'code' => strtoupper($code),
                'rate' => $this->normaliseRate($rate, $decimals),
                'from' => $from,
                'till' => $till,
            ];
        }

        return $history;
    }

    /**
     * Per-FK line count and how many of those lines have the KWD-identity
     * shape, computed in SQL because the real table is 179,421 rows.
     *
     * @return array<int, array{line_count:int,identity_lines:int}>
     */
    private function lineUsage(): array
    {
        $curr = LegacyColumn::name(self::D['curr']);
        $rate = LegacyColumn::name(self::D['rate']);
        $tolerance = (float) config('legacy_pilot.masters.currency.identity_tolerance', 0.0005);
        $identityRate = (string) config('legacy_pilot.masters.currency.identity_rate', '1');

        $fc = sprintf(
            'CAST(%s AS DECIMAL(24,6)) + CAST(%s AS DECIMAL(24,6))',
            LegacyColumn::name(self::D['fc_debit']),
            LegacyColumn::name(self::D['fc_credit'])
        );
        $lc = sprintf(
            'CAST(%s AS DECIMAL(24,6)) + CAST(%s AS DECIMAL(24,6))',
            LegacyColumn::name(self::D['debit']),
            LegacyColumn::name(self::D['credit'])
        );

        $rows = DB::connection('legacy_pilot')->table('stg_acc_detail')
            ->selectRaw("{$curr} as curr_id, COUNT(*) as line_count, SUM(CASE WHEN ABS(({$fc}) - ({$lc})) <= ? AND CAST({$rate} AS DECIMAL(24,12)) = CAST(? AS DECIMAL(24,12)) THEN 1 ELSE 0 END) as identity_lines", [$tolerance, $identityRate])
            ->whereNotNull($curr)
            ->where($curr, '<>', '')
            ->groupBy($curr)
            ->get();

        $usage = [];

        foreach ($rows as $row) {
            $currId = LegacyStagingCast::toString($row->curr_id);

            if ($currId === null || preg_match('/^\d+$/', $currId) !== 1) {
                continue;
            }

            $usage[(int) $currId] = [
                'line_count' => (int) $row->line_count,
                'identity_lines' => (int) $row->identity_lines,
            ];
        }

        return $usage;
    }

    /**
     * The app's own `currencies` table, matched on ISO code. NULL when the
     * code is not one Akeed carries — recorded, never invented.
     */
    private function akeedCurrencyId(string $code): ?int
    {
        if (! DB::getSchemaBuilder()->hasTable('currencies')) {
            return null;
        }

        $id = DB::table('currencies')->where('iso_code', $code)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function normaliseRate(string $rate, int $decimals): string
    {
        return number_format((float) $rate, $decimals, '.', '');
    }
}
