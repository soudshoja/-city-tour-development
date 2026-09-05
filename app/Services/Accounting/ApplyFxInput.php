<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * accounting-builds T1 (Lane A — realised FX on apply). One (source line, applied line) pair for
 * {@see RealisedFxService::compute()} / {@see RealisedFxService::postForApply()} — the "this much
 * of THIS invoice-currency amount moved from the SOURCE posting's own rate to the APPLIED
 * (invoice) posting's own rate" fact a single {@see \App\Services\Accounting\CreditApplicationInput}
 * (one `payment_applications` row) produces.
 *
 * Both `$sourceLineId` and `$appliedLineId` are `journal_entries.id` values of ALREADY-POSTED
 * lines — never freshly built. Per L4 (accounting-builds PLAN.md §2), the realised-FX amount and
 * sides are computed from POSTED journal lines only, never a fresh rate lookup: this class exists
 * so {@see RealisedFxService::compute()} never has to re-derive a rate, only read the two rows
 * these ids name.
 *
 *   - `$sourceLineId` — the SOURCE (applied-FROM) line: the payment/credit's own original posted
 *     `CLIENT_ADVANCE`/`RECEIVABLE_CONTROL` line (the leg that recorded the money when it first
 *     entered the ledger, e.g. a TOPUP's `Cr CLIENT_ADVANCE` at receipt time) — NOT the debit line
 *     the current credit-apply JV itself just posted (that JV's own two lines always share ONE
 *     rate, by construction, so comparing them against each other can never produce a real
 *     difference — see {@see RealisedFxService}'s own class docblock).
 *   - `$appliedLineId` — the invoice's own posted `INV`-document `RECEIVABLE_CONTROL` line (the
 *     TARGET the apply reduces), found via `journal_entries.invoice_id` + the resolved
 *     `RECEIVABLE_CONTROL` account, filtered to the invoice's own `INV` document — never the
 *     credit-apply JV's own credit line either.
 *   - `$appliedFcAmount` — the invoice-currency (FC) amount this ONE application moved (`a` in the
 *     plan's `D = round(a·r_s − a·r_t, 3)` formula) — {@see CreditApplicationInput::$amountApplied}
 *     rounded to `base_decimals`, the SAME figure {@see CreditApplicationDraftBuilder} used to
 *     build this application's own debit line.
 *   - `$idSource`/`$id` — the apply event's own identity, mirroring
 *     {@see CreditApplicationInput::$idSource}/`$id` — used to build this document's idempotency
 *     key (`"fx-apply:{idSource}:{id}"`, one FXR document per `payment_applications` row).
 */
final class ApplyFxInput
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $branchId,
        public readonly int $sourceLineId,
        public readonly int $appliedLineId,
        public readonly float $appliedFcAmount,
        public readonly string $idSource,
        public readonly int $id,
        public readonly \DateTimeInterface $docDate,
        public readonly ?int $invoiceId = null,
        public readonly ?int $userId = null,
    ) {
        if ($id <= 0) {
            throw new \InvalidArgumentException(
                'ApplyFxInput::$id must be a real, positive id; got '.$id.'.'
            );
        }
    }
}
