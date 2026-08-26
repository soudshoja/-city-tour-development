<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by AccountService (and its `creating` observer backstop, App\Observers\AccountObserver)
 * when an account-creation request violates one of the blueprint's nine COA rules: missing parent
 * (outside the fixed roots), depth > 6, cross-tenant parent, a duplicate root name, or an unknown
 * party type passed to ensurePartyLeaf().
 *
 * Deliberately NOT a PostingException subclass: this is a COA tree-shape violation at
 * account-creation time, not a document-posting-pipeline violation — the two families are kept
 * separate on purpose so callers never need to catch both as one type.
 */
class AccountValidationException extends \InvalidArgumentException {}
