<?php

namespace App\Services\Accounting;

/**
 * Immutable description of one balanced document to post. Built by feeders; validated by
 * {@see PostingService}.
 *
 * Contract: 11-technical-implementation-plan.md ("file 11") §P1.1, L206-222, verbatim, WITH one
 * type-widening deviation documented below.
 *
 * DEVIATION (documented, minimal): `$sourceType` is typed `mixed` instead of the verbatim `?int`.
 * File 11's own inline comment on this field reads "// reference_type" — i.e. it is meant to carry
 * the value ultimately written to `transactions.reference_type`, which is a MySQL ENUM of the
 * STRING values `Receipt|Invoice|Payment|Refund` (verified in existingSchemaFacts). A property typed
 * strictly `?int` cannot legally hold one of those strings, so the verbatim type would make the
 * field's own documented purpose unusable by any real caller. `mixed` is chosen (rather than
 * inventing a narrower `?string` that would itself be a bigger textual deviation from "verbatim")
 * because it is the exact type already used one line below for the sibling field `$sourceId`
 * ("mixed $sourceId = null") in file 11's own snippet — this keeps the fix to a type-widening, not a
 * new design choice. See {@see PostingService::resolveReferenceType()} for how the value (when a
 * string matching the enum) is honoured, and the documented fallback when it is not supplied.
 *
 * ── P1 FIX ROUND additions (see ../../../.planning/P1-VERIFICATION-FINDINGS.json) ──────────────────
 * Two more additive, defaulted, trailing fields were appended after the adversarial verification
 * pass. Both are pure additions — every existing named-argument call site (tests, PostingService's
 * own reverse()) already omits every trailing optional parameter it doesn't set, so neither field
 * changes behaviour for a caller that doesn't pass it.
 *
 *   • `$invoiceId` (BLOCKER 3): the verification found that PostingService used to INFER
 *     `transactions.invoice_id` from `$sourceId` whenever the docType→reference_type fallback map
 *     produced 'Invoice' — which it did for JV/OJV/REV, none of which are actually invoices. A
 *     journal voucher carrying a task id or payment id in `$sourceId` was therefore written straight
 *     into a column with a real FK to `invoices`, producing either a hard rollback or a silent,
 *     possibly cross-tenant, wrong invoice link. `invoice_id` is now populated ONLY from this
 *     dedicated field — never inferred from `$docType`, `$sourceType`, or `$sourceId` — so a caller
 *     must say explicitly "this document's source really is an invoice" by passing the invoice's id
 *     here. `$sourceId`/`$sourceType` remain exactly what they were: an informational audit trail of
 *     "what fed this document", not a linkage instruction.
 *   • `$allowLockedPeriods` (MEDIUM finding): PeriodGuard::assertOpen() already accepts a forward-
 *     compatible `$allowLocked` parameter (see PeriodGuard's own docblock) for the
 *     13-party-ledger-reattribution-plan.md's "--allow-locked-periods" exemption, but nothing on
 *     DocumentDraft could carry that request down to PostingService::post()'s call site — so once
 *     P5.1 makes PeriodGuard a real (non-stub) check, file 13 Stage D's back-dated reattribution
 *     documents would have no way to ask for the bypass without changing post()'s own signature. This
 *     field completes that seam now, while it's cheap, without touching PostingService's call site
 *     again later. Inert in P1 (PeriodGuard::assertOpen() is a true no-op regardless of this value)
 *     — see PostingService::post() step 5.
 */
final class DocumentDraft
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $branchId,
        public readonly string $docType,          // INV/RV/PV/JV/CRN/DBN/OJV/REV
        public readonly ?string $subType,
        public readonly \DateTimeInterface $docDate,   // this IS transaction_date — the ONE period column (BUG-C4)
        public readonly string $narration,
        /** @var LineDraft[] */ public readonly array $lines,
        public readonly ?string $idempotencyKey = null, // gateway txn id, payment id, task id… (F2)
        public readonly mixed $sourceType = null,        // reference_type — see class docblock deviation note
        public readonly mixed $sourceId = null,         // invoice_id / payment_id / task_id — audit trail ONLY
        public readonly ?int $invoiceId = null,         // BLOCKER 3 fix — see class docblock. The ONLY source
        // of transactions.invoice_id; set this explicitly when (and only when) this document's source
        // really is an Invoice. Never inferred from docType/sourceType/sourceId.
        public readonly ?int $userId = null,
        public readonly ?int $costCenterId = null,
        public readonly bool $allowLockedPeriods = false, // MEDIUM finding fix — see class docblock.
    ) {}
}
