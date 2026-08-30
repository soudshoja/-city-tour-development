<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\MissingIdempotencyKeyException;
use App\Exceptions\Accounting\PostingEngineDisabledException;
use App\Exceptions\Accounting\PostingException;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * THE ONE SEAM (R3 decision, 2026-08-26): every feeder calls THIS class, never
 * {@see PostingService} directly. It is the route-to-legacy cutover point described in
 * Accounting Gap/11-technical-implementation-plan.md §C2 ("Every migrated call site in P2 calls
 * the engine through one seam") — narrowed, for this build, to a plain two-way route (legacy vs.
 * engine) rather than §C2's full three-mode legacy/shadow/live design; shadow-mode dual-write/diff
 * is explicitly out of this class's scope and may be layered on top of it later without changing
 * this contract.
 *
 * ── Why the gate is duplicated here, ahead of PostingService's own ────────────────────────────
 * PostingService::post() already has its own W0 kill-switch gate (identical two-flag check) and
 * throws PostingEngineDisabledException when it fails — so this class's own pre-check in
 * isEnabledFor()/post() is, on the happy path, redundant with that. It is duplicated anyway
 * because the two gates serve different callers with different failure contracts:
 *   - PostingService's gate protects PostingService itself against ANY caller, engine-path or
 *     not, and fails by THROWING.
 *   - This seam's gate exists so a FEEDER — which has legacy behaviour to fall back to — never
 *     needs to know PostingEngineDisabledException exists at all. Checking here first means the
 *     overwhelmingly common case (engine off) is a plain Log::info + closure call, not a
 *     throw/catch round-trip through PostingService on every single legacy-path post.
 *   - The race window between this seam's check and the PostingService::post() call it makes on
 *     the ON path is real (another request can flip either flag in between) — see the ON branch
 *     of post() below for how that specific case is handled. PostingEngineDisabledException must
 *     therefore NEVER be allowed to reach a feeder: if it happens anyway, it is a race, not a
 *     genuine "route this to legacy" instruction, and is handled as such.
 *
 * ── Usage (how a feeder wraps its existing code) ───────────────────────────────────────────────
 * ```php
 * return $seam->post($draft, function () use (...) {
 *     // legacy JournalEntry::create code, moved here VERBATIM — behaviour byte-identical to HEAD.
 *     return JournalEntry::create([...]);
 * }, 'myfatoorah.payment');
 * ```
 * The closure's return value passes straight through on the OFF path; on the ON path this method
 * returns the engine's PostedDocument instead and the closure never runs at all. A feeder that
 * needs different shapes back from the two paths should normalise inside its own call site (e.g.
 * pull the fields it needs out of either a legacy model or a PostedDocument) — this class does not
 * paper over that difference, since making the two paths behave identically end-to-end is a
 * feeder-by-feeder concern, not something this seam can decide generically.
 *
 * ── Contract, mirroring the mission brief exactly ──────────────────────────────────────────────
 *   - Company is resolved strictly from `$draft->companyId` via `Company::find()` — NEVER
 *     `Auth::user()` — matching PostingService's own gate (console/queue/webhook callers have no
 *     authenticated user).
 *   - OFF (global flag false, company flag false, OR company unresolvable): logs
 *     `accounting.legacy_path` at INFO and returns `$legacy()` verbatim — including its return
 *     value and any exception it throws (this class does not wrap or interpret the legacy
 *     closure's own failures; only the ENGINE path's failures are this class's concern).
 *   - ON (both flags true): requires a non-empty `$draft->idempotencyKey`
 *     ({@see MissingIdempotencyKeyException} if missing — thrown BEFORE PostingService::post() is
 *     ever called, so nothing is written and no document number is reserved), then calls
 *     `PostingService::post($draft)` and returns its `PostedDocument` unchanged.
 *   - Any `PostingException` other than a raced `PostingEngineDisabledException` (see below) is
 *     logged at CRITICAL with feeder/company/idempotency-key/exception-class/message and
 *     RETHROWN untouched — an engine correctness failure (unbalanced draft, frozen account,
 *     tenant mismatch, superseded idempotency key…) must be LOUD and must NEVER silently fall
 *     back to `$legacy()`, per the R3 decision: a fallback there would let the legacy path
 *     double-post the same real-world event the engine just refused to post safely. `\Throwable`
 *     is never caught — only the sealed `PostingException` hierarchy this seam is contractually
 *     aware of.
 *   - The one exception to "never fall back": a `PostingEngineDisabledException` that reaches
 *     this seam's own `try` block despite the pre-check having passed can only mean the flag was
 *     flipped between this seam's check and the `PostingService::post()` call — a genuine race,
 *     not an engine correctness failure. That case logs `accounting.engine_disabled_race` at
 *     WARNING and routes to `$legacy()`, exactly as the OFF path would have if the race had
 *     resolved a few milliseconds earlier.
 *
 * ── W1.1 FIX ROUND (seam hardening — W1 lead report §3, S1/C4/S3/S4; findings against W1's OWN
 *    additive seam, none of them present at HEAD) ─────────────────────────────────────────────────
 *   • S1 — the OFF path now checks whether the ENGINE already posted this exact
 *     `(company_id, idempotency_key)` pair before running `$legacy()` at all (a real possibility:
 *     an earlier call posted through the engine, then a kill-switch flip took this company back to
 *     the OFF path). If a live transaction already exists under that key, `$legacy()` is NEVER
 *     invoked — this method logs `accounting.legacy_skip_already_posted` at WARNING and
 *     **returns `null`** instead. THIS IS THE ONE CASE WHERE `post()` CAN RETURN A BARE `null`
 *     (every other path returns either the legacy closure's own return value or a
 *     `PostedDocument`, or throws) — every feeder must tolerate it. Verified against all three W1.1
 *     call sites (`CheckMyFatoorahPayments`, `AgentController::update()`, `ChatController::
 *     postChatInvoiceTaskEntries()`): none of them assign or dereference this method's return
 *     value, so none needed a code change for this — noted here so a FUTURE feeder does not learn
 *     the hard way that this method's return type is not "always truthy on success".
 *   • C4 — a caller-supplied `companyId <= 0` (an unresolvable company — W1's own chat feeder
 *     reproduced this: `(int) $taskBranch?->company_id` casts a null branch's company id to 0) used
 *     to fall through to the exact same `accounting.legacy_path` INFO line as an ordinary
 *     flag-disabled decision, with nothing to distinguish "this feeder's company resolution is
 *     broken" from "the engine is deliberately off here" — both look identical in the log. Now
 *     logged separately, at ERROR, as `accounting.company_unresolvable`, whenever this happens
 *     while the GLOBAL flag reads ON (when the global flag is off, every draft routes to legacy
 *     regardless of companyId, so there is nothing anomalous about this one doing the same — the
 *     signal is specifically "this looked like it should have gone to the engine and couldn't").
 *     Routing itself is UNCHANGED — legacy still runs, matching HEAD's own tolerance for this case.
 *   • S3 — `MissingIdempotencyKeyException`, thrown before `PostingService::post()` ever runs, used
 *     to skip the `accounting.engine_failure` channel entirely — every OTHER engine-path failure
 *     (the catch block below) logs there at CRITICAL first. An operator grepping only that one
 *     channel for "which engine-path attempts failed and why" silently missed this failure mode.
 *     Now logged there too, with the same shape (`exception_class` included), before the throw.
 *   • S4 — this method now resolves `Company` **at most once per call**: the whole gate (global
 *     flag + company flag) is computed inline from one `Company::find()`, rather than delegating to
 *     `isEnabledFor()` (which does its own, separate find) and then needing a second lookup to
 *     answer S1/C4's "why did the gate fail" questions. `isEnabledFor()` itself is UNCHANGED and
 *     still does its own independent find — it remains a public probe a caller (or a test) can
 *     invoke standalone, on its own; this method simply no longer routes through it internally.
 *     This is unrelated to, and does not reduce, the SEPARATE `Company::find()` `PostingService::
 *     post()` performs on its own independent gate a moment later on the ON path — that second,
 *     cross-class read stays deliberate (see "Why the gate is duplicated here" above): it is what
 *     the race test (`PostingSeamTest::
 *     test_race_disabled_between_seam_check_and_engine_gate_routes_to_legacy_with_warning`) relies
 *     on to exist at all.
 *
 * ── P2.5.A ADDITION (period-lock-design.md §14.3: "period enforcement is FREE once PeriodGuard
 *    ships real logic — no wave builds its own period check") ───────────────────────────────────
 * This class now also takes a {@see PeriodGuard} dependency and calls `assertOpen()` in the
 * OFF/legacy branch, right before invoking `$legacy()`, so a legacy writer routed through this seam
 * gets the same period enforcement the ON path already gets for free (PostingService::post()'s own
 * step 5) — closing the one gap `p2_5-brief.md` §P2.5.A named explicitly ("confirm the seam legacy
 * branch also invokes the guard"). A `PeriodLockedException` raised there propagates uncaught, the
 * same way any other exception a real `$legacy()` closure might throw already does — this class
 * does not wrap or interpret it.
 */
final class PostingSeam
{
    public function __construct(
        // Plain constructor DI, matching PostingService: AccountingServiceProvider::register()
        // binds nothing explicitly for PostingService either (its `register()` body is empty —
        // see that provider's own docblock), so PostingService is resolved by Laravel's automatic
        // concrete-class resolution today, and this class is wired the identical way rather than
        // introducing a binding convention PostingService itself does not use.
        private PostingService $postingService,
        // P2.5.A addition (p2_5-brief.md §P2.5.A: "confirm the seam legacy branch also invokes the
        // guard [PeriodGuard] — add the single call if missing"). Verified missing: the ON path
        // already gets period enforcement for free (PostingService::post()'s own step 5), but the
        // OFF/legacy branch below used to call `$legacy()` with no period check at all — any
        // legacy writer routed through this seam was completely outside PeriodGuard's reach.
        // Resolved the same way PostingService itself is (automatic concrete-class resolution, no
        // explicit binding).
        private PeriodGuard $periods,
    ) {}

    /**
     * Whether the engine is the live writer for this company RIGHT NOW — both
     * `config('accounting.engine.enabled')` and `companies.posting_engine_enabled` must be true.
     * Exposed publicly so a feeder (or a test) can ask "which path would this take?" without
     * actually posting anything, and so this seam and PostingService's own gate share one
     * authoritative definition of "enabled" rather than two independently-maintained copies of
     * the same two-flag check silently drifting apart.
     *
     * Fails CLOSED on every ambiguous case, matching PostingService::post()'s own gate: engine
     * off, company flag off, or company unresolvable all return false identically.
     */
    public function isEnabledFor(int $companyId): bool
    {
        $globallyEnabled = (bool) config('accounting.engine.enabled');

        if (! $globallyEnabled) {
            return false;
        }

        $company = Company::find($companyId);

        return $this->computeEnabled($globallyEnabled, $company);
    }

    /**
     * Shared "is the engine live for this resolved company" predicate — the one authoritative
     * definition {@see isEnabledFor()} and {@see post()} both evaluate, so a caller resolving
     * Company once for {@see post()} (W1.1 fix, S4 — see class docblock) doesn't have to duplicate
     * this three-part check by hand.
     */
    private function computeEnabled(bool $globallyEnabled, ?Company $company): bool
    {
        return $globallyEnabled && $company !== null && (bool) $company->posting_engine_enabled;
    }

    /**
     * Route one document: legacy closure when the engine is off for this draft's company, the
     * live engine when it is on. See class docblock for the full contract and the usage example.
     *
     * @param  \Closure(): mixed  $legacy  The feeder's existing posting code, moved here verbatim.
     *                                     Invoked with no arguments; whatever it returns (or throws) passes straight through
     *                                     on the OFF path.
     * @param  string  $feederKey  A stable, human-readable identifier for the call site (e.g.
     *                             'myfatoorah.payment', 'invoice.issue') — written into every log line this method
     *                             produces so an operator can trace which feeder took which path for which
     *                             company/key.
     * @return mixed The legacy closure's return value (OFF path); a {@see PostedDocument} (ON
     *               path); or a bare `null` (W1.1 fix, S1 — OFF path ONLY, and only when the
     *               engine had already posted this exact `(company_id, idempotency_key)` pair
     *               before a kill-switch flip: `$legacy()` is deliberately never invoked in that
     *               case, to avoid double-posting the same real-world event — see class docblock's
     *               W1.1 FIX ROUND / S1). Every feeder must tolerate this null.
     *
     * @throws MissingIdempotencyKeyException When both flags are ON but `$draft->idempotencyKey`
     *                                        is null/empty — thrown before `PostingService::post()`
     *                                        is called, so nothing is written. Logged to
     *                                        `accounting.engine_failure` at CRITICAL first (W1.1
     *                                        fix, S3), matching every other engine-path failure.
     * @throws PostingException Rethrown, after Log::critical, for any engine-path failure other
     *                          than a raced PostingEngineDisabledException (see class docblock).
     *
     * W1.2 note: a caller MAY wrap this call in its own `DB::transaction()` (e.g. to commit a
     * related model update atomically with the post, as {@see \App\Http\Controllers\AgentController::update()}
     * does) — on MySQL, `PostingService::post()`'s own transaction then opens as a SAVEPOINT
     * rather than a second top-level transaction, so both still commit or roll back together;
     * the only cost is that `PostingService`'s internal deadlock-retry becomes inert for that
     * call (see `PostingService::TRANSACTION_RETRY_ATTEMPTS`'s own docblock), which is acceptable
     * for a single synchronous request with no other retry path.
     *
     * W2 note (D1 — orchestrator design call, gateway-payment cutover): the same SAVEPOINT
     * nesting above is the SANCTIONED pattern for posting from INSIDE a `DB::transaction()` that
     * also holds a `Payment::lockForUpdate()` row lock (the P0 hotfix shape already present in
     * `PaymentController`'s gateway-completion entry points) — this call site is atomic with the
     * payment's own completion write, which a post-after-commit alternative could never guarantee
     * (a crash between the payment commit and this call would silently lose the accounting
     * entry). `PostingService`'s deadlock retry is inert here for the same reason as the
     * AgentController case above, but the surrounding row lock is what actually matters for a
     * gateway feeder: unlike a synchronous UI request, concurrent redelivery of the SAME webhook
     * (or a webhook racing a status-check cron over the same payment) is the norm, not the
     * exception, and it is `Payment::lockForUpdate()` — not this seam's or PostingService's own
     * retry — that serializes those redeliveries so only one of them ever reaches this call for a
     * given payment at a time.
     */
    public function post(DocumentDraft $draft, \Closure $legacy, string $feederKey): mixed
    {
        $companyId = $draft->companyId;
        $idempotencyKey = $draft->idempotencyKey;

        // S4 fix (W1.1): resolve Company at most ONCE for this call — see class docblock's W1.1
        // FIX ROUND / S4 for why this is deliberately NOT the same as, or a reduction of, the
        // separate find PostingService::post() performs on its own gate a moment later.
        $globallyEnabled = (bool) config('accounting.engine.enabled');
        $company = $companyId > 0 ? Company::find($companyId) : null;
        $enabledForCompany = $this->computeEnabled($globallyEnabled, $company);

        if (! $enabledForCompany) {
            // C4 fix (W1.1): see class docblock's W1.1 FIX ROUND / C4. Routing is unchanged
            // (legacy still runs, matching HEAD's own tolerance for an unresolvable company) —
            // only the silence is fixed.
            if ($companyId <= 0 && $globallyEnabled) {
                Log::error('accounting.company_unresolvable', [
                    'feeder' => $feederKey,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            // S1 fix (W1.1): see class docblock's W1.1 FIX ROUND / S1. Deliberately bypasses every
            // global scope on Transaction EXCEPT the soft-delete one — the same two concerns
            // PostingService::findByIdempotencyKey() already documents: Transaction's
            // company-authorization scope is keyed off the ambient authenticated user (a no-op for
            // a console/queue caller, but a real filter for an HTTP request), so it must be
            // bypassed to ask "does this row exist for THIS draft's company" rather than "...for
            // whichever company the ambient auth user happens to belong to"; SoftDeletingScope is
            // kept — a soft-deleted transaction was never "the engine already posted this", so it
            // must never suppress a legitimate legacy post.
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = Transaction::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('company_id', $companyId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first(['id']);

                if ($existing !== null) {
                    Log::warning('accounting.legacy_skip_already_posted', [
                        'feeder' => $feederKey,
                        'company_id' => $companyId,
                        'idempotency_key' => $idempotencyKey,
                        'transaction_id' => $existing->id,
                    ]);

                    return null;
                }
            }

            // P2.5.A addition — see constructor's own comment. Runs only once we're actually about
            // to invoke $legacy() (i.e. past the S1 already-posted-via-engine short-circuit above,
            // which returns null without writing anything and has no document to period-check).
            // A missing accounting_periods row resolves to "open" (PeriodGuard's own docblock), so
            // this is a no-op for every company that hasn't run accounting:periods:init yet —
            // every existing legacy-path test in this suite is unaffected.
            $this->periods->assertOpen(
                $companyId,
                $draft->docDate,
                $draft->allowLockedPeriods,
                $draft->userId,
                $draft->overrideReason,
            );

            Log::info('accounting.legacy_path', [
                'feeder' => $feederKey,
                'company_id' => $companyId,
                'idempotency_key' => $idempotencyKey,
            ]);

            // P2.5.F fix (verified gap, 2026-08-30): this was one of the 15 accounting.* events
            // AccountingLog's own class docblock already claimed were mirrored into
            // accounting_audit_log, but no write() or event() call for it actually existed anywhere
            // -- a document posted via the engine-OFF legacy path left NO Log Center row at all,
            // unlike every engine-ON post()/reverse() (which each get their own row). Kept as a
            // direct write() (not event()) precisely so it does not touch the Log facade a second
            // time and risk interfering with a future Log::spy() assertion on this exact call site
            // the way AccountingLog::event()'s Log::channel('accounting')->log() call would.
            AccountingLog::write(
                action: 'legacy_path',
                companyId: $companyId,
                subjectType: 'transaction',
                after: [
                    'feeder' => $feederKey,
                    'idempotency_key' => $idempotencyKey,
                ],
                postingPeriod: $draft->docDate,
            );

            return $legacy();
        }

        if ($idempotencyKey === null || $idempotencyKey === '') {
            // S3 fix (W1.1): see class docblock's W1.1 FIX ROUND / S3 — logged to
            // accounting.engine_failure at CRITICAL, same shape as every other engine-path
            // failure below, before throwing.
            $exception = new MissingIdempotencyKeyException($feederKey, $companyId);

            Log::critical('accounting.engine_failure', [
                'feeder' => $feederKey,
                'company_id' => $companyId,
                'idempotency_key' => $idempotencyKey,
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        try {
            return $this->postingService->post($draft);
        } catch (PostingEngineDisabledException $e) {
            // Race: both flags read ON a moment ago, in this method's own gate above (W1.1 fix,
            // S4 — computed inline from one Company::find(), no longer via a call to
            // isEnabledFor()), but PostingService::post() re-reads them itself (it has no way to
            // trust a pre-check performed outside its own transaction) and found at least one OFF
            // by the time it ran — the only way this specific exception can reach this catch block
            // at all, since $enabledForCompany above already evaluated true. This is a timing
            // accident, not a "route this to legacy" instruction from either flag's actual
            // intended value, so it is logged
            // distinctly from the ordinary legacy_path case and routed to the same legacy closure
            // as a safe fallback — the closure runs exactly the code this feeder would already be
            // running had the flag flip happened a few milliseconds earlier.
            Log::warning('accounting.engine_disabled_race', [
                'feeder' => $feederKey,
                'company_id' => $companyId,
                'idempotency_key' => $idempotencyKey,
                'message' => $e->getMessage(),
            ]);

            return $legacy();
        } catch (PostingException $e) {
            // A genuine engine correctness failure (unbalanced draft, frozen/non-leaf/cross-tenant
            // account, superseded idempotency key, missing currency, …) — never swallowed, never
            // downgraded to a legacy fallback (see class docblock: falling back here would let the
            // legacy path double-post the same event the engine just refused). Loud and rethrown.
            Log::critical('accounting.engine_failure', [
                'feeder' => $feederKey,
                'company_id' => $companyId,
                'idempotency_key' => $idempotencyKey,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
