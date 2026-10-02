<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * XBRL X9 (PLAN.md L13, X9; H-M3): the year-end reserve appropriation refused. The message says
 * why and what to do; it is always thrown inside the appropriation's own transaction, so nothing
 * is left half-written.
 *
 * Deliberately NOT a {@see PostingException}: a refusal here is a precondition of the procedure
 * (a locked December, a soft-closed December with no reason, a journal that would not land on
 * 31 December), not a document the engine refused. A posting refusal raised underneath propagates
 * as itself.
 */
final class ReserveAppropriationException extends \RuntimeException {}
