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
 *
 * ── W1.1 FIX ROUND (M3/C5 line attribution task, header-level check) ────────────────────────────
 * One more additive, defaulted, trailing field. Checked against the full `transactions` migration
 * chain: the table has NO `voucher_number` column at all (that column exists only on
 * `journal_entries` — see LineDraft::$voucherNumber — and on `payments`), so there is nothing to
 * add for that half of the check. It DOES have `payment_reference` (nullable, unused by
 * PostingService today), which the myfatoorah legacy closure writes
 * (`Transaction::create(['payment_reference' => $invoiceRef, ...])`, the gateway's own invoice
 * reference) and the engine header write silently drops. `$paymentReference` closes that one real
 * header-level gap.
 *   • `$paymentReference` -> `transactions.payment_reference`. Null by default (W1's existing
 *     header write already omits this column entirely, i.e. leaves it NULL), so a caller that
 *     doesn't set it sees no change.
 *
 * ── W1.2 FIX ROUND (Task A — engine header attribution: transactions.payment_id) ────────────────
 * One more additive, defaulted, trailing field. `transactions.payment_id` (unsignedBigInteger,
 * nullable, FK to `payments` `onDelete('set null')` — migration
 * 2025_06_24_122434_update_transactions_table_for_payment_tracking.php) is written by legacy's
 * `CheckMyFatoorahPayments` closure (`Transaction::create(['payment_id' => $payment->id, ...])`)
 * but was never part of PostingService::post()'s own header write (W1.1 lead report §3 myfatoorah,
 * G19 "unexpected_deltas": OFF path `payment_id = 1`, ON path `payment_id = NULL`) — a
 * PRE-EXISTING engine-contract gap, not a W1.1 regression. `$paymentId` closes it the same way
 * `$paymentReference` did: null by default (a caller that doesn't set it sees `transactions.
 * payment_id` stay NULL, exactly today's behaviour), populated only when a feeder explicitly
 * says "this document really is attached to this payment" (MyFatoorah's engine draft now does).
 * NOTE: `transactions` also carries a real unique index `(payment_id, reference_type)` — see
 * migration 2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php — which
 * exempts NULL `payment_id` rows (MySQL does not enforce uniqueness among NULLs) but WILL reject
 * a second document for the same (payment_id, reference_type) pair. A feeder that sets this field
 * must ensure at most one document per payment carries a given `$sourceType`/reference_type
 * combination — MyFatoorah's own idempotency-key dedup already guarantees this for its own
 * 'Receipt' rows; see PostingService::post()'s header-write step for the reference_type this
 * field is written alongside.
 *
 * ── P2.5.A addition (period-lock-design.md §3; p2_5-brief.md §P2.5.A) ────────────────────────────
 * One more additive, defaulted, trailing field. `$overrideReason` — the mandatory reason a caller
 * supplies when deliberately posting into a `soft_closed` accounting period (design doc §3: "each
 * such post requiring a mandatory reason, audit-logged"). Consulted only by
 * {@see PeriodGuard::assertOpen()} (see PostingService::post()'s step-5 call site, which now
 * passes it alongside `$allowLockedPeriods`) — never written to any ledger column. Null by
 * default, so a caller that never sets it (every feeder that predates this wave) sees no change: a
 * soft_closed period simply refuses the post, exactly as a locked one would without
 * `$allowLockedPeriods`.
 *
 * ── P2.5.B addition (period-lock-design.md §8.1; p2_5-brief.md §P2.5.B — the three-date model) ──
 * One more additive, defaulted, trailing field. `$postingDate` — "defaulting to docDate" per the
 * brief's own words: this property itself is `null` unless a caller deliberately sets it, and
 * every consumer (today, only {@see PostingService::post()}'s step 5) reads it as
 * `$draft->postingDate ?? $draft->docDate`, so an unset value behaves exactly like "the same as
 * $docDate" without this class needing to reference `$docDate` from its own parameter defaults
 * (which PHP does not allow for promoted constructor properties). This is the date PostingService
 * actually resolves a period against and persists to the new `journal_entries.posting_date` /
 * `transactions.posting_date` columns — `$docDate` (== `transaction_date`) is never altered by
 * that resolution, no matter what it produces. See PostingService::post()'s own step-5 docblock
 * for the full shift sequence (request → assertOpen → on PeriodLockedException, shift via
 * PeriodGuard::earliestOpenOnOrAfter() → re-assertOpen → log `accounting.posting_date_shifted` if
 * a shift actually happened). A caller with no reason to think about periods (every feeder that
 * predates this wave, and most that come after it) never sets this field and sees the engine
 * silently pick the right period on its own. Setting this field explicitly changes WHICH date gets
 * resolved (in place of `$docDate`) — it does NOT itself skip the shift: an explicit postingDate
 * landing on a locked/soft_closed period with no valid override shifts exactly like a defaulted
 * one would. What actually keeps a document unshifted on a non-open date is pairing an explicit
 * postingDate with a VALID override (`$overrideReason` + permission, for a soft_closed period) —
 * `PeriodGuard::assertOpen()` then never throws in the first place, so step 5 never reaches the
 * shift branch at all. That is the real shape a future close-screen "post this correction dated
 * exactly into this soft_closed period" action would use.
 * `withRepostIdempotencyKey()`/`withoutPaymentId()` (PostingService's two field-for-field
 * DocumentDraft reconstructions) both carry this field over like every other one.
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
        public readonly ?string $paymentReference = null, // W1.1 fix -> transactions.payment_reference;
        // see class docblock's W1.1 FIX ROUND note.
        public readonly ?int $paymentId = null, // W1.2 fix (Task A) -> transactions.payment_id;
        // see class docblock's W1.2 FIX ROUND note. Never inferred from sourceId — a caller must
        // say explicitly "this document is attached to this payment", same convention as $invoiceId.
        public readonly ?string $overrideReason = null, // P2.5.A addition — see class docblock.
        // Consulted by PeriodGuard::assertOpen() for a soft_closed period only; never persisted.
        public readonly ?\DateTimeInterface $postingDate = null, // P2.5.B addition — see class
        // docblock. Null means "resolve from $docDate"; never read directly by a caller other than
        // PostingService::post(), which applies the `?? $docDate` fallback itself.
    ) {}
}
