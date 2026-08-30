<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * PROPOSED NAME (W2.1 build, residual 4 fix). Thrown by handleHesabeResponse() when
 * createInvoicePaymentCOA()'s LEGACY branch reports $coaResult['success'] === false — i.e. the
 * OFF-path (or ON-path business-rule) COA write failed on its own terms, not because of a
 * framework/database-driver error.
 *
 * Deliberately NOT a PostingException subclass: PostingException's own docblock is explicit that
 * its family is "never a framework/database-driver error" raised by the engine pipeline. This
 * class is the opposite half of that same catch site — the legacy $coaResult failure — and it
 * deliberately does NOT extend \RuntimeException (PDOException/QueryException do), so narrowing
 * the catch clause to this concrete type, instead of the \RuntimeException supertype it replaces,
 * lets a genuine QueryException/PDOException propagate uncaught (-> HTTP 500) rather than being
 * silently swallowed into a "Payment Failed" redirect with raw SQL in the session flash.
 */
final class LegacyInvoiceCoaFailureException extends \Exception {}
