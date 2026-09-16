<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy\Concerns;

use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\Scope\LegacyCompanyGuard;
use App\Services\Onboarding\Scope\LegacyIdBandGuard;
use App\Services\Onboarding\Scope\LegacyLoadScope;
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
        LegacyPathGuard::assertQuarantinedConnection();

        $scope = LegacyLoadScope::forCompany($companyId);

        app(LegacyCompanyGuard::class)->assertTargetCompany($scope);
        app(LegacyIdBandGuard::class)->assertArmed($scope);

        return $scope;
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
