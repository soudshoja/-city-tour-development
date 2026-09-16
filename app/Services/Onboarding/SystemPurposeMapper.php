<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Account;
use App\Models\SystemAccount;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP1.2 — maps each tblSystemParameters control-account
 * pointer into a `map_purpose` row for one company, resolved via the
 * legacy_acc_map this phase's LP1.1 step already built.
 *
 * NO HARDCODED ACCOUNT IDS, and no hardcoded legacy tblSystemParameters KEY
 * NAMES either — the real key names are export DATA, not schema, and this
 * phase's data-handling rule ("nothing from D:\akeedac goes into git")
 * forbids baking real values into source. The correspondence between a
 * legacy parameter name and an Akeed purpose_code is therefore supplied at
 * call time via config('legacy_pilot.parameter_purpose_map') (or an
 * explicit array passed in) — populated by whoever runs the real LP1.2
 * pass after inspecting the real (never-committed) export, never invented
 * here. Left unconfigured, EVERY purpose in
 * config('accounting.purpose_codes.global') reports honestly as
 * 'unmapped' — that is the correct, safe default, not a bug: "0 mapped, N
 * unmapped" is exactly what SystemAccountsSeeder already reports on a
 * fresh chart (PLAN.md §5.1 R9 — vacuous green is not health, but neither
 * is a fabricated mapping).
 *
 * MAPPING-RULES.md decision O12: a `map_purpose` row is a REPORT, not a
 * mapping the engine can use. AccountResolver reads `system_accounts` and
 * nothing else, so every status='mapped' row is ALSO upserted into
 * `system_accounts` here — through the same
 * SystemAccount::updateOrCreate((company_id, purpose_code, service_type))
 * shape SystemAccountsSeeder::upsert() uses, so the two writers can never
 * produce different row shapes. Without that write, LP1.2 would report
 * "mapped" while AccountResolver still resolved nothing, and
 * LegacyCoaImporter's pool targets would silently fall through to the
 * structural fallback.
 *
 * ── COORDINATOR RULING R2 (2026-09-07, LP1c) ────────────────────────────
 * Two extensions, both forced by the real export (staging run #3):
 *
 * 1. A --purpose-map VALUE may now be a bare numeric legacy Acc_ID as well
 *    as a tblSystemParameters ParameterName. Some purposes have a real,
 *    identified legacy target that NO parameter names — RETAINED_EARNINGS'
 *    true posting leaf 6301012 is one level below the group 6301 that
 *    `ProfitLossAccount` points at, and no parameter names the leaf. The
 *    parameter-only mechanism could express that only by inventing a
 *    parameter, so the operator may instead name the Acc_ID outright. It is
 *    still not a hardcode: the value comes from the run's own --purpose-map
 *    argument, never from source or config committed to this repo.
 *
 * 2. When a purpose's target resolves to a GROUP, the mapper descends to
 *    the group's single LEAF child if — and only if — exactly one exists.
 *    Zero or more than one is a refusal with a message naming the group and
 *    the candidates: picking one of several would be a guess, and this
 *    phase never guesses a posting target. (On the real chart, group 6301
 *    keeps two leaf children after the eleven yearly "Profit & Loss Account
 *    <year>" leaves fold away, so it refuses and the operator names 6301012
 *    directly per extension 1 above.)
 */
final class SystemPurposeMapper
{
    /**
     * @param  array<string, string>  $parameterPurposeMap  purpose_code => legacy
     *                                                      tblSystemParameters ParameterName, OR (R2) a bare numeric legacy Acc_ID
     * @return array{mapped:int,unmapped:int,skipped_non_leaf:int,report:array<int,array<string,mixed>>}
     */
    public function map(int $companyId, array $parameterPurposeMap): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $purposeCodes = (array) config('accounting.purpose_codes.global', []);
        $stats = ['mapped' => 0, 'unmapped' => 0, 'skipped_non_leaf' => 0];
        $report = [];

        foreach ($purposeCodes as $purposeCode) {
            $mapValue = $parameterPurposeMap[$purposeCode] ?? null;

            if ($mapValue === null) {
                $this->record($companyId, $purposeCode, null, null, 'unmapped', 'no legacy parameter mapping configured for this purpose');
                $stats['unmapped']++;
                $report[] = ['purpose_code' => $purposeCode, 'status' => 'unmapped', 'reason' => 'no legacy parameter mapping configured'];

                continue;
            }

            // R2 extension 1: a bare numeric value IS the legacy Acc_ID; no
            // tblSystemParameters lookup is involved (and no parameter name
            // is recorded, because none was used).
            if (is_numeric($mapValue)) {
                $parameterName = null;
                $accId = (int) $mapValue;
            } else {
                $parameterName = (string) $mapValue;

                $parameterRow = DB::connection('legacy_pilot')->table('stg_system_parameters')
                    ->where('parametername', $parameterName)
                    ->first();

                if ($parameterRow === null || ! is_numeric($parameterRow->parametervalue ?? null)) {
                    $this->record($companyId, $purposeCode, $parameterName, null, 'unmapped', "legacy parameter '{$parameterName}' not found or not a numeric Acc_ID");
                    $stats['unmapped']++;
                    $report[] = ['purpose_code' => $purposeCode, 'status' => 'unmapped', 'reason' => "parameter '{$parameterName}' not found/non-numeric"];

                    continue;
                }

                $accId = (int) $parameterRow->parametervalue;
            }

            $mapRow = DB::connection('legacy_pilot')->table('legacy_acc_map')
                ->where('company_id', $companyId)
                ->where('acc_id', $accId)
                ->first();

            if ($mapRow === null || $mapRow->account_id === null) {
                $this->record($companyId, $purposeCode, $parameterName, null, 'unmapped', "Acc_ID {$accId} has no resolved account (unclassified or not imported)");
                $stats['unmapped']++;
                $report[] = ['purpose_code' => $purposeCode, 'status' => 'unmapped', 'reason' => "Acc_ID {$accId} unresolved"];

                continue;
            }

            $account = Account::find($mapRow->account_id);

            if ($account === null) {
                $this->record($companyId, $purposeCode, $parameterName, null, 'unmapped', "account {$mapRow->account_id} no longer exists");
                $stats['unmapped']++;
                $report[] = ['purpose_code' => $purposeCode, 'status' => 'unmapped', 'reason' => 'target account missing'];

                continue;
            }

            $descentNote = null;

            if ($account->children()->exists()) {
                // R2 extension 2: descend to the group's SINGLE leaf child.
                $leafChildren = $account->children()->get()
                    ->filter(fn (Account $child) => ! $child->children()->exists())
                    ->values();

                if ($leafChildren->count() !== 1) {
                    $candidates = $leafChildren->map(fn (Account $c) => "{$c->code} ({$c->name})")->implode(', ');

                    $reason = "account {$account->code} ({$account->name}) is a group with ".
                        $leafChildren->count().' leaf child/children'.
                        ($candidates !== '' ? ": {$candidates}" : '').
                        ' — refusing: a group is not postable and picking one of '.
                        ($leafChildren->count() === 0 ? 'none' : 'several').
                        ' would be a guess. Name the intended leaf\'s legacy Acc_ID directly in --purpose-map.';

                    $this->record($companyId, $purposeCode, $parameterName, $account->id, 'skipped_non_leaf', $reason);
                    $stats['skipped_non_leaf']++;
                    $report[] = ['purpose_code' => $purposeCode, 'status' => 'skipped_non_leaf', 'reason' => $reason];

                    continue;
                }

                $group = $account;
                $account = $leafChildren->first();
                $descentNote = "descended from group {$group->code} ({$group->name}) to its single leaf child {$account->code} ({$account->name})";
            }

            // O12: the row the ENGINE reads. AccountResolver never looks at
            // map_purpose.
            SystemAccount::updateOrCreate(
                ['company_id' => $companyId, 'purpose_code' => $purposeCode, 'service_type' => null],
                ['account_id' => $account->id]
            );

            $this->record($companyId, $purposeCode, $parameterName, $account->id, 'mapped', $descentNote);
            $stats['mapped']++;
            $report[] = ['purpose_code' => $purposeCode, 'status' => 'mapped', 'account_id' => $account->id, 'reason' => $descentNote];
        }

        return $stats + ['report' => $report];
    }

    private function record(int $companyId, string $purposeCode, ?string $parameterName, ?int $accountId, string $status, ?string $reason): void
    {
        DB::connection('legacy_pilot')->table('map_purpose')->updateOrInsert(
            ['company_id' => $companyId, 'purpose_code' => $purposeCode],
            [
                'legacy_parameter_name' => $parameterName,
                'account_id' => $accountId,
                'status' => $status,
                'reason' => $reason,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
