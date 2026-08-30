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
