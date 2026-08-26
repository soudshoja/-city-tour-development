<?php

namespace App\Services\Accounting;

/**
 * Period-lock gate consulted by {@see PostingService} on every post()/reverse().
 *
 * Contract: 11-technical-implementation-plan.md ("file 11"), referenced only as a PostingService
 * constructor dependency and pipeline step 5 ("PeriodGuard::assertOpen(companyId, docDate)", L237,
 * L259). Explicitly a "P5.1 no-op stub in P1: always allows" (L237 comment) — `accounting_periods`
 * (P5.1, L471-486) does not exist yet, so in P1 this method is a TRUE no-op: it never throws, for
 * any input, under any condition.
 *
 * Structured so a real check drops in later without touching PostingService's call site: P5.1 adds
 * the `accounting_periods` table and replaces only this method's body with a real lookup
 * (`status === 'locked' && date within range` -> throw). Nothing about the method's name, parameter
 * list order, or PostingService's call to it needs to change.
 *
 * DEVIATION (additive, documented): a third parameter, `$allowLocked`, is appended with a `false`
 * default. File 11's own "RECOMMENDED signature" for this class gives only
 * `assertOpen(int $companyId, \DateTimeInterface $date): void`. The mission brief for this build
 * additionally requires support for "the explicit --allow-locked-periods-style bypass flag the
 * reattribution plan expects" — see 13-party-ledger-reattribution-plan.md L285: "The migration
 * command runs with a PeriodGuard migration exemption (an explicit --allow-locked-periods flag whose
 * use is logged into the batch record) — the only sanctioned back-dated writer, used once." That
 * plan is gated on P1's PostingService existing (Stage A) but is itself a later, separate piece of
 * work; accounting_periods doesn't exist in P1 either, so there is nothing to bypass yet. This
 * parameter is accepted-but-inert today purely so P5.1 can wire real enforcement (including the
 * "logged into the batch record" requirement, which belongs to the caller, not this guard) without
 * changing PostingService's call site or this method's name/positional argument order.
 */
final class PeriodGuard
{
    /**
     * Assert that $date falls within an open accounting period for $companyId.
     *
     * P1 behaviour: always returns, never throws — accounting_periods does not exist yet.
     *
     * @param  \DateTimeInterface  $date  the document date being posted/reversed (== transaction_date)
     * @param  bool  $allowLocked  accepted for forward-compatibility with the P5.1 real guard and the
     *                             reattribution migration's exemption flag; has no effect in P1 (see class docblock).
     */
    public function assertOpen(int $companyId, \DateTimeInterface $date, bool $allowLocked = false): void
    {
        // P1 stub: accounting_periods (P5.1) does not exist yet. Always allow.
        // Intentionally empty — see class docblock for why this must never throw in P1.
    }
}
