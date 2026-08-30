<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * W3d (sale-shape audit / w3d-brief.md). Input to {@see SaleDraftBuilder::buildLines()} — the one
 * shared description of "a client-facing task/service is sold on an invoice" that both
 * `InvoiceController::postSaleJournalEntries()` (the main GDS/task auto-invoicing path) and
 * `ChatController::postChatInvoiceTaskEntries()` (the AI-chat "create invoice" path) now build
 * from, instead of each hand-rolling its own LineDraft array. Deliberately a plain value object
 * (not the Eloquent Task/Invoice/Client/Supplier/Agent rows themselves) for the same reason
 * {@see CreditApplicationInput} is: the builder is a pure function over its inputs and must not
 * depend on, or accidentally trigger, any Eloquent relation/global-scope side effect.
 *
 * ── Two postures, one shape family (blueprint 04 §4, 03 §3/§10) ──────────────────────────────────
 * `$postingBasis` selects which of the two blueprint-consistent shapes {@see SaleDraftBuilder}
 * builds — see that class's own docblock for the exact lines each produces:
 *   - `self::BASIS_AGENT` ("net"): the agency arranges the service but is not the obligor (IFRS 15
 *     agent indicators — no inventory risk, no price-setting discretion). Blueprint's `Dr customer
 *     receivable / Cr supplier payable / Cr income (sell − payable)` pattern (03 §3), one document.
 *   - `self::BASIS_PRINCIPAL` ("gross"): the agency assembles/controls the service before handing
 *     it to the client (IFRS 15 principal indicators — primarily responsible for fulfillment,
 *     bears inventory/pricing risk). Full sell posts as revenue; cost posts as a separate
 *     cost-of-sales pair — this is where `SERVICE_COST` (dead since W1, per the sale-shape audit)
 *     gets its first real call site.
 * See {@see SaleDraftBuilder::resolvePostingBasis()} for how a caller determines which one to pass
 * — the per-company, per-service-type `posting_basis` option (w3d-brief.md decision 2), never
 * hand-picked by a feeder itself.
 *
 * ── Currency (unchanged from both legacy callers) ─────────────────────────────────────────────────
 * Base-currency only, `exchangeRate` 1.0 by default — matching BOTH existing ON-path
 * implementations' own documented convention (`InvoiceController::postSaleJournalEntries()`'s own
 * docblock: "$task->currency is a legacy per-row label HEAD writes verbatim but never uses to
 * convert debit/credit"; `ChatController::postChatInvoiceTaskEntries()`'s own docblock: "Currency:
 * base-currency only"). This builder does not invent multi-currency support neither caller had;
 * see this lane's own report for this as a documented, unmigrated gap, not a silent regression.
 *
 * ── P2.5.D addition — revenue recognition timing (p2_5-brief.md §P2.5.D; doc 22 §15.6) ───────────
 * `$recognitionTiming` — {@see self::RECOGNITION_AT_ISSUE} | {@see self::RECOGNITION_AT_TRAVEL} |
 * `null`. Additive, defaulted-null trailing field: a caller that constructs `SaleDraftInput`
 * directly with no company context (a unit test, most notably) may leave this `null` and see
 * {@see SaleDraftBuilder::buildLines()} fall back to `config('accounting.revenue_recognition.
 * default_by_service_type')`, keyed by `$serviceType` — mirroring `$postingBasis`'s own
 * default-by-type convention. All six real construction sites (InvoiceController/ChatController)
 * instead resolve {@see SaleDraftBuilder::resolveRecognitionTiming()} (which also honours a
 * per-company `Setting` override) and pass the result explicitly here, the same way `$postingBasis`
 * itself is already resolved and passed at each site.
 */
final class SaleDraftInput
{
    public const BASIS_AGENT = 'agent';

    public const BASIS_PRINCIPAL = 'principal';

    // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6) — see class docblock's own addition note.
    public const RECOGNITION_AT_ISSUE = 'at_issue';

    public const RECOGNITION_AT_TRAVEL = 'at_travel';

    public function __construct(
        /** One of config('accounting.purpose_codes.service_types') — carried onto every
         *  per-service LineDraft this input produces (SERVICE_PAYABLE/SERVICE_REVENUE/
         *  SERVICE_COST). Never null: a sale with no service type cannot resolve a per-service
         *  purpose code at all. */
        public readonly string $serviceType,
        /** What the client pays — base currency, already resolved by the caller. Must be > 0
         *  (PostingService's own non-negative-amount rule; unchanged from both legacy callers). */
        public readonly float $sellAmount,
        /** What the agency owes the supplier — base currency. May legitimately be 0.0 (a service
         *  with no supplier cost, e.g. a pure fee) or exceed $sellAmount (a below-cost sale) —
         *  both bases handle a zero/negative margin without rejecting the whole document; see
         *  {@see SaleDraftBuilder}'s own docblock. */
        public readonly float $costAmount,
        /** {@see self::BASIS_AGENT} | {@see self::BASIS_PRINCIPAL} — see class docblock. */
        public readonly string $postingBasis,
        public readonly ?int $clientId = null,
        public readonly ?string $clientName = null,
        public readonly ?int $supplierId = null,
        public readonly ?string $supplierName = null,
        public readonly ?int $agentId = null,
        public readonly ?string $agentName = null,
        public readonly ?int $invoiceId = null,
        public readonly ?int $invoiceDetailId = null,
        public readonly ?int $taskId = null,
        /** Defaults to config('accounting.engine.base_currency') inside the builder when null. */
        public readonly ?string $currency = null,
        public readonly float $exchangeRate = 1.0,
        public readonly ?string $receivableDescription = null,
        public readonly ?string $payableDescription = null,
        /** Principal basis only: description of the full-sell SERVICE_REVENUE credit. Agent basis
         *  ignores this (its SERVICE_REVENUE leg is the margin — see
         *  $marginPositiveDescription/$marginNegativeDescription instead). */
        public readonly ?string $revenueDescription = null,
        /** Agent basis only: description of the sign-aware SERVICE_REVENUE margin leg when the
         *  margin is positive (sold above cost). */
        public readonly ?string $marginPositiveDescription = null,
        /** Agent basis only: description of the sign-aware SERVICE_REVENUE margin leg when the
         *  margin is negative (sold below cost — a contra-income debit, never rejected). */
        public readonly ?string $marginNegativeDescription = null,
        /** Principal basis only: description of the Dr SERVICE_COST cost-of-sales leg. */
        public readonly ?string $costDescription = null,
        /** {@see self::RECOGNITION_AT_ISSUE} | {@see self::RECOGNITION_AT_TRAVEL} | `null` — see
         *  class docblock's "P2.5.D addition" note. `null` (every existing call site) means
         *  "resolve the config default for $serviceType inside the builder". */
        public readonly ?string $recognitionTiming = null,
    ) {
        if (! in_array($postingBasis, [self::BASIS_AGENT, self::BASIS_PRINCIPAL], true)) {
            throw new \InvalidArgumentException(sprintf(
                "SaleDraftInput::\$postingBasis must be '%s' or '%s'; got %s.",
                self::BASIS_AGENT,
                self::BASIS_PRINCIPAL,
                var_export($postingBasis, true)
            ));
        }

        if ($recognitionTiming !== null && ! in_array($recognitionTiming, [self::RECOGNITION_AT_ISSUE, self::RECOGNITION_AT_TRAVEL], true)) {
            throw new \InvalidArgumentException(sprintf(
                "SaleDraftInput::\$recognitionTiming must be null, '%s' or '%s'; got %s.",
                self::RECOGNITION_AT_ISSUE,
                self::RECOGNITION_AT_TRAVEL,
                var_export($recognitionTiming, true)
            ));
        }
    }
}
