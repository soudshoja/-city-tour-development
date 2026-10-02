<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * XBRL-X9r (X9R-VERIFY B-1): a year-end close was about to leave TWO live (unreversed) YEC
 * documents for one company and fiscal year. That double-sweeps the year's profit into Retained
 * Earnings (the verifier measured 12,000 instead of 6,000) while the balance sheet still foots, so
 * the close refuses and rolls back instead. Raised by
 * {@see \App\Services\Accounting\YearEndCloseService::run()}, from its own post-condition or from
 * the database's `transactions_company_live_yec_year_unique` index.
 */
final class DuplicateLiveYearEndCloseException extends PostingException {}
