<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * XBRL-X9r (PLAN.md L25 rule 2): the reopen-a-closed-year procedure
 * ({@see \App\Services\Accounting\ClosedYearAdjustmentService}) refused a step. The message says
 * which step and why; when it is thrown inside the procedure's own transaction nothing is left
 * half-done.
 *
 * Deliberately NOT a {@see PostingException}: a refusal here is a procedure precondition, not a
 * document the engine refused. A posting refusal raised inside the procedure (for example the
 * {@see ProtectedLineException} a reconciled YEC line produces) propagates as itself.
 */
final class ClosedYearProcedureException extends \RuntimeException {}
