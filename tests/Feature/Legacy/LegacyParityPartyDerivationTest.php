<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Parity\ParityHarness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4 -- AR/AP per party leaf (MAPPING-RULES §9.3).
 *
 * This is the check that proves LP1's party POOLING lost nothing. Two
 * customer leaves fold onto one RECEIVABLE_CONTROL account; the only thing
 * that survives the fold is `journal_entries.type_reference_id`. If the
 * derivation is wrong -- or if a pooled line was posted without a party --
 * the control account's TOTAL still ties to the anchor while every
 * individual customer's position is wrong, which is precisely the failure a
 * pooled trial balance cannot see.
 */
class LegacyParityPartyDerivationTest extends TestCase
{
    use BuildsParityFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->companyId = $this->makeParityCompany();
        $this->buildBalancedParityWorld($this->companyId);
    }

    private function parity(string $anchor = ParityHarness::ANCHOR_ALL): array
    {
        return app(ParityHarness::class)->run($this->companyId, CarbonImmutable::parse('2025-12-31'), $anchor);
    }

    private function check(array $result, string $key): array
    {
        foreach ($result['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("No check named '{$key}'.");
    }

    /**
     * The pooled control account holds 650.375 across two customers. The
     * derivation must split that back into 250.250 and 400.125 and match
     * each against its own anchor leaf.
     */
    public function test_two_party_leaves_pooled_on_one_control_account_are_derived_back_per_leaf(): void
    {
        $result = $this->parity('ar');
        $ar = $this->check($result, 'ar');

        $this->assertSame('pass', $ar['status'], json_encode($ar['diffs']));
        $this->assertSame(2, $ar['summary']['parties_derived']);
        $this->assertSame(0, $ar['summary']['undecomposable_lines']);
        $this->assertSame([$this->parityAccounts['AR_CONTROL']], $ar['summary']['control_account_ids']);
    }

    /**
     * MUTATION PROOF for the party derivation: moving a party's amount onto
     * ANOTHER party leaves the control account's total untouched (650.375
     * either way) and the pooled trial balance perfectly green -- only the
     * per-leaf derivation can see it. Deleting the derivation, or
     * summing the control account without grouping by type_reference_id,
     * makes this test pass while both customers' statements are wrong.
     */
    public function test_moving_an_amount_between_two_parties_is_invisible_to_the_pooled_total_and_caught_per_leaf(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['AR_CONTROL'])
            ->where('type_reference_id', 12)
            ->update(['type_reference_id' => 11]);

        $result = $this->parity();

        // The pooled trial balance still ties exactly -- that is the point.
        $this->assertSame('pass', $this->check($result, 'tb_closing')['status'], 'The POOLED total is unchanged; only the per-leaf check can see this.');

        $ar = $this->check($result, 'ar');
        $this->assertSame('fail', $ar['status']);

        $byCode = [];

        foreach ($ar['diffs'] as $diff) {
            $byCode[$diff['acc_code']] = $diff;
        }

        $this->assertArrayHasKey('10904001', $byCode, 'The gaining leaf must be named.');
        $this->assertArrayHasKey('10904002', $byCode, 'The losing leaf must be named.');
        $this->assertSame(400.125, $byCode['10904001']['delta']);
        $this->assertSame(-400.125, $byCode['10904002']['delta']);
        $this->assertSame('missing_in_akeed', $byCode['10904002']['classification']);
    }

    /**
     * MAPPING-RULES §9.3: "A party position that cannot be decomposed (a
     * pooled line with a NULL type_reference_id) is a hard failure of
     * §1.2 (b)'s party-required rule and should not exist."
     */
    public function test_a_pooled_line_with_no_party_is_reported_as_undecomposable(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['AR_CONTROL'])
            ->where('type_reference_id', 12)
            ->update(['type_reference_id' => null]);

        $ar = $this->check($this->parity('ar'), 'ar');

        $this->assertSame('fail', $ar['status']);
        $this->assertSame(1, $ar['summary']['undecomposable_lines']);

        $orphans = array_values(array_filter($ar['diffs'], fn ($d) => $d['classification'] === 'undecomposable_pooled_line'));
        $this->assertCount(1, $orphans);
        $this->assertStringContainsString('NULL type_reference_id', $orphans[0]['detail']);
    }

    /**
     * A party holding a position on a control account with no role account
     * in map_party cannot be tied to any legacy leaf, so its amount would
     * otherwise vanish from the comparison entirely.
     */
    public function test_a_party_with_no_role_account_in_map_party_is_named_not_dropped(): void
    {
        DB::connection('legacy_pilot')->table('map_party')->where('partner_id_fk', 12)->delete();

        $ar = $this->check($this->parity('ar'), 'ar');

        $this->assertSame('fail', $ar['status']);

        $orphans = array_values(array_filter($ar['diffs'], fn ($d) => $d['classification'] === 'party_without_role_leaf'));
        $this->assertCount(1, $orphans);
        $this->assertStringContainsString('PARTY-12', $orphans[0]['detail']);
        $this->assertSame(400.125, $orphans[0]['akeed_value']);
    }

    /**
     * Payables use the OTHER role column (supp_acc_id_fk). Reading the
     * customer column for both roles would silently return nothing for AP.
     */
    public function test_the_payable_side_derives_through_the_supplier_role_column(): void
    {
        $ap = $this->check($this->parity('ap'), 'ap');

        $this->assertSame('pass', $ap['status'], json_encode($ap['diffs']));
        $this->assertSame(1, $ap['summary']['parties_derived']);
    }

    // ====================================================================
    // LP4b bucket B2 -- the ar_/ap_balances exports are GROUP-SCOPED
    // reports, not "every party leaf with a balance". Absence from one of
    // them does NOT mean the leaf's legacy balance is zero.
    // ====================================================================

    /**
     * Staging run #6 reported 15 leaves as `missing_in_legacy` on the
     * strength of a comment that said the anchors omit zero balances only.
     * They do not: the AR export covers `10904%` and the AP export covers
     * `206%`, and all 15 were real party leaves parked under BANK ACCOUNTS /
     * CASH IN HAND / RECEIVABLES-GENERAL (UNCLASSIFIED-274 bucket B), each
     * of which matched its own closing trial-balance figure exactly.
     *
     * So a leaf the party anchor never covers falls back to the CLOSING TB
     * anchor and is compared against it — gaining coverage, not losing it.
     */
    public function test_a_leaf_outside_the_party_anchor_is_compared_against_the_closing_trial_balance(): void
    {
        DB::connection('legacy_pilot')->table('stg_ar_20251231')->where('acccode', '10904002')->delete();

        $ar = $this->check($this->parity('ar'), 'ar');

        $this->assertSame('pass', $ar['status'], json_encode($ar['diffs']));
        $this->assertSame(1, $ar['summary']['anchor_rows'], 'One AR anchor row was removed.');
        $this->assertSame(1, $ar['summary']['outside_party_anchor_compared'], 'The uncovered leaf must still be COMPARED, not silently dropped.');
        $this->assertSame(2, $ar['compared'], 'Both leaves are still compared — one from the AR anchor, one from the closing TB.');
    }

    /**
     * MUTATION PROOF for that fallback: it is a real comparison, not a
     * waiver. Corrupt the closing-TB figure for the uncovered leaf and the
     * check must name it, with `legacy_source` saying where the figure came
     * from. A fallback that merely suppressed the row would stay green here.
     */
    public function test_a_wrong_closing_trial_balance_figure_for_an_uncovered_leaf_is_caught(): void
    {
        DB::connection('legacy_pilot')->table('stg_ar_20251231')->where('acccode', '10904002')->delete();
        DB::connection('legacy_pilot')->table('stg_tb_20251231_consolidated')
            ->where('acccode', '10904002')
            ->update(['perioddr' => '399.125', 'closingnet' => '399.125']);

        $ar = $this->check($this->parity('ar'), 'ar');

        $this->assertSame('fail', $ar['status']);

        $byCode = [];

        foreach ($ar['diffs'] as $diff) {
            $byCode[$diff['acc_code']] = $diff;
        }

        $this->assertArrayHasKey('10904002', $byCode);
        $this->assertSame('balance_mismatch', $byCode['10904002']['classification']);
        $this->assertSame('closing trial-balance anchor', $byCode['10904002']['legacy_source']);
        $this->assertSame(1.0, $byCode['10904002']['delta']);
    }

    /**
     * Absent from BOTH anchors is the only absence that means zero — and a
     * non-zero position against it is still a finding, still called
     * `missing_in_legacy`.
     */
    public function test_a_non_zero_position_absent_from_both_anchors_is_reported_as_missing_in_legacy(): void
    {
        DB::connection('legacy_pilot')->table('stg_ar_20251231')->where('acccode', '10904002')->delete();
        DB::connection('legacy_pilot')->table('stg_tb_20251231_consolidated')->where('acccode', '10904002')->delete();

        $ar = $this->check($this->parity('ar'), 'ar');

        $this->assertSame('fail', $ar['status']);

        $extra = array_values(array_filter($ar['diffs'], fn ($d) => $d['classification'] === 'missing_in_legacy'));
        $this->assertCount(1, $extra);
        $this->assertSame('10904002', $extra[0]['acc_code']);
        $this->assertSame(400.125, $extra[0]['akeed_value']);
        $this->assertSame('absent from both anchors', $extra[0]['legacy_source']);
    }

    // ====================================================================
    // LP4b bucket B1 -- an anchor row on a leaf LP1 imported `direct`.
    // ====================================================================

    /**
     * 14 of the 20 AP diffs and 2 of the 11 AR diffs in staging run #6 were
     * this: the AP export lists the per-branch REFUNDS PAYABLE leaves
     * (`206%`, so in scope for the report) that O8-refunds deliberately
     * keeps OUT of PAYABLE_CONTROL. Their money is on their own account. A
     * party-only decomposition compares them against the 0.000 the pool
     * holds for them and calls every one `missing_in_akeed`.
     */
    public function test_an_anchor_row_on_a_direct_imported_leaf_is_compared_as_an_account(): void
    {
        $this->addDirectPayableAnchorLeaf($this->companyId);

        $result = $this->parity();

        $this->assertSame('pass', $this->check($result, 'tb_closing')['status'], json_encode($this->check($result, 'tb_closing')['diffs']));

        $ap = $this->check($result, 'ap');

        $this->assertSame('pass', $ap['status'], json_encode($ap['diffs']));
        $this->assertSame(2, $ap['summary']['anchor_rows']);
        $this->assertSame(1, $ap['summary']['pooled_leaves_compared']);
        $this->assertSame(1, $ap['summary']['direct_leaves_compared'], 'The direct leaf must be compared as an ACCOUNT, not looked for in the pool.');
        $this->assertSame(1, $ap['summary']['parties_derived'], 'A direct leaf is not a party and must not appear as one.');
    }

    /**
     * MUTATION PROOF for bucket B1: break the direct leaf's own balance and
     * the AP check must name it. Comparing it as a party (the run #6
     * behaviour) reports a fixed 0.000 for it either way, so this test
     * cannot pass under the old derivation — the delta would be the whole
     * balance, not the corruption.
     */
    public function test_a_wrong_balance_on_a_direct_anchor_leaf_is_caught_with_the_exact_delta(): void
    {
        $accountId = $this->addDirectPayableAnchorLeaf($this->companyId);

        DB::table('journal_entries')
            ->where('account_id', $accountId)
            ->update(['credit' => 499.000]);

        $ap = $this->check($this->parity('ap'), 'ap');

        $this->assertSame('fail', $ap['status']);

        $byCode = [];

        foreach ($ap['diffs'] as $diff) {
            $byCode[$diff['acc_code']] = $diff;
        }

        $this->assertArrayHasKey('20603001', $byCode);
        $this->assertSame('balance_mismatch', $byCode['20603001']['classification']);
        $this->assertSame('ap anchor', $byCode['20603001']['legacy_source']);
        $this->assertSame(-500.0, $byCode['20603001']['legacy_value']);
        $this->assertSame(-499.0, $byCode['20603001']['akeed_value']);
        $this->assertSame(1.0, $byCode['20603001']['delta']);
        $this->assertSame($accountId, $byCode['20603001']['account_id'], 'The diff must point at the leaf OWN account, not at the control account.');
    }

    /**
     * A direct anchor leaf that carries no ledger balance at all is still a
     * finding, and is still called `missing_in_akeed` — the classification
     * run #6 used, now reserved for the case that actually means it.
     */
    public function test_a_direct_anchor_leaf_with_no_ledger_balance_is_missing_in_akeed(): void
    {
        $accountId = $this->addDirectPayableAnchorLeaf($this->companyId);

        DB::table('journal_entries')->where('account_id', $accountId)->delete();
        DB::table('journal_entries')->where('account_id', $this->parityAccounts['BANK'])->where('debit', 500.000)->delete();

        $ap = $this->check($this->parity('ap'), 'ap');

        $this->assertSame('fail', $ap['status']);

        $byCode = [];

        foreach ($ap['diffs'] as $diff) {
            $byCode[$diff['acc_code']] = $diff;
        }

        $this->assertArrayHasKey('20603001', $byCode);
        $this->assertSame('missing_in_akeed', $byCode['20603001']['classification']);
        $this->assertSame(-500.0, $byCode['20603001']['legacy_value']);
        $this->assertSame(0.0, $byCode['20603001']['akeed_value']);
    }

    // ====================================================================
    // LP4b buckets B3 / B5 -- ruled out on staging, held ruled out here.
    // ====================================================================

    /**
     * PLAN.md §5.3 **O-b**: a leaf carrying BOTH role FKs pools to the
     * payable side. UNCLASSIFIED-274 §3 said to revisit that only if the
     * AR/AP per-party check showed the 50 dual-role parties landing on the
     * wrong side. It does not, and the reason is structural rather than
     * lucky: each check decomposes only ITS OWN control account and reverses
     * the fold through ITS OWN role column, so one party can hold a customer
     * position and a supplier position at once without either leaking into
     * the other's leaf.
     */
    public function test_a_dual_role_party_is_derived_per_role_without_the_two_sides_leaking(): void
    {
        // PARTY-11 becomes dual-role: the same partner also owns the payable
        // leaf, and holds a position on BOTH control accounts.
        DB::connection('legacy_pilot')->table('map_party')->where('partner_id_fk', 21)->delete();
        DB::connection('legacy_pilot')->table('map_party')->where('partner_id_fk', 11)
            ->update(['is_supplier' => true, 'supp_acc_id_fk' => 4]);

        DB::connection('legacy_pilot')->table('legacy_acc_map')->where('acc_id', 4)->update(['party_id' => 11]);

        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['AP_CONTROL'])
            ->update(['type_reference_id' => 11]);

        $result = $this->parity();

        $ar = $this->check($result, 'ar');
        $ap = $this->check($result, 'ap');

        $this->assertSame('pass', $ar['status'], json_encode($ar['diffs']));
        $this->assertSame('pass', $ap['status'], json_encode($ap['diffs']));

        // 250.250 stayed on the customer leaf and -1250.750 on the supplier
        // leaf; neither role's figure is the other's, and neither is the sum.
        $this->assertSame(1, $ap['summary']['parties_derived']);
        $this->assertSame(2, $ar['summary']['parties_derived']);
    }
}
