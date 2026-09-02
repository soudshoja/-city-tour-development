<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use App\Models\Company;

/**
 * Shared engine-ON refusal guard for legacy maintenance/backfill commands that hand-roll COA rows
 * the posting engine now owns once a company is cut over to it. Mirrors the per-company guard
 * `FixCreditInvoiceCOA::fixInvoice()` / `createCreditPaymentCOA()` established first (see those
 * methods' own docblocks, ~lines 343 and 746, for the "refuse rather than risk writing rows the
 * engine cannot see or reconcile against" rationale) -- extracted here so every OTHER legacy
 * command in this family enforces the identical rule instead of re-deriving it per file.
 *
 * Usage: per company under consideration,
 *   `if ($this->refusePostingEngineEnabledCompany($companyId, $companyName)) { continue; }`
 * then, at the end of handle(), fold the refusal count into the exit code via
 * {@see exitCodeForPostingEngineRefusals()} so a run that skipped EVERY candidate because every
 * company it considered was engine-ON is reported as a failure, not a silent no-op success.
 */
trait RefusesWhenPostingEngineEnabled
{
    /**
     * True when $companyId is cut over to the posting engine -- the caller must skip this company
     * entirely (never hand-roll legacy COA rows for it) rather than proceed.
     */
    protected function isPostingEngineEnabledForCompany(?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return (bool) (Company::find($companyId)?->posting_engine_enabled);
    }

    /**
     * Emits the loud, company-named warning line every refusal must produce, then returns true so
     * call sites can skip in one line:
     *   `if ($this->refusePostingEngineEnabledCompany($id, $name)) { continue; }`
     * Returns false (no warning, no skip) when the company is engine-OFF or unresolvable.
     */
    protected function refusePostingEngineEnabledCompany(?int $companyId, ?string $companyName = null, ?string $commandName = null): bool
    {
        if (! $this->isPostingEngineEnabledForCompany($companyId)) {
            return false;
        }

        $label = $companyName ? "{$companyName} (#{$companyId})" : "#{$companyId}";
        $command = $commandName ?? static::class;

        $this->warn(
            "Skipping company {$label}: the posting engine is enabled -- {$command} hand-rolls COA "
            .'rows the engine now owns for this company; use the engine\'s own repost/reverse tooling '
            .'instead.'
        );

        return true;
    }

    /**
     * The shared exit-code rule every command applying this guard follows: a refusal alone must
     * never be silently swallowed into a false "success" exit code, but a run that skipped SOME
     * engine-ON companies while successfully processing at least one engine-OFF company is still a
     * genuine success. Non-zero ONLY when every candidate this run considered was refused -- i.e.
     * the command processed literally nothing because of the guard. $priorExitCode (the command's
     * own pre-existing error-driven exit code) is passed through unchanged otherwise, so this never
     * downgrades a genuine failure to 0.
     */
    protected function exitCodeForPostingEngineRefusals(int $processedCount, int $refusedCount, int $priorExitCode = 0): int
    {
        if ($refusedCount > 0 && $processedCount === 0) {
            return 1;
        }

        return $priorExitCode;
    }
}
