<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy\Concerns;

use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\Scope\LegacyCompanyGuard;
use App\Services\Onboarding\Scope\LegacyIdBandGuard;
use App\Services\Onboarding\Scope\LegacyLoadScope;
use App\Services\Onboarding\Scope\LegacyRowLedger;
use App\Services\Onboarding\Scope\LegacySandboxGuard;
use App\Services\Onboarding\Scope\LegacyScopeRefused;

/**
 * CD-PORT — the three lines every ported legacy:* WRITE command gains, and nothing else.
 *
 * The ported pipeline is imported machinery (owner ruling: "measure first, then fix"), so the
 * adaptation to City Travelers is kept to a trait the commands call rather than edits threaded
 * through 28 service classes. Each write command's `handle()` gains exactly one call —
 * {@see self::assertLegacyScope()} — placed before its first statement, and the rest of the
 * command is byte-identical to the Akeed-Ai original.
 *
 * Ordering inside that call is deliberate and is the "refuse before the first write" requirement:
 *
 *   1. the quarantine guard (the pipeline's own, ported unchanged) — the staging connection must
 *      resolve to a `legacy_pilot*` or `city_tour_test*` database;
 *   2. the COMPANY gate — the target company must exist, must not be protected, and must itself
 *      live in the band;
 *   3. the ID-BAND gate — every declared write-set table must be armed to mint inside the band.
 *
 * All three run before the command touches a row. A refusal is a {@see LegacyScopeRefused}, which
 * the command turns into `self::FAILURE` and a printed message, so an operator sees the reason
 * rather than a stack trace, and a test can assert on the message rather than on an exit code.
 */
trait GuardsLegacyScope
{
    /**
     * @throws LegacyScopeRefused
     */
    protected function assertLegacyScope(int $companyId): LegacyLoadScope
    {
        // ── THE SANDBOX GATE, first, before everything ───────────────────────────────────────
        // This pipeline's row attribution is NOT safe on an application database shared with
        // another live company. Two verification rounds proved it twice, by two different routes.
        // {@see LegacySandboxGuard} is what keeps a future operator out of that situation, and it
        // runs before the quarantine check, the company gate and the band because it is the one
        // that makes the others meaningful.
        app(LegacySandboxGuard::class)->assertSandbox();

        LegacyPathGuard::assertQuarantinedConnection();

        $scope = LegacyLoadScope::forCompany($companyId);

        app(LegacyCompanyGuard::class)->assertTargetCompany($scope);
        app(LegacyIdBandGuard::class)->assertArmed($scope);

        return $scope;
    }

    /**
     * ROUND 2, finding F2 - the R-CO4 POST-condition, on the deployed path.
     *
     * Round 1 shipped `assertBaselineIntact()`-shaped checks whose only caller was a test. This
     * runs at the END of every legacy:* write command:
     *
     *   1. re-derive and record which rows this load owns ({@see LegacyRowLedger::claim()}), so the
     *      reversal has an accurate ledger and never has to fall back on an id range;
     *   2. prove every row that existed BEFORE the load is byte-identical
     *      ({@see LegacyCompanyGuard::assertBaselineIntact()});
     *   3. prove no table outside the declared write set grew
     *      ({@see LegacyIdBandGuard::assertNoUndeclaredGrowthSinceCapture()});
     *   4. prove every row of the target company landed inside the reserved band
     *      ({@see LegacyIdBandGuard::assertCompanyRowsInBand()}).
     *
     * A failure is reported with the offending tables and rows NAMED, and the command exits
     * non-zero. It does not roll the load back - that is `legacy:unload`'s job, and a silent
     * rollback would destroy the evidence of what went wrong.
     */
    protected function assertLegacyPostConditions(LegacyLoadScope $scope): bool
    {
        try {
            $owned = app(LegacyRowLedger::class)->claim($scope);

            app(LegacyCompanyGuard::class)->assertBaselineIntact($scope);
            $grew = app(LegacyIdBandGuard::class)->assertNoUndeclaredGrowthSinceCapture($scope);
            app(LegacyIdBandGuard::class)->assertCompanyRowsInBand($scope);
        } catch (LegacyScopeRefused $e) {
            $this->error('POST-CONDITION FAILED: '.$e->getMessage());

            return false;
        }

        $this->line(sprintf(
            'scope post-conditions OK: %d row(s) claimed across %d table(s); %d declared table(s) '.
            'grew; no undeclared table grew; every pre-existing row byte-identical.',
            array_sum($owned),
            count(array_filter($owned)),
            count($grew)
        ));

        return true;
    }

    /**
     * The same gate, but reporting the refusal on the console and returning a failure exit code
     * instead of throwing — the shape a `handle()` wants.
     *
     * Returns the scope on success, or null when the command should return FAILURE.
     */
    protected function legacyScopeOrFail(int $companyId): ?LegacyLoadScope
    {
        try {
            return $this->assertLegacyScope($companyId);
        } catch (LegacyScopeRefused $e) {
            $this->error($e->getMessage());

            return null;
        }
    }
}
