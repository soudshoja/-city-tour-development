<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * legacy-ledger-pilot LP1.3 — populates legacy_pilot.map_party from
 * stg_partner.
 *
 * WHY THIS IS PART OF LP1 AND NOT A LATER NICETY. PLAN.md §4 LP1.1 rules
 * that party leaves pool onto RECEIVABLE_CONTROL / PAYABLE_CONTROL rather
 * than becoming per-party GL leaves. A pool is a MANY-TO-ONE fold, and a
 * fold is only legitimate if it is reversible: LP4 check 3 compares our
 * position at 2025-12-31 against ar_balances_* (109 customer leaves) and
 * ap_balances_* (75 payable leaves) PER LEAF. That comparison is
 * impossible unless the leaf → party identity survives the fold. It does,
 * in two places, both written by LP1:
 *
 *   legacy_acc_map.party_id / party_role  — the identity of the party a
 *                                           pooled leaf IS (written by
 *                                           LegacyCoaImporter);
 *   map_party                              — the party registry itself,
 *                                           one row per tblPartner row,
 *                                           dual-role parties kept as ONE
 *                                           party with both role FKs.
 *
 * Together they let a replayed line on the control account be decomposed
 * back to (control account, party id) and the per-party position
 * reproduced exactly — see Tests\Feature\Legacy\LegacyPartyIdentityTest,
 * which proves both the round-trip and that the control account's total
 * equals the sum of its party leaves.
 *
 * Supplier alias/dedup stays deferred (PLAN.md §5.2 O6): the pseudonymised
 * export gives nothing to group on, and account-level mapping is 1:1
 * either way, so trial-balance parity is unaffected.
 */
final class LegacyPartyMapper
{
    /**
     * @return array{parties:int,customers:int,suppliers:int,dual_role:int,unlinked_leaves:int}
     */
    public function map(int $companyId): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $partners = DB::connection('legacy_pilot')->table('stg_partner')->get();

        if ($partners->isEmpty()) {
            throw new RuntimeException('stg_partner is empty — run legacy:load first.');
        }

        $stats = ['parties' => 0, 'customers' => 0, 'suppliers' => 0, 'dual_role' => 0, 'unlinked_leaves' => 0];

        foreach ($partners as $partner) {
            $custAccId = $this->accId($partner->custaccid_fk ?? null);
            $suppAccId = $this->accId($partner->suppaccid_fk ?? null);

            $isCustomer = $custAccId !== null;
            $isSupplier = $suppAccId !== null;

            DB::connection('legacy_pilot')->table('map_party')->updateOrInsert(
                ['company_id' => $companyId, 'partner_id_fk' => (int) $partner->partner_id],
                [
                    'is_customer' => $isCustomer,
                    'is_supplier' => $isSupplier,
                    'cust_acc_id_fk' => $custAccId,
                    'supp_acc_id_fk' => $suppAccId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $stats['parties']++;
            $stats['customers'] += $isCustomer ? 1 : 0;
            $stats['suppliers'] += $isSupplier ? 1 : 0;
            $stats['dual_role'] += ($isCustomer && $isSupplier) ? 1 : 0;
        }

        // Honest report, never a silent default: a pooled party leaf that
        // carries no party_id cannot be decomposed back out of its control
        // account, so LP4's per-leaf AR/AP parity could not be reproduced
        // for it. Counted and returned, so the command can surface it.
        $stats['unlinked_leaves'] = (int) DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->whereIn('resolution', ['pooled_receivable', 'pooled_payable'])
            ->whereNull('party_id')
            ->count();

        return $stats;
    }

    private function accId($value): ?int
    {
        if ($value === null || trim((string) $value) === '' || (int) $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
