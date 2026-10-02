<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * XBRL-X9r (X9R-VERIFY m-2, m-5): {@see \App\Services\Accounting\PostingService::reverse()}
 * refused to reverse a closing-family document outside its own rules:
 *   - a YEC is reversed only inside its own fiscal year (the reopen procedure dates it 31 Dec);
 *   - a PPA is reversed only on its own date (1 January), so it can never leave the opening
 *     position restated while its reversal sits in current-year movement;
 *   - the reversal of a YEC or a PPA is never itself reversed (post a new YEC by re-closing, or a
 *     new PPA, instead).
 * Nothing is written when this is thrown.
 */
final class InvalidClosingReversalException extends PostingException {}
