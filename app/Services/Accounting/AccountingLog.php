<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F): the two writer entry points feeding `accounting_audit_log` beyond
 * the engine's own direct write in {@see PostingService::post()}/{@see PostingService::reverse()}:
 *
 *   - {@see self::write()} — a direct, structured row for a Gate::authorize-guarded mutation
 *     (writer "(b)": period close/reopen, unlock, reconcile/unreconcile, refund approve/reject,
 *     company option changes) — called with real `before`/`after` snapshots by the owning
 *     service/controller.
 *   - {@see self::event()} — writer "(c)": mirrors one of the 15 `accounting.*` `Log::info()`
 *     events (verified count against the working tree 2026-08-30 — see the grep-confirmed list
 *     below) into the SAME table, via the `AccountingLog::event() helper` option the brief itself
 *     offers as an alternative to a Monolog handler. Chosen over a handler because it is directly
 *     unit-testable with no Monolog channel plumbing to fake, and because 5 of the 15 (the ones
 *     ALSO named as "(b)" gated mutations: `period_closed`, `period_reopened`, `record_unlocked`,
 *     `refund_approved`, `refund_rejected`) already get a richer, purpose-built row via
 *     {@see self::write()} at their own call site — mirroring those through this generic path too
 *     would double-write one audit row per action. This method still logs those 5 to the file-only
 *     'accounting' channel where they are called (unchanged), it simply is not ALSO called for them.
 *
 *   Full 15-event roster and which path each takes (grepped `Log::info\('accounting\.` across
 *   app/, 2026-08-30 — exactly 15 distinct event names; the `action`/first-arg values below are
 *   the literal strings each call site actually uses, NOT necessarily the same spelling as the
 *   `accounting.<name>` file-log event next to it — the DB row's `action` column deliberately
 *   uses the brief's own short migration-comment vocabulary (`lock`/`soft_close`/`reopen`/`unlock`/
 *   `approve`/`reject`/...) rather than echoing the longer file-log event name verbatim):
 *     write() (richer, before/after):  period_closed  -> action 'lock'/'soft_close' (PeriodCloseService)
 *                                       period_reopened -> action 'reopen' (PeriodCloseService)
 *                                       record_unlocked -> action 'unlock' (Lockable)
 *                                       refund_approved -> action 'approve' (RefundController)
 *                                       refund_rejected -> action 'reject' (RefundController)
 *                                       period_locked_override -> action 'period_locked_override' (PeriodGuard)
 *                                       posting_date_shifted -> action 'posting_date_shifted' (PostingService)
 *                                       legacy_path -> action 'legacy_path' (PostingSeam) — FIXED
 *                                       2026-08-30: this call previously existed as a file-only
 *                                       Log::info() with no DB mirror at all, despite already being
 *                                       named here as covered; see PostingSeam::post()'s own
 *                                       comment at that call site for why write() (not event())
 *                                       was used for the fix.
 *                                       period_soft_closed_override -> action
 *                                       'period_soft_closed_override' (PeriodGuard) — FIXED
 *                                       2026-08-30 (verify findings, CONFIRMED #2): this call sits
 *                                       on `Log::warning()`, not `Log::info()`, which is exactly why
 *                                       the "grepped Log::info\('accounting\.'" 15-event count above
 *                                       never caught it as a 16th event needing a DB mirror — it had
 *                                       NO DB write of either kind until this fix, despite being the
 *                                       one caller-facing override path that actually carries a
 *                                       reason (P2.5.A's own text requires this event be
 *                                       "audit-logged"). See PeriodGuard::assertOpen()'s own
 *                                       soft_closed branch.
 *     event() (this class, generic):   gateway_refund_completed, gateway_refund_rejected,
 *                                       refund_store_draft_deferred, refund_update_draft_deferred,
 *                                       refund_crn_legacy, revenue_recognized,
 *                                       revenue_recognition_leaf_unmapped
 *
 * Both paths ALWAYS keep the original file log line — `event()` logs through
 * `Log::channel('accounting')` (config/logging.php: writes to storage/logs/accounting/accounting.log
 * AND, via this method, the DB) rather than removing the file trail (p2_5-brief.md: "keep file
 * logs").
 */
final class AccountingLog
{
    /**
     * Writer (a)/(b): a direct, structured audit row. `$action` should be a short verb
     * (`post`, `reverse`, `close`, `unlock`, ...), never `accounting.`-prefixed — the table's own
     * `action` column is already scoped to the accounting domain by virtue of the table itself.
     */
    public static function write(
        string $action,
        ?int $companyId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $transactionId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?int $actorId = null,
        ?string $actorType = null,
        \DateTimeInterface|string|null $postingPeriod = null,
    ): AccountingAuditLog {
        return AccountingAuditLog::create([
            'company_id' => $companyId,
            'actor_id' => $actorId,
            'actor_type' => $actorType ?? ($actorId !== null ? 'user' : 'system'),
            'action' => mb_substr($action, 0, 48),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'transaction_id' => $transactionId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => self::currentIp(),
            'route' => self::currentRouteName(),
            'posting_period' => self::normalizePostingPeriod($postingPeriod),
            'created_at' => now(),
        ]);
    }

    /**
     * Writer (c): mirrors one `accounting.<eventName>` log line into the file channel AND a DB row,
     * with best-effort field extraction from the same `$context` array the file log line already
     * carries — see class docblock for the exact 10 events that call this today.
     */
    public static function event(string $eventName, array $context = [], string $level = 'info'): AccountingAuditLog
    {
        // Defensive: Log::channel() returns null when the Log facade has been replaced with a
        // Mockery spy/fake (Log::spy()/Log::fake()), a pattern many existing accounting tests use
        // to assert on an UNRELATED log call elsewhere in the same test. This must never crash the
        // DB write below just because some other part of the same request also happens to log.
        try {
            Log::channel('accounting')?->log($level, "accounting.{$eventName}", $context);
        } catch (\Throwable) {
            // File-log mirroring is best-effort; the DB row below is the record of truth.
        }

        $companyId = self::intOrNull($context['company_id'] ?? null);
        $actorId = self::intOrNull($context['user_id'] ?? $context['actor_id'] ?? null);
        $transactionId = self::intOrNull($context['transaction_id'] ?? null);
        [$subjectType, $subjectId] = self::inferSubject($context);
        $postingPeriod = $context['posting_period']
            ?? $context['resolved_posting_date']
            ?? (isset($context['year'], $context['month']) ? sprintf('%04d-%02d', (int) $context['year'], (int) $context['month']) : null);

        return self::write(
            action: $eventName,
            companyId: $companyId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            transactionId: $transactionId,
            before: null,
            after: $context,
            reason: $context['reason'] ?? null,
            actorId: $actorId,
            postingPeriod: $postingPeriod,
        );
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private static function inferSubject(array $context): array
    {
        if (isset($context['subject_type'])) {
            return [self::normalizeSubjectType((string) $context['subject_type']), self::intOrNull($context['subject_id'] ?? null)];
        }

        if (isset($context['refund_id'])) {
            return ['refund', self::intOrNull($context['refund_id'])];
        }

        if (isset($context['refund_detail_id']) && ! isset($context['refund_id'])) {
            return ['refund_detail', self::intOrNull($context['refund_detail_id'])];
        }

        if (isset($context['task_id'])) {
            return ['task', self::intOrNull($context['task_id'])];
        }

        // Period-shaped context (company_id + year + month, no other subject) — resolve the real
        // accounting_periods row id when one already exists, rather than leaving subject_id null
        // for an event that genuinely does name one calendar period.
        if (isset($context['company_id'], $context['year'], $context['month']) && ! isset($context['transaction_id'])) {
            $periodId = AccountingPeriod::query()
                ->where('company_id', (int) $context['company_id'])
                ->where('year', (int) $context['year'])
                ->where('month', (int) $context['month'])
                ->value('id');

            return ['accounting_period', $periodId !== null ? (int) $periodId : null];
        }

        if (isset($context['transaction_id'])) {
            return ['transaction', self::intOrNull($context['transaction_id'])];
        }

        return [null, null];
    }

    /**
     * `AuditLogLinker`'s own contract names the short strings (invoice, payment, invoice_receipt,
     * transaction, journal_entry, accounting_period, ...) — a caller passing a raw FQCN (e.g.
     * {@see \App\Http\Traits\Lockable::unlock()}'s `static::class`) is normalized to that same
     * vocabulary so a filter never has to know about both spellings.
     */
    public static function normalizeSubjectTypePublic(string $raw): string
    {
        return self::normalizeSubjectType($raw);
    }

    private static function normalizeSubjectType(string $raw): string
    {
        return match ($raw) {
            \App\Models\Transaction::class => 'transaction',
            \App\Models\JournalEntry::class => 'journal_entry',
            \App\Models\Invoice::class => 'invoice',
            \App\Models\Refund::class => 'refund',
            \App\Models\AccountingPeriod::class => 'accounting_period',
            default => str_contains($raw, '\\')
                ? \Illuminate\Support\Str::snake(class_basename($raw))
                : $raw,
        };
    }

    private static function normalizePostingPeriod(\DateTimeInterface|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m');
        }

        // Already a 'YYYY-MM-DD'/'YYYY-MM' string (e.g. context's own 'resolved_posting_date') —
        // take the first 7 characters rather than round-tripping through Carbon::parse(), which
        // would throw on a value that is already exactly 'YYYY-MM'.
        return mb_substr($value, 0, 7);
    }

    private static function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function currentIp(): ?string
    {
        if (app()->runningInConsole() && ! app()->bound('request')) {
            return null;
        }

        try {
            return request()?->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function currentRouteName(): ?string
    {
        try {
            return RouteFacade::currentRouteName();
        } catch (\Throwable) {
            return null;
        }
    }
}
