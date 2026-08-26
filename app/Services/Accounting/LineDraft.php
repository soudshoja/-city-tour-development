<?php

namespace App\Services\Accounting;

/**
 * One one-sided line of a {@see DocumentDraft}. Feeders name accounts by PURPOSE, never by string.
 *
 * Contract: 11-technical-implementation-plan.md ("file 11") §P1.1, L224-239, verbatim, WITH one
 * additive deviation documented below.
 *
 * DEVIATION (additive, documented — not in file 11's verbatim snippet):
 * `$serviceType` is appended as a new, nullable, defaulted-null trailing constructor parameter.
 * File 11's own pipeline text (PostingService step 2) requires per-line purpose resolution via
 * `AccountResolver::resolve(purpose, companyId, serviceType)` — a THREE-argument call — and file 11's
 * own Open Questions section states the per-service purpose codes (SERVICE_REVENUE / SERVICE_PAYABLE /
 * SERVICE_COST) are meaningless without a service_type carried per line (a single invoice can mix
 * services, e.g. a flight + hotel combo, so service_type cannot live on the DocumentDraft header).
 * Yet the verbatim LineDraft snippet (L224-239) has no field to carry it. Without this field
 * PostingService could never resolve a per-service purpose code at all, so this is a necessary,
 * strictly-additive completion of the given contract rather than a design choice: the field is
 * appended LAST with a `null` default so every positionally- or named-constructed LineDraft the
 * contract's own text implies (`purposeCode, accountId, side, amount, currency, originalAmount,
 * exchangeRate, transactionType, partyAccountRef, description`) still compiles unchanged.
 */
final class LineDraft
{
    public function __construct(
        public readonly string $purposeCode,      // resolved via AccountResolver; OR explicit accountId for user-picked lines
        public readonly ?int $accountId,          // set when the user picked a specific leaf (manual JV)
        public readonly string $side,             // 'debit' | 'credit' — exactly one side, non-negative amount
        public readonly float $amount,            // base-currency amount
        public readonly string $currency,         // original currency
        public readonly float $originalAmount,    // FC amount; == amount when currency == base
        public readonly float $exchangeRate,      // > 0; 1.0 for base
        public readonly ?string $transactionType, // audit label: CUSTOMERDEBITED, SUPPLIERCREDITED, INCOME, CCCHARGES…
        public readonly ?int $partyAccountRef = null,
        public readonly ?string $description = null,
        public readonly ?string $serviceType = null, // DEVIATION (additive): task type dimension for
        // per-service purpose codes (flight/hotel/visa/…); null for global-control purpose codes.
        // See class docblock. Ignored entirely when $accountId is set (explicit-account lines never
        // go through AccountResolver).
    ) {}
}
