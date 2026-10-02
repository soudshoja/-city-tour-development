<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * XBRL-X9r (PLAN.md L15 route (ii), L25 rule 3): thrown by {@see \App\Services\Accounting\PostingService::post()}
 * when a `PPA` (prior-period adjustment) document breaks one of its shape rules:
 *   - a line targets an Income or Expenses leaf (a PPA restates OPENING balances: balance-sheet
 *     leaves and opening retained earnings only, never current-year profit);
 *   - its document date is not 1 January (the first day of the current year, which is what makes
 *     it part of the opening position);
 *   - it would not land on its own date (the period is locked or soft-closed with no valid
 *     override, so the engine would otherwise shift it into a later month), or it asks for the
 *     locked-period bypass that is reserved for the year-end close job.
 *
 * Nothing is written when this is thrown: the checks run before the document number is reserved.
 */
final class InvalidPriorPeriodAdjustmentException extends PostingException {}
