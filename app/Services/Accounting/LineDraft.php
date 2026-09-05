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
 *
 * ── W1.1 FIX ROUND (M3 / C5 — line attribution) ───────────────────────────────────────────────────
 * The verbatim contract gives this class no way to carry the party/document attribution legacy
 * `JournalEntry::create()` call sites always wrote (invoice_id, invoice_detail_id, task_id, a
 * LEGACY-vocabulary `type` label, a party display `name`, a per-line `voucher_number`) — W1 proved
 * that gap breaks `AccountingController::filterLedgers()`'s per-client/supplier/agent ledger filter
 * and its receipt-voucher screen (`whereIn('type', ['receivable','income'])` /
 * `['payable','expenses'])`) the moment a feeder is cut over. Six more additive, nullable,
 * defaulted-null trailing fields close that gap; every existing named-argument call site (which is
 * every call site in this codebase — nothing constructs a LineDraft positionally) already omits
 * every trailing optional parameter it doesn't set, so none of this changes behaviour for a caller
 * that doesn't pass it. `PostingService::post()` writes each field verbatim to its like-named
 * `journal_entries` column when non-null; see that class's step 8 for the exact fallback used when
 * a field IS null (never a crash, never a NOT NULL violation — every one of these columns is
 * nullable, verified against the journal_entries migration chain).
 *
 *   • `$invoiceId` / `$invoiceDetailId` / `$taskId` — plain per-line FKs. Legacy writes these to
 *     `journal_entries.invoice_id` / `.invoice_detail_id` / `.task_id` independently of whatever
 *     `DocumentDraft::$invoiceId` (a HEADER-level, `transactions.invoice_id`-only field — see that
 *     class's own docblock, BLOCKER 3) is set to; a document's lines are not required to share one
 *     invoice/task (a JV can touch several). No inference is attempted between the two — a feeder
 *     that wants both columns populated with the same value passes it to both places explicitly.
 *   • `$ledgerType` — the LEGACY report-vocabulary category ('receivable' / 'payable' / 'income' /
 *     'expense' / …) that `AccountingController` and `BankPaymentController` filter
 *     `journal_entries.type` on. This is DELIBERATELY a separate field from `$transactionType`
 *     (this class's pre-existing "audit label", e.g. CUSTOMERDEBITED/SUPPLIERCREDITED/INCOME) —
 *     the two vocabularies serve different readers (a human-facing legacy report screen vs. an
 *     engine-internal audit trail) and conflating them was the exact defect this round fixes: W1
 *     shipped `'type' => $line->transactionType` verbatim, which silently made every engine-posted
 *     line invisible to both legacy screens the moment a feeder went ON. When `$ledgerType` is
 *     null, `PostingService` falls back to `$transactionType` (W1's existing behaviour), so a
 *     feeder that genuinely has no legacy category to report (this class is also used for brand
 *     new, purely-engine-native documents with no legacy screen counterpart) is unaffected.
 *   • `$partyName` — the party's own display name (e.g. `$client->full_name`) for
 *     `journal_entries.name`. Legacy writes the PARTY's name here, not the account's; W1's
 *     resolved-gap #5 default (`$account->name`) is a reasonable fallback for a genuinely
 *     account-only line but silently wrong for a party-attributed one (a client's receivable line
 *     showing the pooled control account's name, e.g. "Clients", instead of the client's own name —
 *     the exact M3 finding). Falls back to the resolved account's name when null, i.e. W1's
 *     existing behaviour is preserved for any line that doesn't set this.
 *   • `$voucherNumber` — legacy `journal_entries.voucher_number` is frequently a business-facing
 *     voucher number a feeder already has (e.g. `$payment->voucher_number`), independent of
 *     `SequenceService::next()`'s own engine-generated document number (`$formattedNumber`, which
 *     `PostingService` writes here today for every line regardless of what a feeder might already
 *     know). Falls back to `$formattedNumber` when null, so a feeder with no legacy voucher number
 *     of its own keeps W1's existing behaviour unchanged.
 *
 * `$partyAccountRef` (pre-existing, see below) is likewise now WRITTEN, to
 * `journal_entries.type_reference_id` — verified against `AccountingController::filterLedgers()`'s
 * own `case 'client'/'supplier'/'agent': $ledgersQuery->where('type_reference_id', $relatedId)`,
 * i.e. `type_reference_id` is always a PARTY id (client_id/supplier_id/agent_id), never an account
 * id — confirming `$partyAccountRef`'s own name and the three feeders' existing
 * `partyAccountRef: $client->id` / `$supplier->id` / `$agent->id` call sites were already correct;
 * only the engine-side write was missing.
 *
 * ── W5.L FIX ROUND (voucher/instrument prerequisites — w5-brief.md §W5.L) ───────────────────────
 * Five more additive, nullable, defaulted-null trailing fields, for the RV/PV instrument capture
 * `ReceiptVoucherController`/`BankPaymentController` already write today (state doc row "Instrument
 * capture": `journal_entries.cheque_no`/`.cheque_date`/`.bank_info`/`.auth_no` exist and ARE
 * written by both controllers directly — but the engine DTO had no field to carry any of them, so
 * a PostingService-routed voucher could never persist them). `PostingService::post()` writes each
 * verbatim to its like-named `journal_entries` column at step 8 when non-null; every one of the
 * five target columns is nullable, so a feeder that never sets one of these fields changes nothing
 * for that column versus today's behaviour of never referencing it from the engine at all — see
 * PostingService's own docblock for the one documented BEHAVIOUR CHANGE this closes (cheque_date
 * carries a DB-level `useCurrent()` default that used to silently fire on every engine-posted line
 * because the column was never in the INSERT list; it now receives a real, explicit NULL instead
 * when a feeder has no cheque date to report — a bug fix, not a regression, since a mundane JV/INV
 * line stamped with "now" as a fabricated cheque date has no accounting meaning).
 *
 *   • `$chequeNo` -> `journal_entries.cheque_no` (varchar(100)).
 *   • `$chequeDate` -> `journal_entries.cheque_date` (timestamp, nullable, DB `useCurrent()`
 *     default — see the behaviour-change note above). `\DateTimeInterface`, not `?string`: matches
 *     the convention `DocumentDraft::$docDate` already uses, and Illuminate\Database\Connection::
 *     prepareBindings() already formats a bound `DateTimeInterface` for the driver — the same
 *     mechanism that already lets step 7's header write hand `transaction_date` a `Carbon`
 *     instance directly, with no new casting needed here.
 *   • `$bankInfo` -> `journal_entries.bank_info` (varchar(200)).
 *   • `$authNo` -> `journal_entries.auth_no` (varchar(100)).
 *   • `$chequeClearanceDate` -> `journal_entries.cheque_clearance_date` (NEW column, this build's
 *     one migration — `date`, nullable, no DB default). W5.R's manual "Clear" action on a posted
 *     cheque-based RV/PV sets this via a repost carrying the same cheque identity plus this date;
 *     W5.L itself only carries the field down to the column, it does not build that clearance flow.
 *
 * ── W5.P FIX ROUND (BankPaymentController through the seam, w5-brief.md §W5.P) ───────────────────
 * One more additive, nullable, defaulted-null trailing field.
 *
 *   • `$reconciled` -> `journal_entries.reconciled` (tinyint: 0 = not yet reconciled, 1 = reconciled
 *     against another line, 2 = "already reconciled" fast-path record). `PostingService::post()`
 *     step 8 used to hardcode `'reconciled' => 0` unconditionally — no LineDraft field could ever
 *     make an engine-posted line carry anything else. `BankPaymentController`'s pre-existing
 *     `bankpaymenttype === 'PaymentByDate'` fast path (w5-state.md §1 "PV kinds (today)": "sets
 *     `reconciled = 2` directly at creation") needs its OWN new line to carry that same value once
 *     routed through the seam — w5-brief.md §W5.P: "kept, but `reconciled=2` set via an engine line
 *     flag, not a raw column write". Falls back to `0` when null (every existing feeder that never
 *     sets this field — RV, refunds, sales — sees no change from before this field existed).
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
        public readonly ?int $partyAccountRef = null, // party id (client/supplier/agent) -> journal_entries.type_reference_id
        public readonly ?string $description = null,
        public readonly ?string $serviceType = null, // DEVIATION (additive): task type dimension for
        // per-service purpose codes (flight/hotel/visa/…); null for global-control purpose codes.
        // See class docblock. Ignored entirely when $accountId is set (explicit-account lines never
        // go through AccountResolver).
        public readonly ?int $invoiceId = null,        // W1.1 fix (M3/C5) -> journal_entries.invoice_id
        public readonly ?int $invoiceDetailId = null,  // W1.1 fix (M3/C5) -> journal_entries.invoice_detail_id
        public readonly ?int $taskId = null,           // W1.1 fix (M3/C5) -> journal_entries.task_id
        public readonly ?string $ledgerType = null,    // W1.1 fix (M3/C5) -> journal_entries.type; falls
        // back to $transactionType when null. See class docblock — deliberately a SEPARATE
        // vocabulary from $transactionType.
        public readonly ?string $partyName = null,     // W1.1 fix (M3/C5) -> journal_entries.name; falls
        // back to the resolved account's name when null.
        public readonly ?string $voucherNumber = null, // W1.1 fix (M3/C5) -> journal_entries.voucher_number
        // (per line); falls back to the document's own formatted number when null.
        public readonly ?string $chequeNo = null,           // W5.L fix -> journal_entries.cheque_no
        public readonly ?\DateTimeInterface $chequeDate = null, // W5.L fix -> journal_entries.cheque_date
        public readonly ?string $bankInfo = null,           // W5.L fix -> journal_entries.bank_info
        public readonly ?string $authNo = null,             // W5.L fix -> journal_entries.auth_no
        public readonly ?\DateTimeInterface $chequeClearanceDate = null, // W5.L fix (NEW column) ->
        // journal_entries.cheque_clearance_date
        public readonly ?int $reconciled = null, // W5.P fix -> journal_entries.reconciled; falls
        // back to 0 when null. See class docblock's "W5.P FIX ROUND" note.
        public readonly ?string $settlementChannel = null, // accounting-builds T0b (M1, L12) ->
        // journal_entries.settlement_channel (varchar(24), e.g. 'tap:knet', 'bank:transfer').
        // Null by default (unchanged behaviour for every feeder that doesn't set it).
        // PostingService::post() writes it verbatim at step 8; reverse()'s reconstruction carries
        // it over from the original line (see PostingService's own MP-0b-1 note on that copy).
    ) {}
}
