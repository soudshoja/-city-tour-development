<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Base class for every exception the posting engine (PostingService, AccountResolver) throws.
 *
 * File 11 §P1.1: "THROWS PostingException (typed subclasses) on any violation -> the transaction
 * rolls back whole." Every subclass here is a business-rule violation raised deliberately by the
 * engine pipeline — never a framework/database-driver error — so callers can catch this one type
 * to mean "the document could not be posted" and inspect the concrete subclass (and its typed
 * public properties) for why.
 *
 * Deliberately NOT the base for AccountValidationException: that one is a COA tree-shape
 * violation raised at account-creation time by AccountService, not a document-posting-pipeline
 * violation — the two families are kept separate on purpose.
 */
abstract class PostingException extends \RuntimeException {}
