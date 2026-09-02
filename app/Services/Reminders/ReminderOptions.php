<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I). Resolves every company option this sub-wave adds, via the SAME
 * `settings` table / Setting::getByKey() key-namespacing convention
 * {@see \App\Services\Accounting\StatementOptions} already established -- a plain per-company
 * key/value row, read with a config('accounting.reminders') default when no row exists.
 *
 * Queue/console-safe by the same convention every other engine-layer class in this codebase
 * follows: $companyId is a plain argument, never resolved from Auth::user().
 */
final class ReminderOptions
{
    public const KINDS = [
        'overdue_invoice', 'statement_balance', 'ticketing_deadline', 'commission_unearned',
        'payment_link_uninvoiced', 'task_unassigned', 'task_uninvoiced', 'custom',
    ];

    public const CHANNELS = ['whatsapp', 'email', 'both'];

    public static function enabled(int $companyId, string $kind): bool
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, "accounting.reminders.{$kind}.enabled", null) : null;

        if ($value === null) {
            // soud: fail-safe default is OFF, not the akeed-original true -- a kind missing from
            // config/accounting.php's reminders.default_enabled (e.g. a new KINDS entry added
            // without a matching default_enabled key) must never silently start sending, per the
            // same "all kinds ship OFF; opt in per company" rule applied to that config array.
            return (bool) config("accounting.reminders.default_enabled.{$kind}", false);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function channel(int $companyId, string $kind): string
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, "accounting.reminders.{$kind}.channel", null) : null;
        $resolved = $value ?? config('accounting.reminders.default_channel', 'whatsapp');

        return in_array($resolved, self::CHANNELS, true) ? $resolved : 'whatsapp';
    }

    /** @return int[] Days-past-due offsets `overdue_invoice` re-fires on. */
    public static function overdueInvoiceOffsetsDays(int $companyId): array
    {
        $raw = $companyId > 0 ? Setting::getByKey($companyId, 'accounting.reminders.overdue_invoice.offsets_days', null) : null;
        $raw = $raw ?? implode(',', config('accounting.reminders.overdue_invoice_offsets_days', [1, 3, 7, 14, 30]));

        $offsets = array_values(array_unique(array_filter(
            array_map(static fn (string $part): int => (int) trim($part), explode(',', (string) $raw)),
            static fn (int $days): bool => $days > 0
        )));

        return $offsets !== [] ? $offsets : [1, 3, 7, 14, 30];
    }

    public static function dailyRunTime(int $companyId): string
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, 'accounting.reminders.daily_run_time', null) : null;

        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value)
            ? $value
            : (string) config('accounting.reminders.daily_run_time', '09:00');
    }

    /** @return array{start: ?string, end: ?string} HH:MM strings, or both null if disabled. */
    public static function quietHours(int $companyId): array
    {
        $raw = $companyId > 0 ? Setting::getByKey($companyId, 'accounting.reminders.quiet_hours', null) : null;

        if (is_string($raw) && preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', trim($raw), $m)) {
            return ['start' => $m[1], 'end' => $m[2]];
        }

        $default = config('accounting.reminders.quiet_hours', ['start' => null, 'end' => null]);

        return ['start' => $default['start'] ?? null, 'end' => $default['end'] ?? null];
    }

    /**
     * Shifts $scheduledAt forward to the end of the configured quiet-hours window if it falls
     * inside it; returns $scheduledAt unchanged when quiet hours are disabled or the time falls
     * outside the window. Handles an overnight window (start > end, e.g. "22:00"-"07:00") by
     * wrapping across midnight.
     */
    public static function shiftForQuietHours(int $companyId, Carbon $scheduledAt): Carbon
    {
        $quiet = self::quietHours($companyId);
        if ($quiet['start'] === null || $quiet['end'] === null) {
            return $scheduledAt;
        }

        $day = $scheduledAt->copy()->startOfDay();
        $start = $day->copy()->setTimeFromTimeString($quiet['start']);
        $end = $day->copy()->setTimeFromTimeString($quiet['end']);

        if ($start->equalTo($end)) {
            return $scheduledAt; // a zero-width window is treated as "disabled", not "always quiet".
        }

        if ($start->lessThan($end)) {
            // Same-day window, e.g. 13:00-15:00.
            if ($scheduledAt->between($start, $end)) {
                return $end->copy();
            }

            return $scheduledAt;
        }

        // Overnight window, e.g. 22:00-07:00: quiet from $start through midnight, then from
        // midnight through $end the next morning.
        if ($scheduledAt->greaterThanOrEqualTo($start)) {
            return $end->copy()->addDay();
        }

        if ($scheduledAt->lessThanOrEqualTo($end)) {
            return $end->copy();
        }

        return $scheduledAt;
    }
}
