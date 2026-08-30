<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\NoOpenPeriodFoundException;
use App\Exceptions\Accounting\PeriodLockedException;
use App\Models\AccountingPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Period-lock gate consulted by {@see PostingService} on every post()/reverse() — reverse()'s own
 * enforcement is indirect (it builds a reversal DocumentDraft and calls post() with it, which runs
 * this guard the same as any other document; see PostingService::reverse()'s own docblock).
 *
 * Contract: 11-technical-implementation-plan.md ("file 11"), §P5.1; p2_5-brief.md §P2.5.A;
 * period-lock-design.md §2-§5 and §14. This class was a documented P1 no-op stub ("always allows,
 * never throws") until P2.5.A, which is this build: `accounting_periods` now exists (see that
 * migration's own docblock) and this method performs the real lookup.
 *
 * ── Resolution rule ─────────────────────────────────────────────────────────────────────────────
 * `config('accounting.period.length')` (`monthly` default | `annual`) selects the grain:
 *   - `monthly`: the row keyed (company_id, year, month) where month = 1-12 of $date.
 *   - `annual`: the row keyed (company_id, year, AccountingPeriod::ANNUAL_MONTH) — the whole
 *     year collapsed to one lockable unit (see that constant's own docblock).
 *
 * A MISSING row is treated as OPEN, not as an error and not as "locked by default". This is
 * deliberate, not an oversight: `accounting_periods` is populated by the separate, idempotent
 * `accounting:periods:init` command, not by this migration, and this guard must not retroactively
 * block posting for a company that hasn't been initialised yet (or a date in the future, past
 * whatever `accounting:periods:init` last generated up to) — the P1-era behaviour ("always allow")
 * stays the correct behaviour for exactly this "no row" case, so every accounting test written
 * before this wave (none of which provision `accounting_periods` rows) keeps passing unchanged.
 *
 * ── The three-state resolution (design doc §3/§14.2) ───────────────────────────────────────────
 *   - `open` -> pass.
 *   - `soft_closed` -> pass ONLY if the resolved actor (looked up by `$userId` — see below) holds
 *     `accounting.period.post-soft-closed` (or the same admin/accountant tier
 *     `ReconciliationService::assertCanReconcile()` already uses for the identical class of
 *     "still-privileged override" check — kept consistent with that established convention rather
 *     than inventing a second one) AND `$overrideReason` is a non-empty string. Logged at WARNING
 *     (`accounting.period_soft_closed_override`) via the file channel AND written as a permanent
 *     `accounting_audit_log` row (action `period_soft_closed_override`, `reason` = the override
 *     reason) via {@see AccountingLog::write()} — see this method's own soft_closed branch.
 *   - `locked` -> throw {@see PeriodLockedException} unless `$allowLocked` is true. `$allowLocked`
 *     is `DocumentDraft::$allowLockedPeriods` threaded down from the caller — reserved for the
 *     year-end close job only (traps: never exposed to a controller). Logged at INFO
 *     (`accounting.period_locked_override`) when taken.
 *
 * ── Actor resolution — caller-agnostic, matching PostingService's own convention ───────────────
 * `$userId` is resolved to a `User` via `User::find()`, never `Auth::user()` — this class is
 * engine-layer and is called from console/queue contexts with no authenticated user (see
 * PostingService::post()'s own docblock for the identical reasoning re: `$draft->companyId` vs.
 * `Auth::user()`). A null/unresolvable `$userId` can never pass the soft_closed override check.
 *
 * ── Signature (additive, trailing-optional — same convention DocumentDraft's own history uses) ──
 * `$userId`/`$overrideReason` are NEW trailing parameters, both defaulted, added after the P1-era
 * `(companyId, date, allowLocked)` triple so every existing call site's positional/named argument
 * list still compiles unchanged. `PostingService::post()` (the sole caller today) passes both —
 * see that class's own P2.5.A note at its step-5 call site.
 *
 * ── P2.5.B addition — {@see self::earliestOpenOnOrAfter()} ──────────────────────────────────────
 * p2_5-brief.md §P2.5.B; period-lock-design.md §8.1 ("posting_date = transaction_date whenever
 * that period is open; if ... soft_closed/locked, posting_date = the earliest currently open
 * period"). `PostingService::post()` calls `assertOpen()` first, on the caller's requested date
 * exactly as before; ONLY if that throws {@see PeriodLockedException} (i.e. no valid override was
 * supplied either) does it call this new method to find where the document should land instead —
 * see that class's own P2.5.B docblock note at its step-5 call site for the full shift sequence.
 * This method itself never throws PeriodLockedException; it either returns an open date or throws
 * {@see \App\Exceptions\Accounting\NoOpenPeriodFoundException} if the bounded forward search finds
 * nothing open at all (see that exception's own docblock for how unreachable this is meant to be
 * in practice, given "no row = open").
 */
final class PeriodGuard
{
    /**
     * Assert that $date falls within a period this company may still post into.
     *
     * @param  \DateTimeInterface  $date  the document date being posted/reversed — today this is
     *                                    `docDate`/`transaction_date` (BUG-C4); P2.5.B introduces a
     *                                    dedicated `posting_date` column and the caller passes that
     *                                    instead, with no change needed here — this method resolves
     *                                    a period for whatever DateTimeInterface it is given.
     * @param  bool  $allowLocked  bypass for a `locked` period — reserved for the year-end close
     *                             job; never exposed to a controller (traps).
     * @param  ?int  $userId  the acting user, for the `soft_closed` permission check. Resolved via
     *                        `User::find()`, never `Auth::user()` (see class docblock).
     * @param  ?string  $overrideReason  required alongside the permission check to post into a
     *                                  `soft_closed` period — `DocumentDraft::$overrideReason`.
     *
     * @throws PeriodLockedException
     */
    public function assertOpen(
        int $companyId,
        \DateTimeInterface $date,
        bool $allowLocked = false,
        ?int $userId = null,
        ?string $overrideReason = null,
    ): void {
        $carbon = Carbon::instance($date);
        [$year, $month] = $this->resolveYearMonth($carbon);

        $period = $this->findPeriodRow($companyId, $year, $month);

        // No row = open (see class docblock — deliberate, not a gap).
        if ($period === null || $period->isOpen()) {
            return;
        }

        if ($period->isLocked()) {
            if ($allowLocked) {
                // Kept as the ORIGINAL, unchanged Log::info() call — PeriodGuardTest pins this
                // exact facade call (Log::spy() + shouldHaveReceived('info')->once()) — replacing
                // it with AccountingLog::event() would both break that assertion (a different
                // facade method, Log::channel(), would be invoked instead of Log::info()) and,
                // under that same spy, crash (Log::channel() returns null on a Mockery spy). The
                // DB row is written by the separate AccountingLog::write() call directly below,
                // which touches no Log facade method at all.
                Log::info('accounting.period_locked_override', [
                    'company_id' => $companyId,
                    'year' => $year,
                    'month' => $month,
                    'user_id' => $userId,
                ]);

                AccountingLog::write(
                    action: 'period_locked_override',
                    companyId: $companyId,
                    subjectType: 'accounting_period',
                    subjectId: $period->id,
                    after: ['year' => $year, 'month' => $month],
                    actorId: $userId,
                    postingPeriod: sprintf('%04d-%02d', $year, $month),
                );

                return;
            }

            throw new PeriodLockedException($companyId, $year, $month, $period->status);
        }

        // soft_closed: pass only with permission AND a reason; refuse (and log nothing) otherwise
        // — a refused attempt carries no reason to log beyond the exception itself.
        if ($this->actorMayOverrideSoftClosed($userId) && $this->isNonEmptyReason($overrideReason)) {
            // Kept as the ORIGINAL, unchanged Log::warning() call — PeriodGuardTest pins this exact
            // facade call, same reasoning as the `locked` branch's Log::info() above. The DB row is
            // written by the separate AccountingLog::write() call directly below.
            Log::warning('accounting.period_soft_closed_override', [
                'company_id' => $companyId,
                'year' => $year,
                'month' => $month,
                'user_id' => $userId,
                'reason' => $overrideReason,
            ]);

            // P2.5.F fix-round (2026-08-30, per verify findings — CONFIRMED #2): this branch is the
            // one caller-facing path that actually carries a reason into a period override, and
            // P2.5.A's own text requires it to be "audit-logged" — with P2.5.F's accounting_audit_log
            // table now existing (this class's docblock, written when it did not, is stale on this
            // point), the file-only Log::warning() above is no longer sufficient on its own.
            AccountingLog::write(
                action: 'period_soft_closed_override',
                companyId: $companyId,
                subjectType: 'accounting_period',
                subjectId: $period->id,
                after: ['year' => $year, 'month' => $month],
                reason: $overrideReason,
                actorId: $userId,
                postingPeriod: sprintf('%04d-%02d', $year, $month),
            );

            return;
        }

        throw new PeriodLockedException($companyId, $year, $month, $period->status);
    }

    private function isNonEmptyReason(?string $reason): bool
    {
        return $reason !== null && trim($reason) !== '';
    }

    /**
     * P2.5.E addition (p2_5-brief.md §P2.5.E; period-lock-design.md §8.2): a non-throwing read of
     * the SAME period resolution {@see self::assertOpen()} performs, for callers that need to
     * DISPLAY a period's status rather than gate a write against it --
     * {@see UnlockDependencyResolver} is the first such caller, building the "period" node of the
     * unlock dependency chain ("no row = open", same convention as `assertOpen()`; see class
     * docblock). Returns one of {@see AccountingPeriod}'s own STATUS_* constants -- never throws.
     */
    public function statusFor(int $companyId, \DateTimeInterface $date): string
    {
        $carbon = Carbon::instance($date);
        [$year, $month] = $this->resolveYearMonth($carbon);
        $period = $this->findPeriodRow($companyId, $year, $month);

        return $period?->status ?? AccountingPeriod::STATUS_OPEN;
    }

    /**
     * How many periods forward {@see self::earliestOpenOnOrAfter()} will search (monthly: ~20
     * years; annual: 240 years) before giving up and throwing
     * {@see \App\Exceptions\Accounting\NoOpenPeriodFoundException}. Generous on purpose — see that
     * exception's own docblock for why this is meant to be practically unreachable.
     */
    private const MAX_LOOKAHEAD_PERIODS = 240;

    /**
     * P2.5.B (see class docblock's "P2.5.B addition" section). Finds the earliest period, starting
     * AT `$from`'s own period and walking forward, that is open (or has no `accounting_periods` row
     * at all — "no row = open", same convention {@see self::assertOpen()} uses). Returns the FIRST
     * DAY of that period (year/month-01, or year-01-01 under `annual` length) — posting_date
     * identifies a PERIOD for bucketing purposes, not a specific calendar day within it (design doc
     * §8.1: "posting_date = the earliest currently open period").
     *
     * Never throws {@see PeriodLockedException} — that is `assertOpen()`'s job, and
     * `PostingService::post()` calls this method only from inside the catch block of an
     * `assertOpen()` call that already threw it (see that class's step-5 docblock). This method's
     * own only failure mode is {@see \App\Exceptions\Accounting\NoOpenPeriodFoundException}, thrown
     * if nothing within {@see self::MAX_LOOKAHEAD_PERIODS} periods is open.
     */
    public function earliestOpenOnOrAfter(int $companyId, \DateTimeInterface $from): Carbon
    {
        $length = (string) config('accounting.period.length', 'monthly');
        $cursor = Carbon::instance($from)->startOfDay();

        for ($i = 0; $i <= self::MAX_LOOKAHEAD_PERIODS; $i++) {
            [$year, $month] = $this->resolveYearMonth($cursor);
            $period = $this->findPeriodRow($companyId, $year, $month);

            if ($period === null || $period->isOpen()) {
                return $length === 'annual'
                    ? Carbon::create($year, 1, 1)->startOfDay()
                    : Carbon::create($year, $month, 1)->startOfDay();
            }

            $cursor = $length === 'annual'
                ? $cursor->copy()->addYear()->startOfYear()
                : $cursor->copy()->addMonthNoOverflow()->startOfMonth();
        }

        throw new NoOpenPeriodFoundException($companyId, $from, self::MAX_LOOKAHEAD_PERIODS);
    }

    /** @return array{0: int, 1: int} [year, month] — month is {@see AccountingPeriod::ANNUAL_MONTH} under annual length. */
    private function resolveYearMonth(Carbon $date): array
    {
        $length = (string) config('accounting.period.length', 'monthly');
        $year = (int) $date->format('Y');
        $month = $length === 'annual' ? AccountingPeriod::ANNUAL_MONTH : (int) $date->format('n');

        return [$year, $month];
    }

    private function findPeriodRow(int $companyId, int $year, int $month): ?AccountingPeriod
    {
        /** @var AccountingPeriod|null */
        return AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Same dual-check convention {@see ReconciliationService::assertCanReconcile()} already
     * establishes for "still-privileged override" checks in this codebase: an admin/accountant
     * role tier OR the explicit Spatie permission — never only one. `$user->can()` degrades to
     * `false` (never throws) when the permission row does not exist yet, via Spatie's
     * `checkPermissionTo()` — see that method's own docblock; this codebase's other `accounting.*`
     * ability strings (e.g. `accounting.reconcile`) are used the identical way today with no
     * PermissionSeeder row of their own.
     */
    private function actorMayOverrideSoftClosed(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $actor = User::find($userId);

        if ($actor === null) {
            return false;
        }

        return $actor->hasRole('admin')
            || $actor->hasRole('accountant')
            || in_array($actor->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $actor->can('accounting.period.post-soft-closed');
    }
}
