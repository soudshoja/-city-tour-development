<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by Invoice::boot()'s `saving` guard (W3a PROFORMA LOCK, owner decision 2026-08-27) when
 * code attempts to change an amount-bearing column on an invoice whose `proforma_sent_at` was
 * already set on a PRIOR save — i.e. an invoice already shown to the client as a binding proforma
 * quote. A proforma's amounts are immutable once sent: any real amount change must go through a
 * reverse + re-send flow (a new document/amount), never a silent in-place overwrite of this one.
 *
 * Deliberately NOT a {@see PostingException} subclass: this is an Invoice-model business rule, not
 * a posting-engine pipeline violation — PostingSeam/PostingService's own `catch (PostingException
 * $e)` blocks must never see, swallow, or reinterpret this exception as an engine-path failure.
 */
final class ProformaAmountLockedException extends \RuntimeException
{
    public function __construct(
        public readonly ?int $invoiceId,
        public readonly string $column,
        public readonly mixed $originalValue,
        public readonly mixed $attemptedValue,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Invoice #%s: cannot change `%s` from %s to %s — this invoice was already sent as a '.
            'proforma (amounts are locked). Use a reverse + re-send flow instead of an in-place edit.',
            $this->invoiceId !== null ? (string) $this->invoiceId : '?',
            $this->column,
            var_export($this->originalValue, true),
            var_export($this->attemptedValue, true)
        ));
    }
}
