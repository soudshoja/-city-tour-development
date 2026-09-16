<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * legacy-ledger-pilot LP4 -- reads LP1's mapping tables so the harness can
 * put a legacy AccCode and an Akeed account_id on the same row.
 *
 * Reads `legacy_acc_map` and `map_party` as PLAIN TABLES on the
 * legacy_pilot connection. It deliberately does not import LP1's importer
 * or mapper classes: LP4 must be able to run against a load LP1 produced
 * weeks earlier, and against LP3's map_document/map_document_line, without
 * either lane's build-time classes being present or their constructors
 * being satisfiable. A mapping table that exists is the contract; the code
 * that wrote it is not.
 */
final class LegacyMapReader
{
    public const RESOLUTION_POOLED_RECEIVABLE = 'pooled_receivable';

    public const RESOLUTION_POOLED_PAYABLE = 'pooled_payable';

    /**
     * Every mapped legacy account, keyed by THEIR AccCode -- the only
     * account identity a legacy anchor row carries.
     *
     * A duplicate AccCode is refused rather than last-one-wins: two legacy
     * accounts sharing a code would make every anchor row for that code
     * compare against an arbitrary one of them.
     *
     * @return array<string, array<string, mixed>>
     */
    public function accountMapByCode(int $companyId): array
    {
        $this->assertTable('legacy_acc_map');

        $rows = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->orderBy('acc_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $code = trim((string) $row->acc_code);

            if ($code === '') {
                continue;
            }

            if (isset($out[$code])) {
                throw new RuntimeException("legacy_acc_map holds two rows for AccCode '{$code}' in company {$companyId} — parity cannot decide which Akeed account an anchor row for that code means.");
            }

            $out[$code] = [
                'legacy_acc_id' => (int) $row->acc_id,
                'acc_code' => $code,
                'account_id' => $row->account_id === null ? null : (int) $row->account_id,
                'resolution' => (string) $row->resolution,
                'party_id' => $row->party_id === null ? null : (int) $row->party_id,
                'party_role' => $row->party_role === null ? null : (string) $row->party_role,
            ];
        }

        return $out;
    }

    /**
     * The Akeed control accounts that pooled party leaves folded onto.
     *
     * @return list<int>
     */
    public function controlAccountIds(int $companyId, string $resolution): array
    {
        $this->assertTable('legacy_acc_map');

        return DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->where('resolution', $resolution)
            ->whereNotNull('account_id')
            ->distinct()
            ->pluck('account_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * party id -> the legacy leaf AccID that party's role account IS.
     *
     * This is the fold's inverse (LegacyPartyMapper's docblock: "a fold is
     * only legitimate if it is reversible"). `journal_entries.
     * type_reference_id` carries the party id onto a pooled line; map_party
     * carries that party back to `cust_acc_id_fk` / `supp_acc_id_fk`, and
     * legacy_acc_map turns that AccID into the AccCode the ar_/ap_ anchor
     * is keyed on.
     *
     * @return array<int, int> party_id => legacy acc_id
     */
    public function partyRoleLeafByParty(int $companyId, string $role): array
    {
        $this->assertTable('map_party');

        $column = match ($role) {
            'customer' => 'cust_acc_id_fk',
            'supplier' => 'supp_acc_id_fk',
            default => throw new RuntimeException("Unknown party role '{$role}' — expected 'customer' or 'supplier'."),
        };

        $rows = DB::connection('legacy_pilot')->table('map_party')
            ->where('company_id', $companyId)
            ->whereNotNull($column)
            ->get(['partner_id_fk', $column]);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->partner_id_fk] = (int) $row->$column;
        }

        return $out;
    }

    /**
     * legacy acc_id -> AccCode, for turning a party's role-account FK back
     * into the code the anchor is keyed on.
     *
     * @return array<int, string>
     */
    public function accCodeByAccId(int $companyId): array
    {
        $this->assertTable('legacy_acc_map');

        $rows = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->get(['acc_id', 'acc_code']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->acc_id] = trim((string) $row->acc_code);
        }

        return $out;
    }

    public function hasTable(string $table): bool
    {
        return Schema::connection('legacy_pilot')->hasTable($table);
    }

    private function assertTable(string $table): void
    {
        if (! Schema::connection('legacy_pilot')->hasTable($table)) {
            throw new RuntimeException("legacy_pilot.{$table} does not exist. LP1 (`legacy:import-coa`) must have run before legacy:parity can map an anchor row to an Akeed account.");
        }
    }
}
