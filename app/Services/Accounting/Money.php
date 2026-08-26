<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * Minimal money-rounding helper. PostingService's constructor (file 11 §P1.1, L253-259, verbatim
 * snippet: `private Money $money,`) names this class as a collaborator, but no file 11 section
 * anywhere defines its shape — it is never mentioned again outside that one constructor line, and
 * no other builder in this parallel build produced it. Reflection against the actual
 * PostingService.php that landed shows exactly one call shape used: `$this->money->round($amount,
 * $decimals)` (step 3a, applied to both `amount` and `originalAmount` before every other per-line
 * rule evaluates them). This class supplies exactly that method — an integration-time addition,
 * not a design choice — so `app(PostingService::class)` can resolve at all.
 *
 * Kept deliberately tiny: a single pure function with no state, no config reads of its own
 * (PostingService already sources decimals from config('accounting.engine.base_decimals') and
 * passes them in), so it is trivial to extend later (e.g. currency-aware formatting for P5+
 * reporting) without touching the one call site that exists today.
 */
final class Money
{
    /**
     * Round a monetary amount to $decimals places using standard half-away-from-zero rounding —
     * the same rounding mode PHP's own round() and every existing money column in this schema
     * (decimal columns via MySQL) use, so a value rounded here matches what gets persisted.
     */
    public function round(float $amount, int $decimals): float
    {
        return round($amount, $decimals);
    }
}
