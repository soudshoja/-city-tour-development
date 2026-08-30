<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * W6.C. The task-level context {@see SupplierChargeLineBuilder::buildLines()} needs to turn a
 * resolved {@see \App\Models\SupplierChargeRule} into real {@see LineDraft}s — a plain value
 * object, same convention as {@see SaleDraftInput}/{@see SupplierCostCorrectionInput}: a pure
 * function of its inputs, no Eloquent relation/global-scope side effects.
 */
final class SupplierChargeLineInput
{
    public function __construct(
        /** One of config('accounting.purpose_codes.service_types') — the TASK's own real service
         *  type, independent of how broadly a matched rule was scoped (a company-wide rule has
         *  service_type=NULL on the rule itself; the LINE is still tagged with this real type). */
        public readonly string $serviceType,
        /** {@see SaleDraftInput::BASIS_AGENT} | {@see SaleDraftInput::BASIS_PRINCIPAL} — the SAME
         *  posting basis the task's own sale document uses (never independently chosen). */
        public readonly string $postingBasis,
        public readonly int $companyId,
        /** Booking reference — the once_per_reference dedup key (see
         *  {@see SupplierChargeRuleResolver}) and the amount-basis context below. */
        public readonly string $reference,
        /** Base fare amount (percent_of_fare basis). */
        public readonly float $fareAmount = 0.0,
        /** Full sell/total amount (percent_of_total basis). */
        public readonly float $totalAmount = 0.0,
        public readonly int $passengerCount = 1,
        public readonly int $segmentCount = 1,
        public readonly ?int $supplierId = null,
        public readonly ?string $supplierName = null,
        public readonly ?int $clientId = null,
        public readonly ?string $clientName = null,
        public readonly ?int $invoiceId = null,
        public readonly ?int $invoiceDetailId = null,
        public readonly ?int $taskId = null,
        public readonly ?string $currency = null,
        public readonly float $exchangeRate = 1.0,
    ) {}
}
