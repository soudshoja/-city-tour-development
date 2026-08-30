<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (W0 kill-switch gate) when the posting engine is not enabled
 * for this write, either globally (config('accounting.engine.enabled') === false) or for the
 * specific company (companies.posting_engine_enabled === false, including the case where the
 * company could not be resolved at all).
 *
 * This is the ONLY rollback lever the engine has until P2 wires a feeder. Both flags are now
 * LIVE and operable: config('accounting.engine.enabled') and companies.posting_engine_enabled
 * are read by PostingService::post() (the gate), and posting_engine_enabled is writable via
 * `php artisan accounting:engine {company} --enable|--disable` (App\Console\Commands\
 * AccountingEngine) — the column is in Company::$fillable/$casts, so mass-assignment
 * ($company->update([...])) persists correctly instead of silently no-op'ing. The gate fails
 * CLOSED on every ambiguous case (company not found => refuse, never "skip the check").
 *
 * Thrown BEFORE DB::transaction() opens in post() itself — a refusal from WITHIN post() never
 * reserves a document number, never opens post()'s own transaction, and leaves zero trace.
 * reverse() actually owns two writes of its own that never go through post() — a
 * `Transaction::withoutGlobalScopes()->whereKey($result->transaction->id)->update([
 * 'reversal_of_transaction_id' => $posted->id])` call (stamped on the NEW reversal transaction,
 * using post()'s own return value $result) and a `->whereKey($posted->id)->update([
 * 'posting_status' => 'reversed'])` call (stamped on the ORIGINAL) inside
 * PostingService::reverse(). The gate still covers them: both calls sit inside reverse()'s own
 * `DB::transaction()` closure (the one wrapping the whole method body, opened before the internal
 * `$result = $this->post($reversalDraft, $userId);` call), which already holds a
 * `lockForUpdate()` row lock (taken on the `Transaction::withoutGlobalScopes()
 * ->whereNull('deleted_at')->lockForUpdate()->findOrFail(...)` re-fetch at the top of that
 * closure).
 *
 * THE REAL INVARIANT: every write reverse()/repost() performs must stay inside that same
 * DB::transaction() closure as the internal post() call — order within the closure is
 * irrelevant, because DB::transaction() rolls back every write made inside its closure on any
 * exception regardless of the order those writes happened in. What actually matters is that no
 * such write is moved to run BEFORE that closure opens (in autocommit, or in its own separate
 * transaction) — such a write commits on its own and then survives the refusal, leaving a refused
 * reverse() with a stray 'reversed' original instead of zero trace.
 *
 * W0.4 MEASURED, one mutation at a time, against PostingEngineGateTest (7 tests, 58 assertions).
 * Only the `posting_status` write is movable at all, so all three results are about that one:
 *   - Moved BEFORE the internal post() call, still inside reverse()'s closure — suite stays green
 *     ("Tests: 7 passed (58 assertions)"). Order inside the closure genuinely does not matter.
 *   - Hoisted OUT of the closure, to run BEFORE `return DB::transaction(...)` (autocommit) — suite
 *     goes RED: "engine disabled refuses reverse of previously posted transaction ... CAVEAT: the
 *     ORIGINAL transaction's posting_status must stay 'posted' ... -'posted' +'reversed'",
 *     "Tests: 1 failed, 6 passed (56 assertions)". This is the escape these two gate tests catch.
 *   - Hoisted OUT of the closure the OTHER way, to run AFTER `DB::transaction(...)` returns —
 *     suite stays GREEN ("Tests: 7 passed (58 assertions)"), because on a refusal the exception
 *     propagates out of DB::transaction() and the hoisted write never executes at all. KNOWN
 *     COVERAGE GAP, stated so nobody mistakes green for proof: that arrangement is not gate-unsafe
 *     (nothing strays on a refusal) but it IS crash-unsafe — a failure between the commit and the
 *     stamps would leave a committed reversal with a NULL reversal_of_transaction_id and an
 *     original still marked 'posted', and no test here would notice.
 * The `reversal_of_transaction_id` write is not movable in either direction relative to post():
 * not a rollback rule, a plain data dependency — its WHERE clause needs `$result->transaction->id`
 * and `$result` is post()'s own return value, so hoisting it above that call does not compile into
 * anything runnable (it fails with "Undefined variable $result"), and it must stay in the closure
 * for the autocommit reason above.
 *
 * PostingService::repost() inherits the same coverage transitively, by calling reverse() (which
 * calls post()) from inside its own outer DB::transaction(). Every write statement in this class
 * — the `JournalEntry::create([...])` calls inside post()'s own DB::transaction(), reverse()'s
 * two update() calls above, createTransactionHeader()'s `$transaction->save()`, and
 * recordSupersededIdempotencyKeyRejection()'s `IdempotencyKeyRejection::create([...])` — is
 * reachable only via a WRITE made through post()/reverse()/repost(), all of which route through
 * this gate. The one path that bypasses it on purpose is reverse()'s own already-reversed early
 * return (`if ($existingReversal !== null) { return $this->toPostedDocument($existingReversal);
 * }`): it is read-only, returns success without ever calling post(), and so has no write for the
 * gate to guard.
 *
 * Deliberately independent of config('accounting.account_observer.enabled'), which gates
 * App\Observers\AccountObserver only — see config/accounting.php for why those two flags are
 * kept decoupled on purpose.
 */
final class PostingEngineDisabledException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly bool $globallyEnabled = false,
        public readonly ?bool $companyEnabled = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Posting engine is disabled%s: config(accounting.engine.enabled)=%s, company#%s.posting_engine_enabled=%s.',
            $this->companyId !== null ? " for company #{$this->companyId}" : '',
            $this->globallyEnabled ? 'true' : 'false',
            $this->companyId !== null ? (string) $this->companyId : 'unresolved',
            $this->companyEnabled === null ? 'unresolved/company-not-found' : ($this->companyEnabled ? 'true' : 'false')
        ));
    }
}
