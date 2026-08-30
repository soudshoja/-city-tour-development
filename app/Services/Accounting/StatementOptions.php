<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Setting;

/**
 * P2.5.H (p2_5-brief.md §P2.5.H; doc 22 §16.2 "statement_mode option"). Resolves the one
 * company option this sub-wave adds, via the SAME `settings` table / Setting::getByKey()
 * key-namespacing convention {@see VoucherOptions} already established — a plain per-company
 * key/value row, read with a config-level default (config('accounting.statements')) when no row
 * exists.
 *
 * Queue/webhook-safe by the same convention every other engine-layer class in this codebase
 * follows: $companyId is a plain argument, never resolved from Auth::user().
 */
final class StatementOptions
{
    public const MODE_KEY = 'accounting.statement_mode';

    public const MODE_OPEN_ITEMS = 'open_items';

    public const MODE_FULL_ACTIVITY = 'full_activity';

    /** The only two legal values for {@see MODE_KEY}. */
    public const MODES = [self::MODE_OPEN_ITEMS, self::MODE_FULL_ACTIVITY];

    /**
     * Falls back to config('accounting.statements.mode_default') when no Setting row exists, and
     * to 'open_items' (the brief's own default) if a stored value somehow drifted outside the
     * legal set — never lets an invalid stored string reach a caller silently.
     */
    public static function mode(int $companyId): string
    {
        $value = $companyId > 0 ? Setting::getByKey($companyId, self::MODE_KEY, null) : null;

        $resolved = $value ?? config('accounting.statements.mode_default', self::MODE_OPEN_ITEMS);

        return in_array($resolved, self::MODES, true) ? $resolved : self::MODE_OPEN_ITEMS;
    }

    /** @return int[] Ageing bucket upper bounds in days, e.g. [30, 60, 90, 120]. */
    public static function ageingBuckets(): array
    {
        return config('accounting.statements.ageing_buckets', [30, 60, 90, 120]);
    }

    public static function unsettledTolerance(): float
    {
        return (float) config('accounting.statements.unsettled_tolerance', 0.001);
    }
}
