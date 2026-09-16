<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base test case for the P1 posting-engine suite
 * (Accounting Gap/11-technical-implementation-plan.md §C1).
 *
 * File 11 requires the C1 global invariant — "after any operation exercised
 * by any test in the accounting suite, the acting company's trial balance
 * still balances" — to be wired as a tearDown hook on a base
 * AccountingTestCase, "not a standalone named test, a structural requirement
 * on the whole suite" (§acceptanceTests). This class is that hook.
 *
 * Usage: extend this instead of Tests\TestCase, and call
 * trackCompanyForInvariants($companyId) once a company under test exists
 * (typically right after creating it). tearDown() then runs the full
 * AccountingInvariants suite against every tracked company — including
 * companies used only in a rejected/rolled-back post() attempt, so a defect
 * that leaks a partial write past a thrown PostingException is still caught
 * even though the test itself only asserted on the exception.
 */
abstract class AccountingTestCase extends TestCase
{
    use AccountingInvariants;
    use RefreshDatabase;

    /** @var int[] */
    protected array $invariantCompanyIds = [];

    protected function trackCompanyForInvariants(int $companyId): void
    {
        if (! in_array($companyId, $this->invariantCompanyIds, true)) {
            $this->invariantCompanyIds[] = $companyId;
        }
    }

    /**
     * CT-D2b — drop this company's gateway CLEARING mappings, so a bare seeded fixture is "a
     * company whose gateways are not configured yet" rather than one carrying the pooled shape.
     *
     * ── CT-A7-5 RE-DERIVED (this helper's own instruction, followed) ────────────────────────────
     * The version below used to delete every `system_accounts` row pointing AT the pool account,
     * and asserted it deleted at least one, with the message "SystemAccountsSeeder is expected to
     * park the gateway purposes on the Payment Gateway pool itself. If it no longer does, this
     * helper is stale — re-derive it." CT-A7-5 is exactly that event: COA-DESIGN-PROPOSAL
     * correction C1 now seeds the five per-gateway clearing leaves (1310-1314) under `1300`, so
     * `resolveGatewayClearing()`'s NAME-matching branch maps each purpose onto its own leaf and the
     * bare-pool branch never runs. Nothing is parked on the pool any more, `$dropped` became 0, and
     * the pre-condition fired on all five call sites.
     *
     * Re-derived to the INTENT rather than to the old mechanism: delete the `GATEWAY_CLEARING_*`
     * mappings wherever they now sit. The callers' premise — a company whose gateways are simply
     * not configured, yielding `UNRESOLVED_PURPOSE` / `ruling` rows rather than blocking ones — is
     * unchanged and is what the paragraphs below still describe.
     *
     * The history below is kept because it is the record of WHY CT-A7-5 changed the seeder.
     *
     * Why any fixture that runs `accounting:coa-linkage --apply` needs this on the merged CT-D2
     * head, and why it is a FIXTURE change and not a production one:
     *
     * `SystemAccountsSeeder::resolveGatewayClearing()`'s bare-pool branch maps ALL five gateways
     * onto the pool while the pool is still a LEAF — deliberately, because it refuses to guess a
     * clearing leaf from a pool that can legitimately hold unrelated payment instruments. Then
     * `coa-linkage --apply` mints the `Knet` and `uPayment` leaves under that same pool, which turns
     * it into a GROUP and leaves MYFATOORAH / HESABE / TAP mapped onto a non-leaf. That state has
     * always existed on a bare seeded chart — VERIFY-CT-A56-R3 recorded it as R3-14, BENIGN. What
     * changed is that PR #14 (CT-A4b) makes a `NON_LEAF_PURPOSE_MAPPING` unconditionally BLOCKING
     * regardless of purpose-code prefix, deliberately, to close the travelerp defect where `--apply`
     * exited 0 with three gateways left unpostable.
     *
     * Measured: `--apply` on a bare seeded chart exits **0** at `c70ab5d0c` and **1** at
     * `fafaa14268`, with exactly three blocking rows (`purpose
     * GATEWAY_CLEARING_{MYFATOORAH,HESABE,TAP} maps to a non-leaf account`). No test file involved
     * differs between those two commits — it is PR #14's change, not CT-D2b's.
     *
     * A real company does not sit in that state: its pool carries per-gateway NAMED children (CT-A1
     * measured `Tap`, `MyFatoorah`, `Hesabe` on company 1, alongside `Cash`, `Cheques`, `Deema`,
     * `Tabby`, `Taly`), so each purpose maps onto its own leaf. A fixture that drops the pooled
     * mappings is a company whose gateways are simply not configured yet — which is exactly the
     * premise the `GATEWAY_CLEARING_` "ruling, not a blocker" carve-out exists for, and which
     * yields `UNRESOLVED_PURPOSE` / `ruling` rows instead of blocking ones.
     *
     * The collision itself — `SystemAccountsSeeder` deliberately PRESERVES a pool mapping that
     * `CoaLinkage::verifyPurposes()` now calls a defect this run introduced — was reported as
     * **CT-D2b-1** and is CLOSED by CT-A7-5 (correction C1), which removes the pooled state the two
     * rules disagreed about instead of adding a guard to one of them.
     */
    protected function dropPooledGatewayMappings(int $companyId): void
    {
        $poolId = (int) \Illuminate\Support\Facades\DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('name', 'Payment Gateway')
            ->value('id');

        $this->assertGreaterThan(0, $poolId, "No 'Payment Gateway' pool on company {$companyId}.");

        $dropped = \Illuminate\Support\Facades\DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($poolId) {
                $q->where('account_id', $poolId)
                    ->orWhere('purpose_code', 'like', 'GATEWAY_CLEARING_%');
            })
            ->delete();

        $this->assertGreaterThan(
            0,
            $dropped,
            'Fixture pre-condition: this company is expected to have gateway CLEARING mappings to drop '
            .'(on the 1300 pool before CT-A7-5, on the per-gateway 1310-1314 leaves after it). If it has '
            .'neither, this helper is stale — re-derive it.'
        );
    }

    protected function tearDown(): void
    {
        // Run before parent::tearDown() tears down the application container
        // that DB::table()/app(TrialBalanceService::class) need.
        //
        // Residual 8 fix: this loop used to run with no try/finally, so an invariant assertion
        // FAILURE (a real PHPUnit\Framework\ExpectationFailedException, not a PHP error) threw
        // out of tearDown() before parent::tearDown() ever ran — and RefreshDatabase's own
        // tearDown is what rolls back the per-test database transaction. Skipping it left that
        // transaction open on the connection RefreshDatabase's connection pool hands to the NEXT
        // test, which would then appear to deadlock ~50s later (MySQL's innodb_lock_wait_timeout)
        // against a lock this test's own never-rolled-back transaction was still holding — a
        // bogus DeadlockException on a completely unrelated later test, and at least one wrong
        // diagnosis in a build report chased exactly that symptom to the wrong cause. Wrapping the
        // loop in try/finally guarantees parent::tearDown() (and therefore the rollback) always
        // runs, whether or not an invariant failed, so a genuine invariant failure is reported
        // exactly once, on the test that caused it, and never leaks into any test that runs after.
        try {
            foreach ($this->invariantCompanyIds as $companyId) {
                $this->assertAccountingInvariants($companyId);
            }
        } finally {
            parent::tearDown();
        }
    }
}
