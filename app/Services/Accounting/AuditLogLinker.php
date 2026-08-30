<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Facades\Route;

/**
 * P2.5.E (p2_5-brief.md's owner refinement, 2026-08-30): "each item also links to its Log Center
 * rows (P2.5.F subject link) once F exists -- build the link helper now, F fills it."
 *
 * P2.5.F (the Accounting Log Center) has not shipped yet as of this build -- there is no
 * `accounting_audit_log` table and no admin screen. This class is the CONTRACT P2.5.F is expected
 * to satisfy, not a working link today: {@see self::forSubject()} resolves to a URL only once a
 * route named `accounting.audit-log.index` exists, and returns null until then (every caller in
 * this wave -- {@see UnlockDependencyResolver} -- already treats a null link as "omit the link",
 * never as an error).
 *
 * ── Contract P2.5.F must satisfy for every existing call site to start working automatically ────
 * Register a route named exactly `accounting.audit-log.index` that accepts (at minimum) the query
 * parameters `subject_type` (a string identifying the model, e.g. `invoice`, `payment`,
 * `invoice_receipt`, `transaction`, `journal_entry`, `accounting_period` -- P2.5.F's brief already
 * lists these as its own `accounting_audit_log.subject_type` polymorphic values, so no new naming
 * scheme is introduced here) and `subject_id` (the row id). P2.5.F's own brief text already commits
 * to this shape independently ("filters ... subject_type (multi) + subject number/id search" and
 * "URL query string mirrors filter+search state") -- this class merely builds a URL against a
 * contract P2.5.F was already going to expose, so no coordination beyond the route NAME is needed.
 *
 * Deliberately a static helper, not a service resolved via the container: every call site is a
 * plain "give me a URL or null" lookup with no state and no dependencies of its own, and a static
 * method keeps it trivial to call from inside an array-building loop
 * ({@see UnlockDependencyResolver}'s blocker builders) without threading a service instance
 * through every private method signature purely to reach this one line.
 */
final class AuditLogLinker
{
    /**
     * @param  string  $subjectType  one of the `accounting_audit_log.subject_type` values P2.5.F's
     *                               brief already names (invoice, payment, invoice_receipt,
     *                               transaction, journal_entry, accounting_period, ...).
     */
    public static function forSubject(string $subjectType, int $subjectId): ?string
    {
        if (! Route::has('accounting.audit-log.index')) {
            return null;
        }

        return route('accounting.audit-log.index', [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }
}
