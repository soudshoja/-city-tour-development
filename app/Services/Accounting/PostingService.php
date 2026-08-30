<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\CrossTenantAccountException;
use App\Exceptions\Accounting\DuplicatePaymentReferenceException;
use App\Exceptions\Accounting\FcConsistencyException;
use App\Exceptions\Accounting\FrozenAccountException;
use App\Exceptions\Accounting\InvalidCurrencyCodeException;
use App\Exceptions\Accounting\InvalidRepostSourceException;
use App\Exceptions\Accounting\NonCanonicalJournalLineException;
use App\Exceptions\Accounting\NonLeafAccountException;
use App\Exceptions\Accounting\NonNegativeAmountException;
use App\Exceptions\Accounting\OneSidedLineException;
use App\Exceptions\Accounting\PeriodLockedException;
use App\Exceptions\Accounting\PostingEngineDisabledException;
use App\Exceptions\Accounting\ProtectedLineException;
use App\Exceptions\Accounting\SerialCollisionExhaustedException;
use App\Exceptions\Accounting\SupersededIdempotencyKeyException;
use App\Exceptions\Accounting\UnbalancedDocumentException;
use App\Models\Account;
use App\Models\Company;
use App\Models\IdempotencyKeyRejection;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The keystone: exactly one code path may write `journal_entries`, and it is impossible for it to
 * write an unbalanced, non-leaf, negative, cross-tenant, or duplicate document.
 *
 * Contract: 11-technical-implementation-plan.md ("file 11") §P1.1, L253-308, verbatim pipeline.
 * This build is FLAG-OFF / WIRED-TO-NOTHING (mission scope rule 1): no feeder calls this class yet.
 *
 * Exceptions used here (App\Exceptions\Accounting\*, PostingException base) and the neighbouring
 * classes it depends on (AccountResolver, SequenceService, Money, config/accounting.php) were built
 * in parallel by other agents against this same contract; namespaces/constructors below were
 * verified against those files directly once they landed, not guessed.
 *
 * ── Signature deviations from file 11's literal text (both documented in the mission task brief for
 *    this file, not invented here) ────────────────────────────────────────────────────────────────
 *   • post(): file 11 gives `post(DocumentDraft $draft): PostedDocument`. This build's task brief
 *     explicitly specifies `post(DocumentDraft $draft, ?int $userId = null): PostedDocument`. Both
 *     are honoured: DocumentDraft already carries a `$userId` field, so `$userId ?? $draft->userId`
 *     is used — a caller may pass it explicitly (e.g. a console command with no natural draft-level
 *     actor) or rely on the draft, and omitting the parameter is fully backward-compatible with the
 *     contract's own single-argument form.
 *   • reverse()/repost(): kept EXACTLY as file 11 gives them, including reverse()'s non-optional
 *     (but nullable-typed) `?int $userId` and repost()'s lack of a `$force` parameter — see reverse()
 *     and repost() docblocks for how repost() therefore defaults to the safe (unforced) reversal.
 *
 * ── Resolved contract gaps (schema-driven, not stylistic — see method docblocks for each) ─────────
 *   1. `transactions.entity_id`/`entity_type` are NOT NULL with no default (verified against the
 *      live migration chain and every existing `Transaction::create()` call site) but do not appear
 *      anywhere in DocumentDraft/LineDraft or the P1.1 pipeline text. Pinned to
 *      (companyId, 'company') — see post()'s header-write step.
 *   2. `transactions.transaction_type` is NOT NULL with no default and is not written by the
 *      pipeline text either. Set to `$draft->docType` — see post().
 *   3. `transactions.reference_type` is a closed 4-value legacy ENUM (`Receipt|Invoice|Payment|
 *      Refund`) that P1's migration set does not widen, yet DocumentDraft's 8-value `$docType`
 *      (INV/RV/PV/JV/CRN/DBN/OJV/REV) has no 1:1 mapping onto it. See resolveReferenceType() —
 *      this is flagged prominently in the build report as a genuine P1 gap worth a follow-up
 *      decision (widen the enum in a later migration, or accept the best-effort map below). NOTE
 *      (P1 fix round, BLOCKER 3): this map feeds `reference_type` ONLY. It must never also feed
 *      `transactions.invoice_id` — see DocumentDraft::$invoiceId and resolved-gap #3a below.
 *   3a. (P1 fix round, BLOCKER 3) `transactions.invoice_id` has a real FK to `invoices` and used to
 *      be inferred as "`$draft->sourceId` when the reference_type map resolves to 'Invoice'" — but
 *      that map sends JV/OJV/REV to 'Invoice' too, so a journal voucher's task/payment id in
 *      `sourceId` was written straight into `invoice_id`: either a hard rollback (id isn't an
 *      invoice) or a silent, possibly cross-tenant, wrong link. `invoice_id` is now populated ONLY
 *      from the dedicated, explicit `DocumentDraft::$invoiceId` field — never inferred. See that
 *      field's docblock.
 *   4. `SequenceService::next()`'s formatted number has no named destination column in file 11's
 *      pipeline text. Written to `transactions.reference_number` (existing column, evident purpose)
 *      and mirrored onto every line's `journal_entries.voucher_number` (matches the pre-existing
 *      legacy convention observed across the codebase) — this was file 11's own Open Question
 *      recommendation.
 *   5. `journal_entries.name` (NOT NULL) and `.description` (NOT NULL) have no DocumentDraft/
 *      LineDraft source named in file 11. `name` = `$line->partyName ?? $account->name` (W1.1 fix,
 *      M3 — see LineDraft::$partyName's own docblock; originally just the resolved account's name,
 *      unconditionally); `description` = `$line->description ?? $draft->narration` — both were
 *      file 11's own Open Question recommendations.
 *   6. `journal_entries.balance` (nullable) is left NULL on every write. Populating it correctly
 *      would require reading the account's current balance before writing the line — exactly the
 *      read-modify-write pattern step 9 forbids (F5/F11) — and file 11's pipeline text never
 *      instructs writing it. The column stays nullable at the schema level, so this is safe.
 *   7. `transactions.$fillable` (app/Models/Transaction.php, not in this build's file list and not
 *      touched here) predates the ten P1.1 columns (doc_type, sub_type, doc_year, posting_status,
 *      total_debit, total_credit, reversal_of_transaction_id, idempotency_key, created_by,
 *      posted_by, posted_at) — plain `Transaction::create()` would throw
 *      MassAssignmentException. `forceFill()->save()` is used instead (see createTransactionHeader())
 *      so this file never needs to modify the model.
 *   8. No per-company base-currency column exists anywhere in the schema (single legal entity per
 *      tenant today — file 11 C3). `config('accounting.engine.base_currency')` is used (falls back
 *      to 'KWD'), matching CLAUDE.md, config/accounting.php, and every money-precision statement in
 *      this build's mission brief.
 *   9. `journal_entries.cost_center_id` does not exist in P1 (P5.7, later) even though
 *      DocumentDraft carries a header-level `$costCenterId` for forward compatibility — it is
 *      deliberately NOT written here (file 11's own Open Question resolution).
 *  10. (P1 fix round) `accounts.actual_balance` is `decimal(10,2)` and was never widened to the
 *      engine's 3dp scale (the widening migration is explicitly out of scope for this round). The
 *      original build incremented it with 3dp deltas anyway, which MySQL silently truncates to 2dp
 *      on store — a permanent, compounding fils-level drift on the one balance the engine claimed
 *      to maintain atomically. See step 9 below: this build now maintains NO legacy balance column
 *      at all while the flag is off. `TrialBalanceService` (the audit's verified-correct source,
 *      which recomputes from `journal_entries` rather than trusting a maintained running total) is
 *      the authoritative balance today; `actual_balance` maintenance returns only after the
 *      money-column widening lands, at the column's real scale.
 *
 * ── P1 FIX ROUND (see .planning/P1-VERIFICATION-FINDINGS.json — adversarial verification, verdict
 *    FIX-FIRST) — summary of what changed in this file and why. Each item is documented in full at
 *    its call site; this is an index, not a duplicate of the reasoning. ───────────────────────────
 *   • BLOCKER 3 — invoice_id no longer inferred from docType/sourceId. See resolved-gap #3a,
 *     DocumentDraft::$invoiceId, and the header-write step.
 *   • BLOCKER 4 — actual_balance is no longer maintained by this engine at all. See resolved-gap
 *     #10 and step 9 (now a no-op with a docblock explaining why).
 *   • BLOCKER 5 — idempotency race: the header insert now catches the (company_id, idempotency_key)
 *     duplicate-key violation and returns the winner's PostedDocument instead of letting a raw
 *     QueryException escape; step 1's own lookup no longer filters on posting_status, aligning it
 *     with what the unique index actually rejects. See step 1 and the header-write step.
 *   • HIGH — the leaf test no longer trusts `accounts.is_group` (its unreliable TRUE default would
 *     reject nearly every real account until the deferred backfill runs); it is now driven solely by
 *     actual child-row existence. See step 3d.
 *   • HIGH — every `withoutGlobalScopes()` read of a soft-deleting model (Transaction, JournalEntry)
 *     now explicitly excludes `deleted_at IS NOT NULL` rows, since dropping ALL global scopes also
 *     drops `SoftDeletingScope`. See step 1, reverse(), and toPostedDocument().
 *   • HIGH — reverse() now refuses (NonCanonicalJournalLineException) an original line whose
 *     debit/credit shape isn't canonical (exactly one strictly positive, neither negative) instead of
 *     silently mis-reversing legacy sign-error/both-legs-set rows.
 *   • HIGH — NAN/INF now rejected explicitly per line (they previously slipped past both the
 *     non-negative rule and the header balance rule, since NAN/INF comparisons are false either way).
 *   • HIGH — account row locks are now acquired in ONE statement, sorted ascending by account id,
 *     regardless of which resolution path (explicit accountId vs purposeCode) found the account or
 *     what order the draft's lines list them in — closing the lock-order/lock-timing mismatch that
 *     could deadlock two concurrent posts touching the same accounts in opposite orders. Belt-and-
 *     braces for BLOCKER 1 (AccountResolver.php, a different fixer's file) is folded into the same
 *     pass: the same-tenant assertion now runs unconditionally on every resolved account, not only
 *     the explicit-accountId branch. `DB::transaction()` on post()/reverse()/repost() now also takes
 *     an explicit retry count, so a deadlock that still occurs is retried rather than surfaced raw.
 *   • MEDIUM — reverse() now stamps `posting_status = 'reversed'` on the ORIGINAL transaction (it
 *     previously only ever touched the new reversal row), so the enum value from migration
 *     2026_08_24_120004 is no longer dead and downstream "was this reversed?" checks don't need a
 *     reverse join.
 *   • MEDIUM — DocumentDraft::$allowLockedPeriods completes the seam from post() down to
 *     PeriodGuard::assertOpen()'s already-present (but previously unreachable) bypass parameter.
 *   • MEDIUM — FC consistency (step 3f) now compares currency case-insensitively (a line tagged
 *     'kwd' used to slip into the foreign-currency branch and skip the base-currency identity check
 *     entirely) and asserts amount ≈ originalAmount × exchangeRate for foreign-currency lines.
 *   • MEDIUM — repost() now asserts the replacement draft's companyId matches the document being
 *     reversed, and that the document being reversed is actually posting_status = 'posted'
 *     (InvalidRepostSourceException).
 *   • MEDIUM — accounts.deleted_at (added by migration 2026_08_24_120002, but Account does not yet
 *     `use SoftDeletes` — a different fixer's file) is now checked explicitly during account
 *     resolution: a soft-deleted account is refused rather than silently posted to.
 *   • LOW — a line that supplies both an explicit accountId and a non-empty purposeCode is now
 *     rejected outright (ambiguous input) instead of silently preferring accountId.
 *   • LOW — a zero-amount line is now rejected: `amount`/`originalAmount` must be strictly > 0. A
 *     zero-value double-entry line has no real accounting meaning and would otherwise trivially pass
 *     every rule while still burning a real document number — this was flagged as a judgement call in
 *     the verification findings; resolved here in favour of rejecting it.
 *   • LOW — reverse()'s existing-reversal idempotency probe now excludes soft-deleted rows too (same
 *     `withoutGlobalScopes()` gap as above). Whether a REV document may itself be reversed
 *     (REV-of-REV) remains deliberately UNRESTRICTED, as file 11's prose is genuinely ambiguous
 *     between "refuse if already has a reversal" (this build's reading, still enforced) and "refuse
 *     if this IS a reversal" — recorded here as a conscious non-fix, not an oversight.
 *
 * ── P1 FIX ROUND 3 (adversarial re-verification of the round above returned FIX-FIRST: BLOCKER 5
 *    was only PARTIALLY closed and the round-above fix itself introduced 3 new HIGH regressions).
 *    Summary of what changed in this pass and why; full reasoning lives at each call site. ────────
 *   • HIGH REGRESSION — the BLOCKER 5 concurrent-race recovery re-query (in post()'s header-insert
 *     catch block) used a PLAIN read, which under MySQL's default REPEATABLE READ isolation is
 *     served from this transaction's read-view snapshot fixed at step 1's own first plain read —
 *     a snapshot taken BEFORE the winning concurrent transaction committed, so the recovery query
 *     could never actually see the row it exists to recover, and the original QueryException
 *     escaped anyway. Fixed by making both re-query paths (findByIdempotencyKey($forUpdate: true)
 *     and the deleted_at-inclusive fallback) LOCKING reads instead — InnoDB defines a locking read
 *     as always reading the latest COMMITTED data, bypassing the snapshot. See the catch block's
 *     own comment for the full timeline proof.
 *   • MEDIUM — isIdempotencyKeyRaceViolation()'s SQLSTATE-23000-or-1062 check was too broad: 23000
 *     is also the class FK violations report, so an FK failure on this same INSERT (e.g. a bad
 *     $draft->invoiceId) could be misread as a race and produce a wrong returned document. Narrowed
 *     to the specific driver code (1062) AND the specific unique index name
 *     (IDEMPOTENCY_KEY_UNIQUE_INDEX), matching the same pattern SequenceService::
 *     isDuplicateKeyViolation() already uses for the identical class of problem.
 *   • MEDIUM — repost() with $new carrying the SAME idempotencyKey as $old (the natural case: a
 *     repost is "the same real document, corrected") used to resolve post()'s own step 1 lookup to
 *     $old itself (now reversed) and silently never post $new at all. Fixed by defining and
 *     enforcing a key convention: repost() suffixes $new's key with ":repost:{old->id}" whenever it
 *     collides with $old's. See repost()'s own docblock for the full convention and why it is safe
 *     for retries.
 *   • MEDIUM — the FC-consistency rule (step 3f, added last round) made reverse() throw on ordinary
 *     legacy foreign-currency lines. The DIRECTION CONVENTION ITSELF WAS VERIFIED CORRECT against a
 *     real feeder (App\Http\Traits\CurrencyExchangeTrait::convert(), as used by PaymentController's
 *     advanced payment-link flow: base = original(FC) × exchangeRate, rate looked up FC→base) — the
 *     bug was that reverse()'s reconstruction of a swapped LineDraft TRUSTED three legacy columns
 *     (currency/original_amount/exchange_rate) as an already-consistent unit, when in practice they
 *     are routinely populated independently by different legacy call sites. Fixed by reconstructing
 *     self-consistently instead: a line only carries a genuine FC figure when its currency is
 *     non-base AND its original_amount is actually populated and positive, in which case
 *     exchangeRate is DERIVED as amount ÷ originalAmount (never trusted from the stored column);
 *     otherwise the line is booked as the base-currency amount it unambiguously already is. See
 *     reverse()'s own comment for the full InvoiceController-based counterexample this closes.
 *   • LOW — DB::transaction()'s retry count is silently inert whenever post()/reverse()/repost()
 *     run nested inside each other (or inside an external caller's own transaction) — Laravel only
 *     retries at the outermost transaction level. Documented at TRANSACTION_RETRY_ATTEMPTS and at
 *     each of the three call sites rather than changed behaviourally, since this class has no
 *     reliable way to distinguish its own legitimate internal nesting from an external caller's.
 *
 * ── P1 FIX ROUND 4 (owner decision: BLOCKING #2 — reusing an idempotency key whose transaction was
 *    soft-deleted must THROW a clear named exception, and the event must be recorded somewhere an
 *    admin can notice) — summary of what changed and why; full reasoning at each call site. ───────
 *   • BLOCKING #2 — the header-insert catch block's soft-delete-inclusive fallback lookup used to
 *     hand the dead (soft-deleted) transaction straight back via toPostedDocument() as if it were
 *     a genuine idempotent return — proven by execution: post key K for 20.000, soft-delete that
 *     transaction, post key K again for 999.000, get the dead 20.000 document back as "success",
 *     with NOTHING written to journal_entries and a real document number burned. Fixed: that
 *     specific branch (the deleted_at-inclusive fallback actually finding a row — which, given
 *     the deleted_at-exclusive lookup immediately above it already found nothing, can only be a
 *     soft-deleted row) now records an IdempotencyKeyRejection (durable — see that model's own
 *     docblock for why it survives what happens next) and throws
 *     SupersededIdempotencyKeyException instead of returning. Throwing — rather than returning —
 *     is also what keeps this path from burning a document number: the exception propagates out of
 *     post()'s own DB::transaction() closure, which rolls back EVERYTHING that closure did on this
 *     call, including step 6's serial_schemas UPDATE, exactly as if the attempt had never been
 *     made (see the header-insert catch block's own comment for the concurrent-race branch, which
 *     is genuinely different and NOT touched by this fix — a live winner's document is still
 *     returned normally there).
 *   • MEDIUM — a line whose `currency` is longer than 3 characters used to reach step 8's INSERT
 *     unchecked and crash with a raw MySQL "Data too long for column 'original_currency'" driver
 *     error (that column is varchar(3); journal_entries.currency is varchar(10), so 3 is the real
 *     limit) — after steps 1-7 had already run, including the document-number reservation. Now
 *     validated explicitly per line (new step 3a.2) and rejected with InvalidCurrencyCodeException
 *     before any of that happens.
 *   • MEDIUM — repost()'s own docblock (see that method, and the round-3 bullet above referencing
 *     "why it is safe for retries") claimed retry-idempotency it does not actually deliver for the
 *     realistic retry shape (a caller re-loading `$old` fresh from the database before calling
 *     repost() again, rather than reusing the exact same still-in-PHP-memory object from the first
 *     attempt). CORRECTED THE DOCBLOCK rather than changing behaviour — see repost()'s own docblock
 *     for the accurate description and why a behaviour change was judged out of scope for this
 *     round rather than attempted.
 *   • LOW — the pre-existing gap "the idempotency-race loser permanently burns a document number"
 *     (header-insert catch block, concurrent-race branch — NOT the BLOCKING #2 branch above, which
 *     this round's fix already makes serial-safe) is now documented precisely at its call site
 *     rather than left silent, per this round's own instruction that documenting a gap precisely is
 *     an acceptable outcome when neither "reserve after insert" (the formatted number is itself an
 *     input to the header INSERT, so it cannot be deferred until after that INSERT succeeds without
 *     a much larger restructure) nor "release on the race path" (decrementing a shared, concurrently
 *     -contended counter after the fact cannot be proven safe — another caller may already have
 *     reserved the number one past it) is a clean fix. See that catch block's own comment.
 *
 * ── W1.1 FIX ROUND (M3/C5 — line attribution; W1 lead report §3, seam S1/C4/S3/S4) — CHANGES THIS
 *    FILE'S MD5. Summary of what changed and why; full reasoning at each call site. ────────────────
 *   • M3/C5 — every attribution field LineDraft now carries (see its own docblock: $invoiceId,
 *     $invoiceDetailId, $taskId, $ledgerType, $partyName, $voucherNumber) is written to its
 *     like-named journal_entries column at step 8, with the exact fallback documented at each
 *     write site below — never a crash, never a NOT NULL violation (every target column is
 *     nullable). `$partyAccountRef` (pre-existing, previously accepted and written nowhere — a
 *     dead parameter) now writes `journal_entries.type_reference_id`. Header-level: the
 *     `transactions` table has no `voucher_number` column at all (see DocumentDraft's own W1.1
 *     docblock note); `$draft->paymentReference`, the one real header-level gap found, is now
 *     written to `transactions.payment_reference`.
 *
 * ── W1.2 FIX ROUND (Task A — engine header attribution: transactions.payment_id) — CHANGES THIS
 *    FILE'S MD5 (old 921ec36fb8b3fa47bc72d8e57f090ace). ─────────────────────────────────────────
 *   • `$draft->paymentId` (see DocumentDraft's own W1.2 docblock note) is now written to
 *     `transactions.payment_id` at the header-write step, alongside the pre-existing
 *     `payment_reference` write — same null-by-default, never-inferred convention. `reference_type`
 *     is deliberately left engine-derived (resolveReferenceType(), 'Receipt' for an 'RV' docType)
 *     rather than forced to legacy's 'Payment' label for the same event: that label was already
 *     inconsistent at HEAD (a receipt of money labelled as a payment OUT), and this class's own
 *     `reference_type` contract (resolved-gap #3 above) is feeder-agnostic — changing its meaning
 *     for one feeder would break every other consumer of that enum. `withRepostIdempotencyKey()`
 *     (a field-for-field DocumentDraft copy) carries `paymentId` over like every other field;
 *     `reverse()`'s reversal-draft reconstruction deliberately does NOT set it (matching the
 *     pre-existing gap that `reverse()` already never carries `paymentReference` over either —
 *     W1 lead report §3.1, "Open — engine, PRE-EXISTING" item 4 — a reversal is not itself
 *     "attached to" the original payment in the same sense a fresh receipt is, and inventing that
 *     link was judged out of this task's scope).
 *
 * ── W2 FIX ROUND (D3 — payment_id belongs to the ORIGINAL receipt only) — CHANGES THIS FILE'S
 *    MD5 AGAIN (old 930de49cad15bcbb28adb298a01ab35e, the value the W1.2 lead's report restored
 *    to). W1.2 Task A above closed one gap (payment_id was never written at all) but opened a
 *    live one: once `transactions.payment_id` is populated, engine header rows fall under
 *    `transactions_payment_id_reference_type_unique` (P0 hotfix migration
 *    2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php) for the first
 *    time — and `reverse()` never clears `payment_id` off the document it reverses (it only
 *    stamps `posting_status` and `reversal_of_transaction_id`), so a reversed original keeps
 *    occupying its `(payment_id, reference_type)` slot. `repost()`'s natural caller shape —
 *    "same real-world payment, corrected" — reuses that same payment_id on the replacement
 *    draft, which used to collide on that slot with a raw, untyped `QueryException` (W1.2 lead
 *    report §5 residual #2). Two independent fixes close this: ─────────────────────────────────
 *   • `repost()` now forces the REPLACEMENT draft's `paymentId` to NULL unconditionally (via the
 *     new private `withoutPaymentId()`), regardless of what the caller's `$new` draft carries.
 *     `payment_id` is now carried ONLY by the ORIGINAL receipt document that first set it.
 *     CORRECTION (residual 7 — the paragraph this replaces was FALSE and reproduced as such by an
 *     independent verifier): the replacement is NOT "linked to the payment transitively via
 *     `reversal_of_transaction_id`" — that column is stamped on the REVERSAL row (pointing back
 *     at the ORIGINAL, `reversal_of_transaction_id = original.id`), never on the replacement, and
 *     the replacement's own `reversal_of_transaction_id` is left NULL (see `post()`'s header
 *     write — it is never told about the reversal or the original). The true chain after a
 *     `repost($old, $new, ...)` is three independent rows with NO column-level chain at all:
 *     `$old` (payment_id = P, posting_status = 'reversed', reversal_of_transaction_id = NULL) ->
 *     the reversal (`payment_id` = NULL, `reversal_of_transaction_id` = $old->id, idempotency_key
 *     = `'rev:'.$old->id`) -> the replacement (`payment_id` = NULL, `reversal_of_transaction_id`
 *     = NULL, idempotency_key = `$new`'s own key, suffixed with `':repost:'.$old->id` only when it
 *     collided with `$old`'s — see `withRepostIdempotencyKey()`'s own corrected docblock). A query
 *     such as `WHERE payment_id = P AND posting_status = 'posted'` finds NEITHER the reversal nor
 *     the replacement after a repost — only `$old` itself (now `'reversed'`, so typically excluded
 *     by that same query) ever carried payment_id P. The only reliable way to find a repost's
 *     REPLACEMENT is by its idempotency key (see the convention above), never via `payment_id` or
 *     `reversal_of_transaction_id` — a future feeder must not assume either column re-links a
 *     replacement back to the payment that funded the original document.
 *     `withRepostIdempotencyKey()` (the idempotency-key-suffix helper) is fixed the same way
 *     defensively — it now always emits `paymentId: null` rather than copying `$draft->paymentId`
 *     — so it can never re-introduce a payment_id on a repost draft even if called from anywhere
 *     else in the future.
 *     `reverse()`'s own reversal-draft construction was ALREADY correct (it never set
 *     `paymentId` at all, defaulting to DocumentDraft's own `null` default) — no change needed
 *     there; only `repost()`'s caller-built `$new` needed forcing.
 *   • A 1062 that IS a genuine collision on `transactions_payment_id_reference_type_unique`
 *     (e.g. a feeder bug — two different DocumentDrafts, two different idempotency keys, the
 *     same payment_id/reference_type pair) is no longer let through as a raw `QueryException`.
 *     `isIdempotencyKeyRaceViolation()` already refused to misidentify it (it names the OTHER
 *     index), so the exact same header-insert catch block now separately recognises THIS index
 *     via the new `isPaymentReferenceTypeRaceViolation()` (identical narrow-match pattern: driver
 *     code 1062 AND the specific index name) and throws the new, typed
 *     `DuplicatePaymentReferenceException` — naming the exact `payment_id`/`reference_type` pair
 *     that collided — instead. This is NOT treated as a recoverable idempotency race (unlike the
 *     sibling check): the colliding row is a DIFFERENT logical document, not a retry of the one
 *     being posted, so there is no existing document this method could safely hand back instead.
 *     CORRECTION (W2.1, residual 2 — see the header-insert catch block's own comment for the
 *     full fix): "no existing document this method could safely hand back instead" was true only
 *     when this draft's own `idempotencyKey` is null. When it is non-null, W2's own unconditional
 *     throw here was ITSELF the bug — it could not tell a genuine cross-document collision apart
 *     from this exact draft being retried (same idempotencyKey, same payment_id/reference_type),
 *     which collides on THIS index too and does have a safe existing document to hand back. The
 *     catch block now probes for a same-key document before throwing this exception, exactly as
 *     it already did for the sibling idempotency-key race.
 *
 * ── W5.L FIX ROUND (voucher/instrument prerequisites, w5-brief.md §W5.L) ─────────────────────
 *   • DOC_TYPE_REFERENCE_TYPE gains an 'AST' => 'Payment' entry (agent-settlement's temporary
 *     series, w5-brief.md §W5.S) — without it, resolveReferenceType()'s fallback for any docType
 *     this map doesn't name is 'Invoice', which is wrong for a settlement document; grouped with
 *     PV/DBN under 'Payment' for the same reason those two are (a company-side cash movement, not
 *     a sale).
 *   • Step 8 (line write) now also persists $line->chequeNo / $line->chequeDate / $line->bankInfo /
 *     $line->authNo / $line->chequeClearanceDate onto their like-named journal_entries columns —
 *     see LineDraft's own "W5.L FIX ROUND" docblock for the full column list, the one documented
 *     cheque_date useCurrent() behaviour change, and why chequeClearanceDate needed a new
 *     migration while the other four did not.
 *   • w5-brief.md §W5.P: step 8's line write of `journal_entries.reconciled` is no longer a bare
 *     hardcoded `0` -- it now reads `$line->reconciled ?? 0`, so `BankPaymentController`'s
 *     PaymentByDate fast path can set `reconciled = 2` on its own new line via an engine line flag
 *     (LineDraft::$reconciled) rather than a raw post-insert column write. Every feeder that never
 *     sets this field (every one that existed before W5.P) sees no change.
 * ── (continuing the W5.L index below) ──────────────────────────────────────────────────────────
 *   • w5-brief.md §W5.L item 3 ("doc_type RV|PV|AST accepted by the engine with sub_type lists")
 *     is DELIBERATELY NOT enforced here. A first attempt added the check directly in post(), and
 *     running the full accounting suite immediately proved it wrong: docType='PV'/'RV' are SHARED
 *     namespaces with already-shipped feeders (ClientController, HandleGatewayRefundStatusChanged,
 *     RefundPostingService, CheckMyFatoorahPayments, PaymentController) carrying nine real
 *     sub_type values that have nothing to do with W5's voucher taxonomy — a PostingService-level
 *     whitelist rejected every one of them. See App\Services\Accounting\VoucherSubTypeGuard's own
 *     docblock for the full list and the corrected, caller-side design: the vocabulary is
 *     registered in config('accounting.sub_types') and enforced only by feeders that explicitly
 *     call that guard (W5.R/W5.P/W5.S), never by this shared engine chokepoint.
 */
final class PostingService
{
    /** The closed legacy enum on transactions.reference_type. */
    private const VALID_REFERENCE_TYPES = ['Receipt', 'Invoice', 'Payment', 'Refund'];

    /**
     * Best-effort docType -> reference_type fallback map — see class docblock, resolved-gap #3.
     *
     * WARNING (residual 2 docblock note): both 'PV' and 'DBN' fall back to the SAME
     * reference_type, 'Payment' — the identical string
     * PaymentController::firstOrCreateFailureTransaction() writes for a legacy gateway-failure
     * row (see that method's own docblock in PaymentController). A future feeder that posts a PV
     * or DBN DocumentDraft carrying a non-null $paymentId, WITHOUT also pinning an explicit
     * $sourceType (resolveReferenceType() only consults this map when $sourceType is null or not
     * already one of the four valid strings), collides on transactions_payment_id_reference_type_
     * unique with whatever legacy failure row already occupies that same (payment_id, 'Payment')
     * slot for that payment. This class's own header-INSERT catch (residual 2 fix) turns that
     * collision into DuplicatePaymentReferenceException rather than a raw driver error, but it is
     * still a real, user-visible refusal to post — a PV/DBN feeder that carries a payment_id
     * should pin $sourceType explicitly rather than rely on this fallback.
     */
    private const DOC_TYPE_REFERENCE_TYPE = [
        'INV' => 'Invoice',
        'RV' => 'Receipt',
        'PV' => 'Payment',
        'CRN' => 'Refund',
        'DBN' => 'Payment',
        'JV' => 'Invoice',
        'OJV' => 'Invoice',
        'REV' => 'Invoice',
        // W5.L: agent-settlement's temporary series (w5-brief.md §W5.S) — grouped with PV/DBN
        // under 'Payment' for the same reason those two are (a company-side cash movement, not a
        // sale); see this class's own "W5.L FIX ROUND" docblock note.
        'AST' => 'Payment',
    ];

    /** How many times DB::transaction() retries a call whose only failure was a deadlock/serialization
     *  error (P1 fix round, HIGH finding: lock-order normalisation reduces deadlocks but cannot
     *  eliminate them under real concurrency; Laravel's default of 1 attempt turns any residual
     *  deadlock into a raw exception at the caller instead of a transparent retry).
     *
     *  P1 FIX ROUND 3 (LOW finding — this retry is INERT when nested): Laravel's
     *  Connection::transaction() only actually retries at the outermost transaction nesting level
     *  — when a deadlock/serialization error is caught while `$this->transactions > 1` (i.e. this
     *  DB::transaction() call is running inside an already-open transaction, at a SAVEPOINT), it
     *  decrements the savepoint counter and rethrows IMMEDIATELY, ignoring whatever $attempts value
     *  was passed to that inner call. This class has exactly one internal nesting chain —
     *  repost() calls reverse(), and reverse() calls post() — so:
     *    - Calling post() or reverse() DIRECTLY (the normal case, and the only case exercised by
     *      today's flag-off/wired-to-nothing build) IS a true top-level DB::transaction() call, and
     *      TRANSACTION_RETRY_ATTEMPTS behaves exactly as documented above.
     *    - Calling repost() makes IT the true top-level call; its own retry re-runs the ENTIRE
     *      reverse()+post() callback on a deadlock, which is correct and sufficient — but the
     *      TRANSACTION_RETRY_ATTEMPTS on reverse()'s and post()'s OWN DB::transaction() calls,
     *      reached only via repost()'s nesting, are inert for that call chain specifically.
     *    - If ANY external caller ever wraps a call to post()/reverse()/repost() inside its OWN
     *      `DB::transaction()` block, ALL THREE retry counts in this class become inert for that
     *      call, with no other retry logic anywhere in the chain to compensate — a deadlock would
     *      surface raw. This is a documented constraint on callers (see each method's own
     *      docblock), not a bug this class can fix locally without either (a) refusing to run
     *      inside an ambient transaction at all — too disruptive a behaviour change for a LOW
     *      finding — or (b) trying to detect and compensate for arbitrary external nesting, which
     *      Laravel gives this class no reliable way to distinguish from its own legitimate internal
     *      nesting (both look identical: DB::transactionLevel() > 0 on entry). Documenting the
     *      constraint, rather than guessing at behaviour change, is the chosen fix. */
    private const TRANSACTION_RETRY_ATTEMPTS = 3;

    /** The exact name Laravel's schema builder gives `$table->unique(['company_id',
     *  'idempotency_key'])` with no explicit name (migration 2026_08_24_120004) — verified against
     *  that migration's literal call, not guessed. Used by isIdempotencyKeyRaceViolation() (P1 fix
     *  round 3) to narrow the race-recovery catch to THIS specific index. */
    private const IDEMPOTENCY_KEY_UNIQUE_INDEX = 'transactions_company_id_idempotency_key_unique';

    /** W2 fix (D3): the exact name Laravel's schema builder gives `$table->unique(['payment_id',
     *  'reference_type'])` with no explicit name (P0 hotfix migration
     *  2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php — verified
     *  against that migration's literal call, not guessed). Used by
     *  isPaymentReferenceTypeRaceViolation() to distinguish a genuine collision on THIS index
     *  from the idempotency-key race isIdempotencyKeyRaceViolation() already recovers from. */
    private const PAYMENT_REFERENCE_TYPE_UNIQUE_INDEX = 'transactions_payment_id_reference_type_unique';

    /** W3-prereq lane B fix (doc 17 §3.3/§4): the exact name Laravel's schema builder gives
     *  `$table->unique(['company_id', 'doc_type', 'reference_number'], 'transactions_company_
     *  doctype_refnum_unique')` (migration 2026_08_24_120008_add_unique_reference_number_to_
     *  transactions_table.php — verified against that migration's literal, explicitly-named call).
     *  Used by isSerialCollisionViolation() to recognise an engine-minted reference_number
     *  colliding with one already in the table (legacy or a prior engine post) and retry with the
     *  next serial, distinct from the idempotency-key and payment-reference-type races above. */
    private const SERIAL_COLLISION_UNIQUE_INDEX = 'transactions_company_doctype_refnum_unique';

    /** Bounded retry ceiling for a serial-number collision on SERIAL_COLLISION_UNIQUE_INDEX (see
     *  the header-insert catch block). Exhausting this many consecutive collisions throws
     *  SerialCollisionExhaustedException instead of retrying forever — a real, actionable signal
     *  that accounting:seed-serial-schemas needs to run (or re-run) for this company/branch/
     *  doc_type/year rather than silently looping. */
    private const SERIAL_COLLISION_MAX_ATTEMPTS = 5;

    /** P1 FIX ROUND 4 (MEDIUM finding): the tighter of the two legacy columns a line's `currency`
     *  is written into verbatim at step 8 — `journal_entries.currency` is varchar(10),
     *  `.original_currency` is varchar(3) (both verified against their migrations directly, see
     *  InvalidCurrencyCodeException's own docblock) — so 3, not 10, is the real limit. */
    private const MAX_CURRENCY_CODE_LENGTH = 3;

    /** Header balance tolerance: abs(Σdebit − Σcredit) < this (R2, BUG-C1, check 1.1) — file 11 L258. */
    private readonly float $balanceTolerance;

    /** Money decimal places for base-currency and FC amounts alike (KWD 3dp). */
    private readonly int $moneyDecimals;

    /** See class docblock, resolved-gap #8. */
    private readonly string $baseCurrency;

    public function __construct(
        private AccountResolver $accounts,
        private SequenceService $sequences,
        private PeriodGuard $periods,
        private Money $money,
    ) {
        // Sourced from config/accounting.php (built alongside this class against the same
        // contract) so the tolerance/decimals/base-currency live in exactly one place; the
        // literal defaults here only guard a missing/unpublished config key.
        $this->balanceTolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $this->moneyDecimals = (int) config('accounting.engine.base_decimals', 3);
        $this->baseCurrency = (string) config('accounting.engine.base_currency', 'KWD');
    }

    /**
     * Post one balanced document atomically. See class docblock for the `$userId` signature note.
     *
     * @throws UnbalancedDocumentException
     * @throws NonLeafAccountException
     * @throws NonNegativeAmountException
     * @throws OneSidedLineException
     * @throws FrozenAccountException
     * @throws FcConsistencyException
     * @throws CrossTenantAccountException
     * @throws InvalidCurrencyCodeException
     * @throws SupersededIdempotencyKeyException
     * @throws PostingEngineDisabledException
     * @throws \App\Exceptions\Accounting\UnmappedPurposeException
     * @throws \InvalidArgumentException
     */
    public function post(DocumentDraft $draft, ?int $userId = null): PostedDocument
    {
        if ($draft->companyId <= 0) {
            throw new \InvalidArgumentException('DocumentDraft::$companyId must be a positive integer.');
        }

        if ($draft->lines === []) {
            // Not explicitly named by file 11, but a zero-line "document" is meaningless and would
            // trivially pass the Σd=Σc check while writing an empty header — defensive addition.
            throw new UnbalancedDocumentException(0.0, 0.0, 'DocumentDraft::$lines must contain at least one line.');
        }

        // ── W0 kill-switch gate (see PostingEngineDisabledException's own docblock) ──────────────
        // The single rollback lever for this engine until P2 wires a feeder. Placed after the two
        // cheap arg-validation guards above but BEFORE THIS METHOD's OWN DB::transaction() opens
        // below, so a refusal here never opens/rolls back post()'s own transaction and never
        // touches SequenceService's counter. reverse() DOES own two writes of its own (the
        // `->update(['reversal_of_transaction_id' => ...])` and
        // `->update(['posting_status' => 'reversed'])` calls inside PostingService::reverse())
        // that never go through post() — but both run AFTER reverse()'s internal
        // `$result = $this->post($reversalDraft, $userId);` call, inside reverse()'s own
        // already-open transaction, so a refusal here still rolls them back (see
        // PostingEngineDisabledException for the full chain, incl. repost()). CAVEAT: when reached
        // via reverse() or repost(), THEIR outer DB::transaction() and lockForUpdate() row lock are
        // already open by the time this gate runs, so a refusal there still triggers a rollback of
        // that outer transaction — "never opens/rolls back a transaction" is only true for a
        // direct, top-level call to post().
        //
        // Company is resolved strictly from DocumentDraft::$companyId (a DB-backed value the caller
        // is responsible for populating from the record being posted), never from Auth::user() —
        // this class is engine-layer and must stay caller-agnostic; a queue worker or webhook has
        // no authenticated user. Fails CLOSED on every condition: engine off, company flag off, or
        // company unresolvable all refuse identically.
        $globallyEnabled = (bool) config('accounting.engine.enabled');
        $company = Company::find($draft->companyId);
        $companyEnabled = $company !== null ? (bool) $company->posting_engine_enabled : null;

        if (! $globallyEnabled || $company === null || ! $companyEnabled) {
            throw new PostingEngineDisabledException($draft->companyId, $globallyEnabled, $companyEnabled);
        }

        $userId = $userId ?? $draft->userId;

        return DB::transaction(function () use ($draft, $userId) {
            // ── Step 1: idempotency backstop (F2/F8/F9) ──────────────────────────────────────
            // P1 FIX ROUND (BLOCKER 5 + HIGH soft-delete finding): this lookup used to filter
            // posting_status = 'posted', but the DB unique index (company_id, idempotency_key)
            // does not — so a same-key row in ANY other status (e.g. 'reversed') made this SELECT
            // miss and the header INSERT below explode with a raw duplicate-key error instead of
            // returning cleanly. It also used to run through withoutGlobalScopes(), which drops
            // SoftDeletingScope along with every other scope, so a soft-deleted transaction with
            // this key would incorrectly satisfy "already posted" and swallow a legitimate retry.
            // Both are fixed here: no posting_status filter, explicit deleted_at exclusion.
            if ($draft->idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($draft->companyId, $draft->idempotencyKey);

                if ($existing !== null) {
                    return $this->toPostedDocument($existing);
                }
            }

            $docDate = Carbon::instance($draft->docDate);

            // ── Steps 2-3: resolve every line's account + per-line iron rules ────────────────
            foreach ($draft->lines as $index => $line) {
                if (! $line instanceof LineDraft) {
                    throw new \InvalidArgumentException(
                        "DocumentDraft::\$lines[{$index}] must be a LineDraft instance."
                    );
                }
            }

            // Step 2a: resolve which account id each line targets, WITHOUT locking yet — locking
            // happens in one deterministic pass below (step 2b) so lock ORDER never depends on
            // which resolution path found the account or what order the draft lists its lines.
            $targetAccountIds = [];
            foreach ($draft->lines as $index => $line) {
                $targetAccountIds[$index] = $this->targetAccountId($line, $draft->companyId, $index);
            }

            // Step 2b: lock-order normalisation (P1 fix round, HIGH finding). Previously,
            // explicit-accountId lines were locked (lockForUpdate) individually, in draft line
            // order, at resolve time — while purposeCode-resolved lines were not locked at
            // resolve time at all; they only ever picked up an implicit lock later, at the old
            // step 9 actual_balance increment (now removed — see BLOCKER 4), in a DIFFERENT
            // order. Two concurrent posts touching the same accounts in opposite orders could
            // deadlock. Fix: acquire every row lock this document needs in ONE statement, sorted
            // ascending by account id, so any two concurrent posts that share accounts always
            // request their locks in the same relative order — a lock-order cycle (the precondition
            // for a deadlock) becomes impossible between two calls into this method.
            $uniqueAccountIds = array_values(array_unique($targetAccountIds, SORT_NUMERIC));
            sort($uniqueAccountIds, SORT_NUMERIC);

            $lockedAccounts = Account::withoutGlobalScopes()
                ->whereIn('id', $uniqueAccountIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Batched leaf test (see step 3d below): one query for "which of these accounts have
            // at least one child row", instead of one exists() query per line.
            $accountIdsWithChildren = Account::withoutGlobalScopes()
                ->whereIn('parent_id', $uniqueAccountIds)
                ->distinct()
                ->pluck('parent_id')
                ->flip()
                ->all();

            $resolved = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($draft->lines as $index => $line) {
                /** @var Account|null $account */
                $account = $lockedAccounts->get($targetAccountIds[$index]);

                if ($account === null) {
                    // CrossTenantAccountException is the only "account resolution went wrong"
                    // exception file 11's contract defines; a nonexistent account has no owning
                    // company to report, so accountCompanyId is 0 (sentinel: not applicable).
                    throw new CrossTenantAccountException(
                        $targetAccountIds[$index],
                        0,
                        $draft->companyId,
                        "Account #{$targetAccountIds[$index]} does not exist."
                    );
                }

                // Step 2c — same-tenant assertion (T7.1). P1 FIX ROUND belt-and-braces for
                // BLOCKER 1 (AccountResolver.php, a different fixer's file): the original build
                // only asserted this for the explicit-accountId branch, trusting the
                // purposeCode→AccountResolver branch to have already checked it — but
                // AccountResolver's own query has no company filter on the account it loads, only
                // on the system_accounts row it looked up. Running this check unconditionally,
                // here, on whatever account either path resolved, means a mis-seeded or
                // mis-written system_accounts row can never smuggle a cross-tenant account past
                // this class even if AccountResolver itself fails to catch it.
                if ((int) $account->company_id !== $draft->companyId) {
                    throw new CrossTenantAccountException($account->id, (int) $account->company_id, $draft->companyId);
                }

                // P1 FIX ROUND (MEDIUM finding: accounts.deleted_at is inert). The migration
                // added `deleted_at` to `accounts`, but App\Models\Account does not (yet) `use
                // SoftDeletes` (that trait decision belongs to a different fixer's file), so
                // nothing filters it automatically and withoutGlobalScopes() above wouldn't have
                // stripped a soft-delete scope anyway — there isn't one registered. Read the raw
                // column value directly (still selected/hydrated regardless of the trait) and
                // refuse explicitly rather than silently post to a deleted account.
                if ($account->deleted_at !== null) {
                    throw new FrozenAccountException(
                        $account->id,
                        $account->name,
                        "DocumentDraft::\$lines[{$index}]: account #{$account->id} ({$account->name}) "
                        .'is soft-deleted.'
                    );
                }

                // Step 3a: round to money decimals BEFORE evaluating 3b/3f, so those rules compare
                // exactly the values that will be persisted.
                $amount = $this->money->round($line->amount, $this->moneyDecimals);
                $originalAmount = $this->money->round($line->originalAmount, $this->moneyDecimals);

                // Step 3a.1 — finiteness guard (P1 fix round, HIGH finding). NAN and INF both make
                // every `< 0` / `>=` comparison below evaluate to false, so without this guard a
                // NAN/INF amount silently passes the non-negative rule (3b) AND the header balance
                // rule (step 4, since abs(NAN) is NAN, never >= tolerance) and posts a real,
                // meaningless document.
                if (! is_finite($amount) || ! is_finite($originalAmount) || ! is_finite($line->exchangeRate)) {
                    $badLabel = ! is_finite($amount)
                        ? 'amount'
                        : (! is_finite($originalAmount) ? 'originalAmount' : 'exchangeRate');
                    $badValue = match ($badLabel) {
                        'amount' => $amount,
                        'originalAmount' => $originalAmount,
                        default => $line->exchangeRate,
                    };

                    throw new NonNegativeAmountException(
                        $badValue,
                        "DocumentDraft::\$lines[{$index}] {$badLabel}",
                        sprintf(
                            'DocumentDraft::$lines[%d] %s is not finite (NAN/INF) — this would '
                            .'otherwise silently pass both the non-negative rule and the header '
                            .'balance rule.',
                            $index,
                            $badLabel
                        )
                    );
                }

                // Step 3a.2 — currency code length guard (P1 fix round 4, MEDIUM finding).
                // $line->currency is written VERBATIM, unmodified, into both
                // journal_entries.currency (varchar(10)) and .original_currency (varchar(3)) at
                // step 8 below — 3 is therefore the real limit, not 10. LineDraft (a different
                // fixer's file) applies no length validation of its own, so without this guard an
                // over-long value sailed through every rule above and reached that INSERT
                // unchecked, where MySQL's strict mode turned it into a raw, uncatchable-by-type
                // driver exception ("Data too long for column 'original_currency'") — AFTER steps
                // 1-7 had already run, including the document-number reservation (step 6). Checked
                // here, before anything is written, with the exact raw value that will later be
                // persisted (not the trimmed/uppercased $lineCurrency step 3f computes below) so
                // this guard can never pass a value step 8 would then reject.
                if (mb_strlen($line->currency) > self::MAX_CURRENCY_CODE_LENGTH) {
                    throw new InvalidCurrencyCodeException(
                        $line->currency,
                        self::MAX_CURRENCY_CODE_LENGTH,
                        "DocumentDraft::\$lines[{$index}]"
                    );
                }

                // Step 3b: non-negative AND non-zero. P1 FIX ROUND (LOW finding, a documented
                // judgement call): the original code only rejected amount < 0, so amount == 0.0
                // passed cleanly on both sides — a debit=0/credit=0 row is indistinguishable from
                // "neither side", has no real double-entry meaning, and still burns a real
                // document number. Resolved here in favour of requiring amount > 0 strictly.
                if ($amount <= 0 || $originalAmount <= 0) {
                    $badLabel = $amount <= 0 ? 'amount' : 'originalAmount';
                    $badValue = $amount <= 0 ? $amount : $originalAmount;

                    throw new NonNegativeAmountException(
                        $badValue,
                        "DocumentDraft::\$lines[{$index}] {$badLabel}",
                        sprintf(
                            'DocumentDraft::$lines[%d] %s must be > 0 (got %.6f) — a zero-amount '
                            .'line has no real double-entry meaning.',
                            $index,
                            $badLabel,
                            $badValue
                        )
                    );
                }

                // Step 3c: side is debit XOR credit.
                $isDebit = $line->side === 'debit';
                $isCredit = $line->side === 'credit';
                if ($isDebit === $isCredit) {
                    throw new OneSidedLineException(
                        $isDebit ? $amount : 0.0,
                        $isCredit ? $amount : 0.0,
                        "DocumentDraft::\$lines[{$index}]: \$side must be exactly one of 'debit' or "
                        ."'credit', got '{$line->side}'."
                    );
                }

                // Step 3d: account must be a leaf (R1, BUG-C2). P1 FIX ROUND (HIGH finding):
                // `accounts.is_group` defaults to TRUE (migration
                // 2025_04_03_112301_add_new_columns_in_accounts_table.php) and the `is_group =
                // EXISTS(child)` backfill file 11 §P1.0 requires is explicitly deferred out of
                // this build's scope (verification: ~42,401 accounts flagged is_group vs 25
                // genuine leaf violations). Trusting is_group as currently seeded would reject
                // nearly every real account. CHOSEN FIX: actual child-row existence is the sole
                // authoritative leaf test in P1 — is_group is no longer consulted for rejection at
                // all. Once the backfill lands, a backfilled is_group will always equal
                // EXISTS(child) by construction, so restoring it as a redundant check would be a
                // no-op change, not a behaviour change.
                if (isset($accountIdsWithChildren[$account->id])) {
                    throw new NonLeafAccountException(
                        $account->id,
                        $account->name,
                        "DocumentDraft::\$lines[{$index}]: account #{$account->id} "
                        ."({$account->name}) is not a leaf account."
                    );
                }

                // Step 3e: account not disabled.
                if ((bool) $account->disabled) {
                    throw new FrozenAccountException(
                        $account->id,
                        $account->name,
                        "DocumentDraft::\$lines[{$index}]: account #{$account->id} "
                        ."({$account->name}) is disabled."
                    );
                }

                // Step 3f: FC consistency (R3). P1 FIX ROUND (MEDIUM finding): currency comparison
                // is now case-normalised — a line tagged 'kwd' used to slip past the `===`
                // comparison into the foreign-currency branch below, which only asserted
                // exchangeRate > 0 and never checked amount against originalAmount at all.
                $lineCurrency = strtoupper(trim($line->currency));
                $baseCurrency = strtoupper($this->baseCurrency);

                if ($lineCurrency === $baseCurrency) {
                    if (
                        abs($originalAmount - $amount) > $this->balanceTolerance
                        || abs($line->exchangeRate - 1.0) > 0.000001
                    ) {
                        throw new FcConsistencyException(
                            $line->currency,
                            $this->baseCurrency,
                            $amount,
                            $originalAmount,
                            $line->exchangeRate,
                            "DocumentDraft::\$lines[{$index}]: base-currency line must have "
                            .'originalAmount === amount and exchangeRate === 1.0.'
                        );
                    }
                } else {
                    if ($line->exchangeRate <= 0) {
                        throw new FcConsistencyException(
                            $line->currency,
                            $this->baseCurrency,
                            $amount,
                            $originalAmount,
                            $line->exchangeRate,
                            "DocumentDraft::\$lines[{$index}]: exchangeRate must be > 0 for non-base "
                            ."currency '{$line->currency}'."
                        );
                    }

                    // P1 FIX ROUND (MEDIUM finding): assert amount and originalAmount actually
                    // agree via exchangeRate — previously a structurally-valid-but-semantically-
                    // nonsensical triple (e.g. amount=100.000, originalAmount=999.000,
                    // exchangeRate=0.001) posted cleanly. Convention chosen (file 11 states none):
                    // amount (base) ≈ originalAmount (FC) × exchangeRate — i.e. exchangeRate is
                    // base-units-per-one-FC-unit. Tolerance is intentionally looser than
                    // balanceTolerance to allow for genuine multi-step FX rounding upstream.
                    $expectedAmount = $this->money->round($originalAmount * $line->exchangeRate, $this->moneyDecimals);
                    $fcTolerance = max(0.01, abs($amount) * 0.01);

                    if (abs($amount - $expectedAmount) > $fcTolerance) {
                        throw new FcConsistencyException(
                            $line->currency,
                            $this->baseCurrency,
                            $amount,
                            $originalAmount,
                            $line->exchangeRate,
                            sprintf(
                                'DocumentDraft::$lines[%d]: amount (%.3f) is not consistent with '
                                .'originalAmount (%.3f) × exchangeRate (%.6f) = %.3f (tolerance %.3f).',
                                $index,
                                $amount,
                                $originalAmount,
                                $line->exchangeRate,
                                $expectedAmount,
                                $fcTolerance
                            )
                        );
                    }
                }

                $debit = $isDebit ? $amount : 0.0;
                $credit = $isCredit ? $amount : 0.0;

                $totalDebit += $debit;
                $totalCredit += $credit;

                $resolved[] = [
                    'line' => $line,
                    'account' => $account,
                    'debit' => $debit,
                    'credit' => $credit,
                    'amount' => $amount,
                    'originalAmount' => $originalAmount,
                ];
            }

            // ── Step 4: header balance rule (R2, BUG-C1, check 1.1) ───────────────────────────
            // P1 FIX ROUND (HIGH finding): every addend into these totals is already asserted
            // finite above, so this should be unreachable — kept as an explicit, cheap assertion
            // at the exact point file 11's rule lives, rather than relying only on the per-line
            // guard to have caught every path.
            if (! is_finite($totalDebit) || ! is_finite($totalCredit)) {
                throw new UnbalancedDocumentException($totalDebit, $totalCredit, 'Document totals are not finite.');
            }

            if (abs($totalDebit - $totalCredit) >= $this->balanceTolerance) {
                throw new UnbalancedDocumentException($totalDebit, $totalCredit);
            }

            // ── Step 5: period guard + posting_date resolution (P2.5.B) ──────────────────────────
            // P1 FIX ROUND (MEDIUM finding): $draft->allowLockedPeriods completes the seam down to
            // PeriodGuard's bypass parameter.
            // P2.5.A FIX ROUND (p2_5-brief.md §P2.5.A): PeriodGuard::assertOpen() is no longer a
            // no-op — accounting_periods now exists and this call can genuinely throw
            // PeriodLockedException. Two more trailing arguments are passed for the soft_closed
            // override path (see PeriodGuard's own docblock): $userId (already resolved above, a
            // few lines up — `$userId = $userId ?? $draft->userId;`) and $draft->overrideReason.
            //
            // P2.5.B FIX ROUND (p2_5-brief.md §P2.5.B; period-lock-design.md §8.1 — the three-date
            // model): the document's own date ($docDate == transaction_date) is NEVER what gets
            // shifted and is passed to assertOpen() completely unchanged from P2.5.A's own call —
            // every existing override path (locked+$allowLockedPeriods, soft_closed+permission+
            // reason) behaves EXACTLY as before, landing the document in the period it asked for.
            // What's new is what happens when assertOpen() throws {@see PeriodLockedException}
            // WITHOUT a valid override in play (i.e. exactly the shape that used to end this
            // method right here, with nothing posted): rather than propagate that throw, a NORMAL
            // (unprivileged, no-override) post is silently redirected — `$postingDate` (a
            // SEPARATE value from `$docDate`, persisted to the new `journal_entries.posting_date`
            // / `transactions.posting_date` columns added by this same wave's migration; see
            // ../../../database/migrations/*_p25b_*.php) becomes the earliest period this company
            // has open on or after $docDate, via PeriodGuard::earliestOpenOnOrAfter() — and
            // $docDate/transaction_date is untouched either way. `accounting.posting_date_shifted`
            // is logged once the transaction header exists (step 7 below supplies the id).
            //
            // `$requestedPostingDate` is `$draft->postingDate ?? $docDate` — DocumentDraft::
            // $postingDate "defaults to docDate" (see that class's own P2.5.B docblock) exactly as
            // the brief specifies; a caller that never touches the new field gets $docDate here,
            // identical to every pre-P2.5.B draft. A caller that DOES set $draft->postingDate
            // explicitly is asking PostingService to resolve/validate THAT date instead — the SAME
            // open/shift/override rule below still applies to it: setting this field changes WHICH
            // date gets checked, never WHETHER the shift can fire. An explicit postingDate landing
            // on a locked/soft_closed period with no valid override still shifts, exactly like a
            // defaulted one (PostingSeamPeriodGuardTest::
            // test_engine_branch_shifts_an_explicit_posting_date_the_same_way_as_a_defaulted_one()).
            // What actually lands a document unshifted on a non-open date is pairing an explicit
            // postingDate with a VALID override — assertOpen() below never throws in that case, so
            // the catch block (and its shift) is never reached at all (see that same test file's
            // …_unshifted_when_a_valid_override_is_present() sibling).
            $requestedPostingDate = Carbon::instance($draft->postingDate ?? $draft->docDate);
            $postingDate = $requestedPostingDate;
            $postingDateShifted = false;

            try {
                $this->periods->assertOpen(
                    $draft->companyId,
                    $requestedPostingDate,
                    $draft->allowLockedPeriods,
                    $userId,
                    $draft->overrideReason,
                );
            } catch (PeriodLockedException $e) {
                $postingDate = $this->periods->earliestOpenOnOrAfter($draft->companyId, $requestedPostingDate);
                $postingDateShifted = true;

                // Re-validate the resolved date through the SAME real gate rather than trusting
                // earliestOpenOnOrAfter()'s own search blindly — keeps "PeriodGuard::assertOpen()
                // is the one enforcement point" true even for the shifted path. This call is
                // expected to pass (the search chose $postingDate specifically because it's open
                // or has no row), so it is not wrapped in its own try/catch: if it somehow still
                // throws, that is a genuine refusal this method must still propagate, unchanged
                // from every other step's behaviour.
                $this->periods->assertOpen(
                    $draft->companyId,
                    $postingDate,
                    $draft->allowLockedPeriods,
                    $userId,
                    $draft->overrideReason,
                );
            }

            $transaction = null;
            $serialCollisionAttempts = 0;

            // Bounded retry loop (W3-prereq lane B fix, doc 17 §3.3/§4): each iteration reserves
            // a fresh serial and attempts the header INSERT once; a serial collision (caught
            // below) increments $serialCollisionAttempts and loops back for the next serial,
            // up to self::SERIAL_COLLISION_MAX_ATTEMPTS. Every other exit from this loop body —
            // a clean insert, an idempotency-race return/throw, or the exhaustion throw above —
            // leaves the loop via `break`, `return`, or `throw`, never by falling off the end.
            while ($transaction === null) {
                $serialCollisionAttempts++;

                // ── Step 6: atomic document number, inside this same transaction ─────────────────
                [$formattedNumber, $numericValue] = $this->sequences->next(
                    $draft->docType,
                    $draft->companyId,
                    $draft->branchId,
                    $docDate
                );
                unset($numericValue); // reserved for future use; the formatted number is what we persist

                $referenceType = $this->resolveReferenceType($draft);
                // P1 FIX ROUND (BLOCKER 3): invoice_id comes ONLY from the caller-explicit
                // DocumentDraft::$invoiceId — never inferred from $referenceType/docType/sourceId. See
                // DocumentDraft's docblock and class docblock resolved-gap #3a.
                $invoiceId = $draft->invoiceId;

                // ── Step 7: write header ───────────────────────────────────────────────────────────
                $headerAttributes = [
                    'company_id' => $draft->companyId,
                    'branch_id' => $draft->branchId,
                    // Legacy polymorphic pair predates company-scoped documents and has no natural
                    // engine-side value (class docblock, resolved-gap #1). Pinned to the company itself
                    // so the NOT NULL columns are always satisfiable without inventing party semantics.
                    'entity_id' => $draft->companyId,
                    'entity_type' => 'company',
                    'transaction_type' => $draft->docType, // resolved-gap #2
                    'amount' => $totalDebit,
                    'description' => $draft->narration,
                    'invoice_id' => $invoiceId,
                    'payment_reference' => $draft->paymentReference, // W1.1 fix — see DocumentDraft's own docblock
                    'payment_id' => $draft->paymentId, // W1.2 fix (Task A) — see DocumentDraft's own docblock
                    'reference_type' => $referenceType,
                    'reference_number' => $formattedNumber,
                    'transaction_date' => $docDate,
                    // P2.5.B: the resolved, possibly-shifted period-bucketing date — see step 5's
                    // own docblock. Always set (never null on the engine path); $docDate above
                    // (transaction_date) is the one column this resolution never touches.
                    'posting_date' => $postingDate,
                    'doc_type' => $draft->docType,
                    'sub_type' => $draft->subType,
                    'doc_year' => (int) $docDate->format('Y'),
                    'posting_status' => 'posted',
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'reversal_of_transaction_id' => null,
                    'idempotency_key' => $draft->idempotencyKey,
                    'created_by' => $userId,
                    'posted_by' => $userId,
                    'posted_at' => now(),
                ];

                try {
                    $transaction = $this->createTransactionHeader($headerAttributes);
                } catch (QueryException $e) {
                    // ── W3-prereq lane B fix (doc 17 §3.3/§4): serial-number collision ──────────
                    // Engine-minted numbers from SequenceService::next() can collide with a reference_
                    // number already sitting in `transactions` under the SAME (company_id, doc_type,
                    // reference_number) tuple this INSERT just violated —
                    // `transactions_company_doctype_refnum_unique` (migration 2026_08_24_120008) —
                    // whenever accounting:seed-serial-schemas has not yet raised this (company, branch,
                    // doc_type, doc_year)'s serial_schemas.last_serial above the legacy ceiling for it.
                    // Checked FIRST, before the idempotency-key / payment-reference-type probes below:
                    // it names a DIFFERENT unique index (str_contains-matched the same way those two
                    // already disambiguate each other — see isSerialCollisionViolation()'s own
                    // docblock), so it can never be confused with either.
                    //
                    // RECOVERY: this INSERT reserved $formattedNumber via SequenceService::next() but
                    // never wrote a row with it — MySQL/InnoDB does not poison the whole transaction on
                    // a duplicate-key statement error (the same fact isIdempotencyKeyRaceViolation()'s
                    // own docblock and SequenceService::createSchemaRow() already rely on), so it is
                    // safe to keep using this connection/transaction to reserve the NEXT serial and
                    // retry the header INSERT, bounded at self::SERIAL_COLLISION_MAX_ATTEMPTS total
                    // attempts. Each collision is logged via Log::warning('accounting.serial_collision',
                    // …) before retrying, so an operator can see how often this is firing without
                    // waiting for the bounded ceiling to actually throw.
                    //
                    // NUMBERING GAP (burned, not reused — by design, matching the P1 FIX ROUND 4
                    // idempotency-race recovery path's own documented tradeoff): SequenceService has no
                    // mechanism to decrement/free a reservation once taken (see its own class
                    // docblock), so on the SUCCESS path — some attempt below MAX eventually inserts
                    // cleanly — the surrounding DB::transaction() commits, permanently burning every
                    // earlier attempt's number. A gap in the sequence is a normal, tolerated outcome
                    // (most accounting jurisdictions explicitly forbid only reuse/duplication, not
                    // gaps); a resurrected or double-issued number would not be. On EXHAUSTION (below)
                    // the surrounding DB::transaction() instead rolls back entirely — every attempt's
                    // serial_schemas reservation from THIS call unwinds with it, so nothing from a
                    // failed call is burned; the next post() attempt starts from the same pre-call
                    // last_serial and will hit the same collision until an operator reseeds
                    // (accounting:seed-serial-schemas) or the underlying legacy data is resolved.
                    if ($this->isSerialCollisionViolation($e)) {
                        if ($serialCollisionAttempts >= self::SERIAL_COLLISION_MAX_ATTEMPTS) {
                            throw new SerialCollisionExhaustedException(
                                companyId: $draft->companyId,
                                branchId: $draft->branchId,
                                docType: $draft->docType,
                                docYear: (int) $docDate->format('Y'),
                                lastAttemptedNumber: $formattedNumber,
                                attempts: $serialCollisionAttempts,
                            );
                        }

                        Log::warning('accounting.serial_collision', [
                            'company_id' => $draft->companyId,
                            'branch_id' => $draft->branchId,
                            'doc_type' => $draft->docType,
                            'doc_year' => (int) $docDate->format('Y'),
                            'attempted_number' => $formattedNumber,
                            'attempt' => $serialCollisionAttempts,
                        ]);

                        continue;
                    }

                    // ── BLOCKER 5 fix: idempotency race ───────────────────────────────────────────
                    // Step 1's SELECT only protects sequential callers. Two concurrent posts with the
                    // same idempotency key can both miss it, both proceed through resolution/locking/
                    // sequence-reservation, and then race on this INSERT — the loser hits the
                    // unique(company_id, idempotency_key) index (migration 2026_08_24_120004) and
                    // used to let a raw QueryException (SQLSTATE 23000 / driver 1062) escape to the
                    // caller instead of file 11's documented contract: "return the existing
                    // PostedDocument, do NOTHING". MySQL/InnoDB does not poison the whole transaction
                    // on a duplicate-key statement error the way Postgres does (same fact already
                    // relied on by SequenceService::createSchemaRow()), so it is safe to keep using
                    // this connection/transaction to re-query and return normally below — the caller
                    // gets a clean idempotent result instead of an exception, and by the time this
                    // catch runs the winning transaction is guaranteed already committed (an INSERT
                    // colliding on a unique index blocks until the holder commits or rolls back; a
                    // 1062 here is only possible after the holder committed).
                    //
                    // The catch is deliberately narrow (tightened further in P1 FIX ROUND 3 — see
                    // isIdempotencyKeyRaceViolation()'s own docblock): only the specific duplicate-
                    // entry driver code AND the specific unique index name, and only when this draft
                    // actually declared an idempotency key. Anything else — including a duplicate-key
                    // error under a NULL idempotency_key, which cannot legitimately happen since MySQL
                    // never treats two NULLs as equal — is rethrown untouched.
                    //
                    // W2.1 fix (residual 2 — mis-typed in W2): a 1062 here is not always the
                    // idempotency-key race the recovery block below exists for — `transactions` also
                    // carries the SECOND unique index named in this class's own W2 FIX ROUND docblock,
                    // transactions_payment_id_reference_type_unique, which this same INSERT can just as
                    // easily collide with now that $headerAttributes writes payment_id (see
                    // DocumentDraft::$paymentId). W2 treated EVERY such collision as a genuinely
                    // different document and threw DuplicatePaymentReferenceException unconditionally —
                    // but naming the OTHER unique index does not by itself rule out "this is the exact
                    // same draft being retried" (a caller re-submitting after a timeout would carry the
                    // SAME idempotencyKey AND the SAME payment_id/reference_type pair, and would collide
                    // on THIS index first purely because MySQL evaluates it before the idempotency-key
                    // index for this row's column values). W2's fix therefore made the retry case throw
                    // "a different document already occupies payment_id=X / reference_type=Y" about a
                    // request that was, in fact, its own prior attempt — a false claim.
                    //
                    // FIXED: a payment-reference-type collision now goes through the EXACT SAME
                    // same-key probe the idempotency-key race already uses below (only a real,
                    // non-null idempotencyKey lets it be probed at all — see the immediate check just
                    // below) before anything is decided. That probe answers the only question that
                    // actually distinguishes the two cases: does a document under THIS draft's exact
                    // idempotency key already exist?
                    //   - Yes, live -> this is that same draft's own prior attempt (a retry/race) ->
                    //     return the existing document, not an exception.
                    //   - Yes, but soft-deleted -> SupersededIdempotencyKeyException, exactly as the
                    //     idempotency-key race already handles that shape.
                    //   - No document at all under this key -> the row that actually collided on
                    //     (payment_id, reference_type) belongs to a DIFFERENT idempotency key, i.e. is
                    //     genuinely a different logical document -> DuplicatePaymentReferenceException,
                    //     and now truthfully so.
                    $isIdempotencyKeyRace = $draft->idempotencyKey !== null && $this->isIdempotencyKeyRaceViolation($e);
                    $isPaymentReferenceTypeRace = $this->isPaymentReferenceTypeRaceViolation($e);

                    if (! $isIdempotencyKeyRace && ! $isPaymentReferenceTypeRace) {
                        throw $e;
                    }

                    if ($isPaymentReferenceTypeRace && $draft->idempotencyKey === null) {
                        // Nothing to probe with — this draft declared no idempotency key at all, so
                        // there is no way to test "is this a retry of the same draft". A 1062 on
                        // transactions_payment_id_reference_type_unique with no idempotency key can
                        // only be a genuinely different document already occupying this slot.
                        throw new DuplicatePaymentReferenceException($draft->paymentId, $referenceType);
                    }

                    // ── P1 FIX ROUND 3 (HIGH regression, still-open half of BLOCKER 5) ─────────────
                    // WHY THIS RE-QUERY MUST BE A LOCKING READ, PROVEN STEP BY STEP:
                    //
                    // MySQL/InnoDB REPEATABLE READ assigns a transaction's consistent read-view lazily,
                    // at the first PLAIN (non-locking) read the transaction performs — not at
                    // BEGIN — and every later plain read in that same transaction reuses that SAME
                    // view, seeing none of what committed after it was taken. This transaction's first
                    // plain read is step 1's own findByIdempotencyKey() call above (a non-locking
                    // SELECT), executed before any lock is taken — call that moment T0, and the
                    // snapshot it fixes S0.
                    //
                    // Two callers, A and B, share an idempotency key: both run step 1 at ~T0 and both
                    // miss (S0 predates either row existing). A reaches its header INSERT first and
                    // commits at T1 > T0. B's own header INSERT then hits 1062 at T2 > T1, landing in
                    // this catch block at T3 > T2 — so A's row is DEFINITELY committed by the time this
                    // code runs (the reasoning two paragraphs up already establishes that). But B's
                    // transaction is STILL SNAPSHOTTED AT S0 (fixed at T0, before A even started) —
                    // step 2b's lockForUpdate() on the accounts rows is a locking read on accounts, and
                    // only advances what B can see of ACCOUNTS rows, not transactions rows, and does
                    // not replace B's snapshot for its own future plain reads. So a PLAIN re-query here
                    // — i.e. findByIdempotencyKey($companyId, $key) with the default $forUpdate =
                    // false — would still be served from S0 and CANNOT see A's row, no matter how long
                    // after T1 it runs: $existing comes back null, the "should be unreachable" branch
                    // below fires, and the original QueryException escapes anyway. That was the exact
                    // regression: the recovery path existed but could not see the row it was recovering.
                    //
                    // THE FIX: request a LOCKING read (SELECT ... FOR UPDATE) instead. InnoDB defines a
                    // locking read under REPEATABLE READ to read the LATEST COMMITTED version of each
                    // row it examines, not the transaction's snapshot — this is what lets `SELECT ...
                    // FOR UPDATE` inside a long-lived REPEATABLE READ transaction always see the most
                    // recent committed state (it is the same primitive step 2b already relies on to
                    // read committed account balances). A's row is committed by T3 (proven above), so a
                    // locking read at T3 reads it directly regardless of B's S0 snapshot. There is no
                    // wait/deadlock risk from locking here either: MySQL only reports 1062 AFTER
                    // confirming the colliding row is committed (an uncommitted colliding insert would
                    // instead have made B's own INSERT block until the holder resolved), so the row
                    // this lock targets can never be held by a still-open transaction at this point.
                    $existing = $this->findByIdempotencyKey($draft->companyId, $draft->idempotencyKey, forUpdate: true);

                    if ($existing !== null) {
                        // ── P1 FIX ROUND 4 (LOW finding — documented, not fixed; see this class's own
                        //    "P1 FIX ROUND 4" docblock index for why neither available fix is clean) ──
                        // This is the genuine concurrent-race recovery path: $existing is a LIVE (not
                        // soft-deleted — findByIdempotencyKey()'s default deleted_at exclusion just
                        // confirmed that) transaction some concurrent caller (the "winner") already
                        // committed under this exact key. Returning it here is correct per file 11's own
                        // contract ("return the existing PostedDocument, do NOTHING") and BLOCKER 5's
                        // fix — untouched by this round. But by the time execution reaches this line,
                        // THIS caller (the "loser") has already run step 6 above and reserved a real
                        // document number via SequenceService::next(), which performs its reservation as
                        // an UPDATE against serial_schemas.last_serial WITHIN this same transaction.
                        // Because this method returns NORMALLY from here (no exception), the surrounding
                        // DB::transaction() call COMMITS — including that UPDATE — even though this
                        // specific call never inserts a transaction header of its own. The number that
                        // UPDATE reserved is therefore permanently unusable by any future post() for this
                        // (company, branch, doc_type, doc_year): a real, silent gap in the numbering
                        // sequence.
                        //
                        // Left undone deliberately, not overlooked: the two fixes that would close it
                        // are both worse than the gap itself. (a) Reserving the serial AFTER the header
                        // INSERT succeeds is not available here — $formattedNumber, which step 6
                        // produces, is itself one of the values step 7's INSERT writes
                        // (reference_number), so the reservation cannot be moved after the very
                        // statement that needs its output without a much larger restructure of this
                        // pipeline. (b) Releasing/decrementing the reservation on this race path cannot
                        // be proven safe under real concurrency: by the time this code runs, a THIRD
                        // caller may already have reserved the NEXT number past this one; decrementing
                        // here would hand that same number out twice. A gap in a document-numbering
                        // sequence is a normal, widely accepted outcome (most accounting jurisdictions
                        // explicitly tolerate gaps and forbid only duplicates/reuse — the property
                        // SequenceService actually guarantees); a resurrected or double-issued number
                        // would not be.
                        return $this->toPostedDocument($existing);
                    }

                    // The deleted_at-EXCLUDING lookup just above found nothing under this exact
                    // (company_id, idempotency_key) pair. For an idempotency-key race, the unique index
                    // this INSERT collided with IS keyed on precisely that pair — so a row with this key
                    // DOES exist, and (since the exclusion-aware lookup already missed it) it can only
                    // be soft-deleted. The unique index itself is deleted_at-BLIND (soft-deleting a
                    // transaction does not free its idempotency_key for reuse — the row still physically
                    // occupies the index), which is exactly why findByIdempotencyKey()'s own exclusion
                    // can legitimately miss a row the index just fired on. Re-queried explicitly below
                    // (not merely inferred) — same locking-read reasoning as the call above (REPEATABLE
                    // READ; see that call's own comment), since this fallback is exactly as reachable
                    // under the A/B race as the deleted_at-exclusive one just was.
                    //
                    // For a payment-reference-type race (residual 2 fix), this same-key lookup is a
                    // genuine PROBE, not a proof: the index that actually fired
                    // (transactions_payment_id_reference_type_unique) is keyed on payment_id/
                    // reference_type, NOT on idempotency_key, so a row colliding on IT need not carry
                    // this draft's idempotency key at all — finding nothing here is the ordinary,
                    // expected outcome for a genuinely different document, handled just below.
                    $existing = Transaction::withoutGlobalScopes()
                        ->where('company_id', $draft->companyId)
                        ->where('idempotency_key', $draft->idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing === null) {
                        if ($isPaymentReferenceTypeRace) {
                            // No document at all exists under THIS draft's exact idempotency key, live
                            // or dead — so the row that collided on (payment_id, reference_type) belongs
                            // to a different idempotency key entirely: a genuinely different logical
                            // document, not a retry of this one. This message is now TRUE: a different
                            // document really does already occupy this payment_id/reference_type pair.
                            throw new DuplicatePaymentReferenceException($draft->paymentId, $referenceType);
                        }

                        // Should be unreachable for a pure idempotency-key race — the unique index just
                        // fired on this exact (company_id, idempotency_key) pair; even the
                        // deleted_at-blind fallback found nothing. Never silently swallow a write
                        // failure this deep — surface the original error.
                        throw $e;
                    }

                    if ($existing->deleted_at === null) {
                        // A live row under this exact key exists after all. For a pure idempotency-key
                        // race this cannot happen given the proof above (a live row would have been
                        // caught by the deleted_at-excluding lookup first). For a payment-reference-type
                        // race this branch is reachable only if that live row is ALSO the one that
                        // collided on the other index — i.e. this really was the same draft being
                        // retried — but the deleted_at-excluding lookup two calls above already returns
                        // exactly that row when it exists, so execution would have returned there first.
                        // Either way, this is not the soft-delete-collision case handled below; surface
                        // the original driver error rather than silently mis-handling an unproven shape.
                        throw $e;
                    }

                    // ── P1 FIX ROUND 4 (BLOCKING #2 fix — .planning/P1-VERIFICATION-FINDINGS.json;
                    //    owner decision: THROW a clear named exception, AND make it visible to an
                    //    admin) ────────────────────────────────────────────────────────────────────
                    // Before this fix: execution reached here and did `return
                    // $this->toPostedDocument($existing);` — handing back the DEAD transaction's own
                    // stale document as if it were a fresh, successful post of THIS attempt. Nothing
                    // was written to journal_entries, and step 6's document-number reservation still
                    // committed (this method returns normally, same mechanism as the LOW finding
                    // documented in the branch above — except THIS path is fixed, not documented,
                    // because throwing instead of returning is exactly what closes it: an exception
                    // propagating out of this DB::transaction() closure rolls back EVERYTHING it did on
                    // this call, including step 6's UPDATE, so no number is burned here).
                    //
                    // Recorded BEFORE throwing, on a connection that survives the rollback the throw
                    // is about to trigger — see App\Models\IdempotencyKeyRejection's own docblock for
                    // exactly why a same-connection write would not survive it.
                    $adminRecord = $this->recordSupersededIdempotencyKeyRejection(
                        $draft,
                        $existing,
                        $totalDebit,
                        $userId
                    );

                    throw new SupersededIdempotencyKeyException(
                        companyId: $draft->companyId,
                        idempotencyKey: $draft->idempotencyKey,
                        deadTransactionId: (int) $existing->id,
                        deadTransactionDeletedAt: $existing->deleted_at,
                        attemptedAmount: $totalDebit,
                        adminRecordId: $adminRecord?->id,
                    );
                }
            }

            // P2.5.B: log the shift decided at step 5, now that $transaction->id exists to name
            // (p2_5-brief.md §P2.5.B: "Log accounting.posting_date_shifted with both dates + doc
            // id"). Deliberately logged here rather than at step 5 itself — that is BEFORE the
            // header row exists, so there is no transaction id yet to attach to the log line.
            if ($postingDateShifted) {
                // Kept as the ORIGINAL, unchanged Log::info() call — PostingSeamPeriodGuardTest
                // pins this exact facade call under Log::spy(); see PeriodGuard's identical note
                // at its own period_locked_override call site for why this must stay Log::info()
                // rather than route through AccountingLog::event(). The DB row is written by the
                // separate AccountingLog::write() call directly below.
                Log::info('accounting.posting_date_shifted', [
                    'company_id' => $draft->companyId,
                    'transaction_id' => $transaction->id,
                    'reference_number' => $formattedNumber,
                    'doc_type' => $draft->docType,
                    'transaction_date' => $docDate->toDateString(),
                    'requested_posting_date' => $requestedPostingDate->toDateString(),
                    'resolved_posting_date' => $postingDate->toDateString(),
                ]);

                AccountingLog::write(
                    action: 'posting_date_shifted',
                    companyId: $draft->companyId,
                    subjectType: 'transaction',
                    subjectId: (int) $transaction->id,
                    transactionId: (int) $transaction->id,
                    after: [
                        'reference_number' => $formattedNumber,
                        'doc_type' => $draft->docType,
                        'transaction_date' => $docDate->toDateString(),
                        'requested_posting_date' => $requestedPostingDate->toDateString(),
                        'resolved_posting_date' => $postingDate->toDateString(),
                    ],
                    postingPeriod: $postingDate,
                );
            }

            // ── Step 8: write each JournalEntry line ──────────────────────────────────────────
            $lines = [];
            foreach ($resolved as $r) {
                /** @var LineDraft $line */
                $line = $r['line'];
                /** @var Account $account */
                $account = $r['account'];

                $lines[] = JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $draft->companyId,
                    'account_id' => $account->id,
                    // P1 FIX ROUND 3: journal_entries.branch_id is the one column in this
                    // pipeline that structurally cannot hold the "0 = no branch" sentinel
                    // (branches.id starts at 1) now that its FK to branches is enforced on
                    // any non-NULL value (migration
                    // 2026_08_24_120006_make_journal_entries_branch_id_nullable). Translate
                    // the sentinel to a real NULL only at this write site — transactions.branch_id
                    // and reverse()'s draft construction keep the 0-sentinel convention as-is,
                    // since neither has an FK to violate.
                    'branch_id' => $draft->branchId > 0 ? $draft->branchId : null,
                    // W1.1 fix (M3/C5 — line attribution): the same party/document attribution
                    // legacy JournalEntry::create() call sites always wrote, now carried on
                    // LineDraft itself — see that class's own docblock for the full contract and
                    // the fallback each field falls back to when a feeder doesn't set it (never a
                    // crash: every one of these columns is nullable).
                    'invoice_id' => $line->invoiceId,
                    'invoice_detail_id' => $line->invoiceDetailId,
                    'task_id' => $line->taskId,
                    'type_reference_id' => $line->partyAccountRef,
                    'transaction_date' => $docDate,
                    // P2.5.B: same resolved date as the header's own posting_date — see step 5's
                    // docblock. journal_entries and transactions carry the same posting_date for a
                    // single document by construction (both come from this one $postingDate local).
                    'posting_date' => $postingDate,
                    'description' => $this->truncate($line->description ?? $draft->narration),
                    'debit' => $r['debit'],
                    'credit' => $r['credit'],
                    'balance' => null, // resolved-gap #6 — never read-modify-write
                    'voucher_number' => $this->truncate($line->voucherNumber ?? $formattedNumber),
                    'name' => $this->truncate($line->partyName ?? $account->name),
                    // W1.1 fix (M3/C5): $ledgerType is the LEGACY report-vocabulary category
                    // (AccountingController/BankPaymentController filter on it); $transactionType
                    // remains this engine's own audit-label vocabulary. See LineDraft's own
                    // docblock for why these are deliberately two separate fields, not one.
                    'type' => $line->ledgerType ?? $line->transactionType,
                    'currency' => $line->currency,
                    'exchange_rate' => $line->exchangeRate,
                    'amount' => $r['amount'],
                    // W5.P fix: was hardcoded 0 -- see LineDraft's own "W5.P FIX ROUND" docblock.
                    // Falls back to 0 for every feeder that doesn't set this (unchanged behaviour).
                    'reconciled' => $line->reconciled ?? 0,
                    'original_currency' => $line->currency,
                    'original_amount' => $r['originalAmount'],
                    // W5.L fix — see LineDraft's own "W5.L FIX ROUND" docblock for the full
                    // rationale, the one documented cheque_date behaviour change, and the exact
                    // column widths truncateNullable() below guards against.
                    'cheque_no' => $this->truncateNullable($line->chequeNo, 100),
                    'cheque_date' => $line->chequeDate,
                    'cheque_clearance_date' => $line->chequeClearanceDate,
                    'bank_info' => $this->truncateNullable($line->bankInfo, 200),
                    'auth_no' => $this->truncateNullable($line->authNo, 100),
                ]);
            }

            // ── Step 9: actual_balance — INTENTIONALLY NOT MAINTAINED (BLOCKER 4 fix) ─────────
            // The original build issued `DB::table('accounts')->increment('actual_balance',
            // $delta)` here with $delta rounded to 3dp (moneyDecimals). accounts.actual_balance is
            // decimal(10,2) — never widened, and widening it is explicitly out of scope for this
            // fix round — so MySQL silently truncates the 3rd decimal on every store. Over many
            // fils-level postings this drifts the column permanently and invisibly (TrialBalance
            // Service recomputes from journal_entries, so nothing else would ever surface the
            // drift). Rather than ship a "3dp engine writing a 2dp column", this engine maintains
            // NO legacy balance column while the flag is off: legacy code paths that already read/
            // write actual_balance are completely untouched (mission scope rule 3 — no legacy
            // call-site refactor in this round), and TrialBalanceService remains the single
            // verified-correct source of account balances. actual_balance maintenance by this
            // engine returns only after the money-column widening migration lands and the
            // increment can be written at the column's real scale.

            // ── Step 10 ────────────────────────────────────────────────────────────────────────
            // P2.5.F writer (a) (p2_5-brief.md §P2.5.F): "PostingService::post/reverse/repost
            // write one row per document with transaction_id + posting_period." One row here, per
            // NEW header actually inserted — the idempotent short-circuit at step 1 above returns
            // before reaching this point, so a retried post() with the same idempotencyKey does not
            // burn a second audit row for the same document. repost() gets its own two rows for
            // free: it calls reverse() (which writes its own 'reverse' row below) then this same
            // post() for the replacement (which writes a second 'post' row here) — no separate hook
            // is needed in repost() itself.
            AccountingLog::write(
                action: 'post',
                companyId: $draft->companyId,
                subjectType: 'transaction',
                subjectId: (int) $transaction->id,
                transactionId: (int) $transaction->id,
                after: [
                    'doc_type' => $draft->docType,
                    'sub_type' => $draft->subType,
                    'reference_number' => $formattedNumber,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'transaction_date' => $docDate->toDateString(),
                    'posting_date' => $postingDate->toDateString(),
                ],
                actorId: $userId,
                postingPeriod: $postingDate,
            );

            return new PostedDocument($transaction, $lines, $formattedNumber);
            // NOTE (P1 fix round 3, LOW finding — see TRANSACTION_RETRY_ATTEMPTS's own docblock):
            // this retry is fully live when post() is called directly (the normal case today).
            // When post() is reached via reverse()'s internal call instead, this specific retry is
            // inert (Laravel does not retry a nested/savepoint transaction) — reverse()'s own outer
            // retry covers that case by re-running the whole callback.
            // W1.2 correction (salary feeder round): a caller MAY wrap post() in its own
            // DB::transaction() — Laravel opens this call as a SAVEPOINT rather than a true nested
            // transaction, so it still commits/rolls back atomically with the caller's own writes.
            // The one cost is that this specific retry becomes inert for that call, with nothing
            // else in the chain to compensate a deadlock/serialization error — acceptable for a
            // synchronous UI request with no other retry path (see AgentController::update()),
            // but callers with real concurrent-write contention should prefer calling post()
            // directly (or via PostingSeam without an outer transaction) so this retry stays live.
        }, self::TRANSACTION_RETRY_ATTEMPTS);
    }

    /**
     * Reverse a posted document as a NEW dated document (never mutate/delete posted lines).
     * Idempotent: reversing an already-reversed transaction returns the existing reversal.
     * Refuses (throws ProtectedLineException) when any original line is reconciled, unless
     * $force = true.
     *
     * @throws NonCanonicalJournalLineException when an original line's debit/credit shape isn't
     *                                          the canonical "exactly one strictly positive, neither negative" this method's
     *                                          side-inference requires (P1 fix round, HIGH finding).
     */
    public function reverse(
        Transaction $posted,
        \DateTimeInterface $reversalDate,
        ?int $userId,
        bool $force = false
    ): PostedDocument {
        return DB::transaction(function () use ($posted, $reversalDate, $userId, $force) {
            // P1 FIX ROUND (HIGH soft-delete finding): withoutGlobalScopes() drops
            // SoftDeletingScope along with every other scope — exclude deleted_at explicitly so a
            // soft-deleted transaction cannot be reversed as if it were live.
            /** @var Transaction $posted */
            $posted = Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->findOrFail($posted->getKey());

            // Idempotent: a transaction is only ever reversed once. P1 FIX ROUND (LOW finding):
            // exclude soft-deleted reversal rows too, so a dead reversal can't block a legitimate
            // new one. Whether a REV document may itself be the $posted being reversed here
            // (REV-of-REV) is DELIBERATELY left unrestricted — file 11's prose is genuinely
            // ambiguous between "refuse if already has a reversal" (enforced below) and "refuse if
            // this IS a reversal" (not enforced); recorded as a conscious decision, not a gap.
            $existingReversal = Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('reversal_of_transaction_id', $posted->id)
                ->first();
            if ($existingReversal !== null) {
                return $this->toPostedDocument($existingReversal);
            }

            // P1 FIX ROUND (HIGH soft-delete finding): a legacy document whose one leg was
            // soft-deleted by the delete-and-recreate edit path (R4.1 — the exact corruption P3
            // must repair) must not have that dead leg resurrected into the reversal.
            $originalLines = JournalEntry::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('transaction_id', $posted->id)
                ->get();

            if ($originalLines->isEmpty()) {
                throw new UnbalancedDocumentException(
                    0.0,
                    0.0,
                    "Cannot reverse transaction #{$posted->id}: it has no journal entry lines."
                );
            }

            if (! $force) {
                foreach ($originalLines as $original) {
                    if ((int) ($original->reconciled ?? 0) !== 0) {
                        throw new ProtectedLineException($posted->id, $original->id);
                    }
                }
            }

            $swappedLines = [];
            foreach ($originalLines as $original) {
                $originalDebit = (float) $original->debit;
                $originalCredit = (float) $original->credit;
                $debitIsPositive = $originalDebit > 0;
                $creditIsPositive = $originalCredit > 0;

                // P1 FIX ROUND (HIGH finding): legacy-shape guard. The side-inference below only
                // knows how to swap a structurally sound line — exactly one of debit/credit
                // strictly positive, neither negative. Sign-error rows (verification check 8.1,
                // e.g. JE 15201/15202 both debit = -101.160) or rows with both legs non-zero do
                // not fit that shape; silently treating them as if they did either throws an
                // unrelated, confusing exception downstream or drops a leg without netting the
                // original to zero. Fail loudly and name the exact row instead, so a caller (P3's
                // dedicated repair command) knows to route it through its own correction path
                // rather than plain reverse().
                if ($originalDebit < 0 || $originalCredit < 0 || $debitIsPositive === $creditIsPositive) {
                    throw new NonCanonicalJournalLineException(
                        $posted->id,
                        $original->id,
                        $originalDebit,
                        $originalCredit
                    );
                }

                $magnitude = $debitIsPositive ? $originalDebit : $originalCredit;

                // P1 FIX ROUND 3 (MEDIUM finding: new FC-consistency rule broke reversal of
                // ordinary legacy foreign-currency lines).
                //
                // First, the direction convention itself — CHECKED AGAINST A REAL FEEDER, not
                // assumed. post()'s step 3f (added last round) requires amount ≈ originalAmount ×
                // exchangeRate for a non-base-currency line. The app's one real dual-currency
                // conversion path is App\Http\Traits\CurrencyExchangeTrait::convert(), used by
                // PaymentController's advanced payment-link flow
                // (PaymentController::generatePaymentLink(), the `$isAdvancedMode` branch):
                // `$conversionResult = $this->convert($companyId, strtoupper($item['currency']),
                // 'KWD', $item['extended_amount'])`, i.e. convert(from: FC, to: KWD, amount: the FC
                // figure), and the trait itself returns `'converted_amount' =>
                // round($amount * $exchangeRate, 3)` where `$exchangeRate =
                // getExchangeRate($companyId, $fromCurrency=FC, $toCurrency=KWD)`. That is exactly
                // base(KWD) = original(FC) × exchangeRate, i.e. exchangeRate is base-units-per-one-
                // FC-unit, looked up FC→KWD — the SAME convention step 3f already enforces. The
                // direction was correct; it did not need fixing.
                //
                // What actually broke reversal was TRUST, not direction. journal_entries.currency,
                // .exchange_rate and .original_amount are frequently populated INDEPENDENTLY of
                // each other and of debit/credit by different legacy call sites — e.g.
                // InvoiceController's receivable/revenue entries (InvoiceController.php ~L1512,
                // ~L1580 and others) stamp `currency` from the task and `exchange_rate` from the
                // task's own `exchange_rate` column, but never set `original_amount` at all
                // (journal_entries.original_amount is nullable with NO db-level default —
                // migration 2025_08_02_170959 — so it is simply NULL on that row), while
                // `debit`/`credit`/`amount` always hold the KWD figure ($selling) regardless of the
                // currency tag. The OLD reconstruction below used three INDEPENDENT `?:`/`??`
                // fallbacks: a real (non-KWD) currency tag survived untouched, a missing
                // original_amount fell back to $magnitude (the KWD debit/credit — NOT a real FC
                // figure), and a real nonzero task exchange_rate survived untouched too. Put
                // together, that triple actively FAILS step 3f's own identity (expectedAmount =
                // magnitude × task_rate ≠ magnitude unless the task's rate happens to be 1.0) —
                // an ordinary line, correctly posted under the pre-P1 rules, refused reversal
                // under a validation rule invented after it was written. Exactly the regression.
                //
                // THE FIX does not touch step 3f (it is correct and must stay strict for every NEW
                // document post() validates going forward) or invent a "relaxed mode" flag through
                // it (this class's one keystone rule — "exactly one code path may write
                // journal_entries" — means reverse() has no other legal way to reach
                // journal_entries than through this same post() call). Instead, this reconstruction
                // is made SELF-CONSISTENT BY CONSTRUCTION, so it can never trip step 3f regardless
                // of how incompletely a given legacy write site populated the three columns:
                //   - A line only carries a genuinely reconstructible FC figure when its `currency`
                //     is actually a non-base tag AND its `original_amount` is actually populated
                //     and strictly positive — that is the one fact a row can assert about itself
                //     unambiguously (a present, positive original_amount really was written by
                //     something that meant it to be an FC amount; a NULL/0 one, whatever its
                //     currency tag says, was not).
                //   - When both hold, `exchangeRate` is DERIVED as magnitude ÷ original_amount —
                //     never taken from the stored exchange_rate column — which makes the identity
                //     hold EXACTLY (up to float rounding, far inside step 3f's tolerance) no matter
                //     whether that stored column was ever kept consistent with amount/
                //     original_amount by whichever legacy path wrote it (per the InvoiceController
                //     example above, it demonstrably was not).
                //   - When either fact is missing, there is no real FC figure to reconstruct at
                //     all; the line is booked as what its debit/credit unambiguously already is — a
                //     base-currency amount — exactly matching step 3f's own base-currency identity
                //     (originalAmount === amount, exchangeRate === 1.0), rather than tagging it with
                //     a currency for which no real original amount was ever recorded.
                $rawCurrency = strtoupper(trim((string) ($original->currency ?? '')));
                $rawOriginalAmount = (float) ($original->original_amount ?? 0.0);
                $hasGenuineFcAmount = $rawCurrency !== ''
                    && $rawCurrency !== strtoupper($this->baseCurrency)
                    && $rawOriginalAmount > 0.0;

                if ($hasGenuineFcAmount) {
                    $lineCurrency = trim((string) $original->currency);
                    $lineOriginalAmount = $rawOriginalAmount;
                    $lineExchangeRate = $magnitude / $rawOriginalAmount;
                } else {
                    $lineCurrency = $this->baseCurrency;
                    $lineOriginalAmount = $magnitude;
                    $lineExchangeRate = 1.0;
                }

                $swappedLines[] = new LineDraft(
                    purposeCode: '', // explicit accountId path is always used for reversals
                    accountId: (int) $original->account_id,
                    side: $debitIsPositive ? 'credit' : 'debit',
                    amount: $magnitude,
                    currency: $lineCurrency,
                    originalAmount: $lineOriginalAmount,
                    exchangeRate: $lineExchangeRate,
                    transactionType: $original->type,
                    // W1.1 fix (M3/C5 — line attribution): a reversal used to always write
                    // partyAccountRef: null here, silently dropping type_reference_id (and every
                    // other attribution column) even when the ORIGINAL line being reversed had it
                    // — the exact same defect this fix round closes for feeders, reproduced one
                    // level up. Carried over verbatim from $original so a reversal stays findable
                    // on the same per-client/supplier/agent ledger filter
                    // (AccountingController::filterLedgers()) and receipt-voucher screen as the
                    // document it reverses. $original->type already flows into the reversal's own
                    // `type` column via $transactionType above (ledgerType null -> falls back to
                    // transactionType), so no separate ledgerType is needed here.
                    partyAccountRef: $original->type_reference_id,
                    description: 'Reversal of: '.($original->description ?? $posted->description ?? ''),
                    invoiceId: $original->invoice_id,
                    invoiceDetailId: $original->invoice_detail_id,
                    taskId: $original->task_id,
                    partyName: $original->name,
                    // W5.L fix: same M3/C5 line-attribution reasoning as partyAccountRef/partyName
                    // above, applied to the instrument-identity fields — a reversal that silently
                    // dropped which cheque/bank ref/auth code the ORIGINAL line carried would break
                    // the exact same "findable on the same document" audit trail those fixes exist
                    // for. cheque_date is carried too (it identifies WHICH cheque, same as
                    // cheque_no) — but chequeClearanceDate deliberately is NOT: clearance is a
                    // state of a specific instance in time, not audit-trail identity, and a
                    // reversal does not itself clear or un-clear anything; W5.R's own bounce flow
                    // builds its own explicit LineDraft for that state change.
                    chequeNo: $original->cheque_no,
                    // JournalEntry casts neither cheque_date column as a Carbon/date (only
                    // is_locked/locked_at are cast — see that model's own $casts) — the raw
                    // Eloquent attribute here is a plain string (or null) exactly as PDO returned
                    // it, never a DateTimeInterface, so LineDraft::$chequeDate's strict type must
                    // be satisfied explicitly rather than passed straight through.
                    chequeDate: $original->cheque_date !== null ? Carbon::parse($original->cheque_date) : null,
                    bankInfo: $original->bank_info,
                    authNo: $original->auth_no,
                );
            }

            $reversalDraft = new DocumentDraft(
                companyId: (int) $posted->company_id,
                // NOTE: legacy rows may carry a NULL branch_id; (int) null = 0 in that edge case.
                // Full legacy-data quality repair is P3's job, not P1's.
                branchId: (int) $posted->branch_id,
                docType: 'REV',
                subType: $posted->doc_type,
                docDate: $reversalDate,
                narration: 'Reversal of transaction #'.$posted->id
                    .($posted->reference_number ? " ({$posted->reference_number})" : ''),
                lines: $swappedLines,
                idempotencyKey: 'rev:'.$posted->id,
                sourceType: $posted->reference_type,
                sourceId: $posted->invoice_id ?? $posted->id,
                // P1 FIX ROUND (BLOCKER 3): propagate the ORIGINAL's actual invoice_id, if any —
                // never invent one from sourceId/$posted->id. A reversal of a non-invoice document
                // must not become invoice-linked just because reverse() needed something to put in
                // sourceId for the audit trail above.
                invoiceId: $posted->invoice_id,
                userId: $userId,
                costCenterId: null,
            );

            $result = $this->post($reversalDraft, $userId);

            // P1 FIX ROUND (MEDIUM finding): stamp posting_status = 'reversed' on the ORIGINAL
            // too — previously only the new reversal row was ever touched, leaving the
            // migration's 'reversed' enum value permanently dead and forcing every downstream
            // "was this reversed?" check to do a reverse join instead of reading the row directly.
            Transaction::withoutGlobalScopes()
                ->whereKey($result->transaction->id)
                ->update(['reversal_of_transaction_id' => $posted->id]);
            $result->transaction->setAttribute('reversal_of_transaction_id', $posted->id);

            Transaction::withoutGlobalScopes()
                ->whereKey($posted->id)
                ->update(['posting_status' => 'reversed']);
            $posted->setAttribute('posting_status', 'reversed');

            // P2.5.F writer (a): one 'reverse' row naming BOTH the original document (subject) and
            // the new reversal document it produced (transaction_id) — distinct from the 'post' row
            // written inside post() above for the reversal document itself (that row records "this
            // document was posted"; this one records "this document was reversed, and here is what
            // reversed it"). The idempotent short-circuit a few lines up (existing reversal already
            // found) returns before reaching here, so a retried reverse() never double-writes.
            AccountingLog::write(
                action: 'reverse',
                companyId: (int) $posted->company_id,
                subjectType: 'transaction',
                subjectId: (int) $posted->id,
                transactionId: (int) $result->transaction->id,
                before: [
                    'doc_type' => $posted->doc_type,
                    'reference_number' => $posted->reference_number,
                    'posting_status' => 'posted',
                ],
                after: [
                    'posting_status' => 'reversed',
                    'reversal_transaction_id' => (int) $result->transaction->id,
                    'reversal_reference_number' => $result->transaction->reference_number,
                    'forced' => $force,
                ],
                actorId: $userId,
                postingPeriod: Carbon::instance($reversalDate),
            );

            return $result;
            // NOTE (P1 fix round 3, LOW finding — see TRANSACTION_RETRY_ATTEMPTS's own docblock):
            // live when reverse() is called directly; inert when reached via repost()'s internal
            // call (repost()'s own outer retry covers that case). The internal call this method
            // itself makes to post() just above is, symmetrically, ALSO nested from post()'s point
            // of view — this method's retry, not post()'s own, is what covers a deadlock raised
            // during that inner post() call when reverse() is the top-level entry point. Callers
            // must not wrap reverse() in their own DB::transaction() for the same reason as post().
        }, self::TRANSACTION_RETRY_ATTEMPTS);
    }

    /**
     * Reverse-then-apply: reverse($old) then post($new) in one transaction.
     *
     * File 11's repost() signature has no $force parameter, so the internal reverse() call is made
     * with $force = false — repost() therefore inherits the same reconciled-line protection a bare
     * reverse() has; a caller that genuinely needs to override it must call reverse()/post()
     * separately with an explicit $force = true.
     *
     * THE RESULTING CHAIN, PRECISELY (residual 7 correction — see this class's own W2 FIX ROUND
     * docblock section for the full false-claim-and-fix history): a successful repost() leaves
     * THREE independent rows with NO transitive column chain linking them:
     *   $old (posting_status='reversed', payment_id UNCHANGED, reversal_of_transaction_id=NULL)
     *   -> the reversal (reversal_of_transaction_id=$old->id, payment_id=NULL,
     *      idempotency_key='rev:'.$old->id)
     *   -> the replacement, i.e. this call's return value (reversal_of_transaction_id=NULL,
     *      payment_id=NULL, idempotency_key = $new's own key, suffixed with
     *      ':repost:'.$old->id only when it collided with $old's — see the KEY CONVENTION below).
     * The replacement is reachable ONLY by that idempotency key. It is NOT the target of
     * `reversal_of_transaction_id` (that column, on the reversal, points at $old — never at the
     * replacement, and nothing points at the replacement at all) and it does NOT carry `payment_id`
     * (forced NULL below). A caller that needs to find "the current live document for payment P"
     * after a repost cannot do it via `payment_id` or `reversal_of_transaction_id` — only $old
     * still carries payment_id P, and $old is the DEAD half of the chain once reposted.
     *
     * KEY CONVENTION (P1 FIX ROUND 3, MEDIUM finding — round-2's fix left this broken): the NATURAL
     * way a caller builds $new is to reuse $old's own idempotencyKey verbatim — same payment id,
     * same task id, same gateway reference — because repost() exists precisely for "this is still
     * the same real-world document, just corrected", not a different document. But reverse() above
     * does NOT clear or change $old->idempotency_key when it flips $old to posting_status =
     * 'reversed'; the row keeps occupying that exact (company_id, idempotency_key) slot. BLOCKER
     * 5's fix made findByIdempotencyKey() stop filtering on posting_status, matching what the DB
     * unique index itself enforces — so, without this fix, $this->post($new, ...) below would hit
     * its own step 1, find $old (now reversed) sitting under that identical key, and hand it right
     * back as if it were the freshly-posted replacement. The reversal happens for real; the
     * replacement silently never gets posted; and the caller cannot tell from the return value
     * alone, since a PostedDocument came back with no exception.
     *
     * THE CONVENTION: when — and only when — $new->idempotencyKey is non-null and identical to
     * $old->idempotency_key, repost() suffixes it with ":repost:{$old->id}" before calling post().
     * This is defined and enforced HERE (file 11 names no convention for this case at all) rather
     * than left to callers, because it must hold for every repost() call site uniformly for the
     * fix to be reliable. Consequences, all intentional:
     *   - The replacement's effective key can never collide with $old's original key (still held by
     *     the now-reversed $old row) or with reverse()'s own 'rev:{$old->id}' key (a disjoint
     *     prefix), so post()'s step 1 can never again mistake one for the other.
     *   - The DERIVATION itself is stable/deterministic across retries: same $old->id + same
     *     caller-supplied $new->idempotencyKey always produce the same suffixed key.
     *   - It stays distinct across two unrelated reposts that happen to reuse the same natural key
     *     for two different original documents, since {$old->id} is unique per original.
     * A $new with a DIFFERENT (or null) idempotencyKey from $old's is left completely untouched —
     * this rewrites only the one case that would otherwise silently swallow the replacement.
     *
     * RETRY LIMITATION (P1 FIX ROUND 4, MEDIUM finding — CORRECTED DOCBLOCK, NOT a behaviour
     * change; the paragraph this replaces used to claim "a caller that retries a failed/ambiguous
     * repost ... still gets ordinary post()-level idempotency on the replacement: the second
     * attempt returns the first attempt's replacement document rather than double-posting." That
     * claim is FALSE for the realistic retry shape, and is corrected here rather than made true —
     * see the reasoning below for why a behaviour change was judged out of scope for a MEDIUM
     * finding this round):
     *
     * The key-suffix derivation above really is deterministic in isolation, but repost()'s OWN
     * precondition guard, a few lines below in the method body — `if ($old->posting_status !==
     * 'posted') throw InvalidRepostSourceException(...)` — reads `$old->posting_status` directly
     * off the `$old` model instance the CALLER passed in, with no fresh reload from the database.
     * A first, successful repost($old, $new, ...) call flips that row's posting_status to
     * 'reversed' (inside reverse(), which this method calls). The realistic way a caller retries
     * after a timeout, crash, or ambiguous response — re-fetching `$old` fresh by id before calling
     * repost() again, which is how a new HTTP request, a re-run queue job, or a gateway callback
     * retry naturally behaves — hands repost() an `$old` whose posting_status is ALREADY
     * 'reversed'. repost()'s guard fires on THAT, before reverse() or post() ever run, and the
     * retry gets InvalidRepostSourceException (postingStatus: 'reversed') instead of the first
     * attempt's replacement PostedDocument.
     *
     * The deterministic key suffix would only ever be exercised on a retry in the narrower case of
     * a caller reusing the EXACT SAME still-in-PHP-memory `$old` object from the first attempt
     * (whose in-process `posting_status` attribute was never told about the UPDATE the first call
     * made) — not a safe precondition to rely on for "a gateway callback retry", which is typically
     * a fresh process with no such object to reuse. repost() is therefore NOT safely
     * retry-idempotent for the realistic caller shape today. A caller that wants that property must
     * catch InvalidRepostSourceException on retry and treat postingStatus === 'reversed' as "this
     * may already have been reposted" — e.g. by looking up a transaction whose idempotency_key
     * equals $new->idempotencyKey.':repost:'.$old->id (the same derivation above, computable by the
     * caller too) — rather than assuming a bare repost() retry will hand the replacement back.
     * Making the guard itself retry-aware (e.g. tolerating an already-'reversed' $old when a
     * matching suffixed-key transaction already exists) would close this gap for real, but is a
     * behaviour change with its own edge cases (distinguishing "reversed by OUR prior retry" from
     * "reversed by an unrelated, legitimate reverse() call") that this round's MEDIUM-severity,
     * documentation-scoped fix deliberately does not attempt.
     *
     * @throws InvalidRepostSourceException when $old does not belong to $new's company, or is not
     *                                      in posting_status = 'posted' (P1 fix round, MEDIUM finding) —
     *                                      including, per the RETRY LIMITATION above, a retry whose
     *                                      $old was reloaded after a prior successful repost().
     */
    public function repost(Transaction $old, DocumentDraft $new, \DateTimeInterface $date, ?int $userId): PostedDocument
    {
        return DB::transaction(function () use ($old, $new, $date, $userId) {
            // P1 FIX ROUND (MEDIUM finding): repost() used to reverse $old and post $new with no
            // check that they even belong to the same company, nor that $old is actually a live
            // posted document. A caller that resolves the wrong $old (trivially possible during P2
            // if a controller looks a transaction up by a non-tenant-scoped key) would reverse one
            // company's document and post a different company's replacement in one atomic unit —
            // both halves individually pass their own tenant checks, so nothing else catches it.
            if ((int) $old->company_id !== $new->companyId) {
                throw new InvalidRepostSourceException(
                    $old->id,
                    (int) $old->company_id,
                    $new->companyId
                );
            }

            if ($old->posting_status !== 'posted') {
                throw new InvalidRepostSourceException(
                    $old->id,
                    null,
                    null,
                    (string) $old->posting_status
                );
            }

            // W2 fix (D3): force the REPLACEMENT draft's payment_id to NULL, unconditionally,
            // regardless of what the caller's $new draft carries. See this class's W2 FIX ROUND
            // docblock section for the full reasoning: reverse() (called a few lines below) never
            // clears payment_id off $old, so $old keeps occupying its
            // (payment_id, reference_type) slot in transactions_payment_id_reference_type_unique
            // even after being reversed. The NATURAL way a caller builds $new is to reuse the
            // SAME payment_id $old carried — "this is still the same real-world payment, just
            // corrected" — which would collide on that exact slot. payment_id belongs to the
            // ORIGINAL document only; done here, before the idempotency-key convention below,
            // so it applies whether or not $new's idempotencyKey happens to collide with $old's.
            if ($new->paymentId !== null) {
                $new = $this->withoutPaymentId($new);
            }

            // P1 FIX ROUND 3 (MEDIUM finding): enforce the key convention documented on this
            // method above BEFORE reverse() runs, using $old->idempotency_key as it stands right
            // now — reverse() never touches that column (only posting_status and
            // reversal_of_transaction_id), so there is no ordering hazard in reading it here first.
            if ($new->idempotencyKey !== null && $new->idempotencyKey === $old->idempotency_key) {
                $new = $this->withRepostIdempotencyKey($new, ':repost:'.$old->id);
            }

            $this->reverse($old, $date, $userId, false);

            return $this->post($new, $userId);
            // NOTE (P1 fix round 3, LOW finding — see TRANSACTION_RETRY_ATTEMPTS's own docblock):
            // repost() is the TRUE top-level call in this class's one legitimate internal nesting
            // chain (repost -> reverse -> post), so THIS retry is the one that actually matters —
            // it re-runs the entire reverse()+post() sequence above on a deadlock. reverse()'s and
            // post()'s own TRANSACTION_RETRY_ATTEMPTS are both inert for calls reached through this
            // chain. Callers must not wrap repost() in their own DB::transaction(), which would
            // make this retry inert too with nothing left to compensate.
        }, self::TRANSACTION_RETRY_ATTEMPTS);
    }

    /**
     * Rebuilds $draft field-for-field with $suffix appended to its idempotencyKey. DocumentDraft
     * (a different fixer's file, out of this round's scope to modify) is an immutable value object
     * with no "with"-style mutator, so this is the only way to derive a copy that differs in one
     * field. See repost()'s key-convention docblock for why this exists and when it is called.
     *
     * W2 fix (D3): does NOT copy `$draft->paymentId` — always emits `paymentId: null`. By the
     * time repost() calls this method, $draft's paymentId has already been forced to NULL by
     * repost()'s own unconditional withoutPaymentId() call (see repost()'s docblock), so this is
     * defense-in-depth, not a behaviour change at this method's one real call site: it exists so
     * this "field-for-field copy" helper can never itself re-introduce a payment_id onto a repost
     * draft, for a hypothetical future caller that reaches it any other way. See class docblock's
     * W2 FIX ROUND section for the full reasoning on why payment_id must never appear on a
     * repost() replacement's header.
     *
     * RESIDUAL 18 DECISION — KEEP, not remove: this line's own INPUT ($draft->paymentId) is
     * always already NULL at the one real call site above, so it is "dead" only in the narrow
     * sense that copying `$draft->paymentId` here instead would currently produce an IDENTICAL
     * result (null -> null) — it is not dead in the sense of being unreachable or pointless: a
     * private method on a `final` class has exactly one call graph this class controls, and that
     * graph's only guarantee against a future second call site (e.g. a later refactor that calls
     * this method before withoutPaymentId(), not after) is THIS line, not the caller's current
     * ordering. Removing it would trade a real, if currently redundant, invariant for a
     * behaviourally-equivalent-today method that silently stops protecting itself the moment
     * something upstream changes. Exercised directly (independent of repost()'s current
     * withoutPaymentId()-first ordering) by
     * PostingServiceRepostPaymentIdTest::test_with_repost_idempotency_key_forces_payment_id_null_even_when_the_input_draft_still_carries_one()
     * via ReflectionMethod, which calls this private method with a DocumentDraft whose paymentId
     * is deliberately still non-null — proving the line's own effect, not merely its current
     * no-op-ness at the one call site that happens to pre-clear it today.
     */
    private function withRepostIdempotencyKey(DocumentDraft $draft, string $suffix): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $draft->companyId,
            branchId: $draft->branchId,
            docType: $draft->docType,
            subType: $draft->subType,
            docDate: $draft->docDate,
            narration: $draft->narration,
            lines: $draft->lines,
            idempotencyKey: $draft->idempotencyKey.$suffix,
            sourceType: $draft->sourceType,
            sourceId: $draft->sourceId,
            invoiceId: $draft->invoiceId,
            userId: $draft->userId,
            costCenterId: $draft->costCenterId,
            allowLockedPeriods: $draft->allowLockedPeriods,
            paymentReference: $draft->paymentReference,
            paymentId: null,
            // P2.5.A addition: carried field-for-field like every other field on this
            // reconstruction (see this method's own docblock) — a repost draft that was itself
            // built with an override reason (e.g. correcting a document inside a still-soft_closed
            // period) must not silently lose it and then fail PeriodGuard's check.
            overrideReason: $draft->overrideReason,
            // P2.5.B addition: same "carried field-for-field" convention — a repost draft built
            // with an explicit $postingDate must not silently fall back to auto-resolution from
            // $docDate instead.
            postingDate: $draft->postingDate,
        );
    }

    /**
     * W2 fix (D3): rebuilds $draft field-for-field with paymentId forced to NULL. Used by
     * repost() to enforce, unconditionally, that a repost's replacement document never carries
     * `transactions.payment_id` — see repost()'s own call site and this class's W2 FIX ROUND
     * docblock section for the full reasoning (the ORIGINAL document keeps its payment_id after
     * being reversed; a second document claiming the same (payment_id, reference_type) slot
     * would collide on transactions_payment_id_reference_type_unique). Same "no with-style
     * mutator on an immutable value object" rationale as withRepostIdempotencyKey() above.
     */
    private function withoutPaymentId(DocumentDraft $draft): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $draft->companyId,
            branchId: $draft->branchId,
            docType: $draft->docType,
            subType: $draft->subType,
            docDate: $draft->docDate,
            narration: $draft->narration,
            lines: $draft->lines,
            idempotencyKey: $draft->idempotencyKey,
            sourceType: $draft->sourceType,
            sourceId: $draft->sourceId,
            invoiceId: $draft->invoiceId,
            userId: $draft->userId,
            costCenterId: $draft->costCenterId,
            allowLockedPeriods: $draft->allowLockedPeriods,
            paymentReference: $draft->paymentReference,
            paymentId: null,
            // P2.5.A addition: carried field-for-field like every other field on this
            // reconstruction (see this method's own docblock) — a repost draft that was itself
            // built with an override reason (e.g. correcting a document inside a still-soft_closed
            // period) must not silently lose it and then fail PeriodGuard's check.
            overrideReason: $draft->overrideReason,
            // P2.5.B addition: same "carried field-for-field" convention — a repost draft built
            // with an explicit $postingDate must not silently fall back to auto-resolution from
            // $docDate instead.
            postingDate: $draft->postingDate,
        );
    }

    /**
     * Step 2a: which account id does this line target? Explicit accountId is used verbatim (no
     * query — existence/tenancy is checked once the batched, locked read happens); purposeCode
     * goes through AccountResolver, which throws UnmappedPurposeException on a miss rather than
     * silently skipping a leg (R7.3, BUG-H6).
     *
     * P1 FIX ROUND (LOW finding): a line that supplies BOTH a non-null accountId and a non-empty
     * purposeCode used to silently prefer accountId with no warning even if the two disagreed —
     * exactly how a wrong-account posting hides in review. Rejected outright now.
     */
    private function targetAccountId(LineDraft $line, int $companyId, int $index): int
    {
        if ($line->accountId !== null && $line->purposeCode !== '') {
            throw new \InvalidArgumentException(sprintf(
                'DocumentDraft::$lines[%d] supplies both accountId (#%d) and a non-empty purposeCode '
                ."('%s') — a line must resolve its account by exactly one path.",
                $index,
                $line->accountId,
                $line->purposeCode
            ));
        }

        if ($line->accountId !== null) {
            return $line->accountId;
        }

        return $this->accounts->resolve($line->purposeCode, $companyId, $line->serviceType)->id;
    }

    /**
     * See class docblock, resolved-gap #3: transactions.reference_type is a closed 4-value legacy
     * enum this build's migration set does not widen. A caller-supplied $draft->sourceType wins when
     * it is already one of the four valid strings; otherwise a best-effort docType map is used.
     *
     * NOTE (P1 fix round, BLOCKER 3): this value feeds transactions.reference_type ONLY. It must
     * never be used to derive transactions.invoice_id — see DocumentDraft::$invoiceId.
     */
    private function resolveReferenceType(DocumentDraft $draft): string
    {
        if (is_string($draft->sourceType) && in_array($draft->sourceType, self::VALID_REFERENCE_TYPES, true)) {
            return $draft->sourceType;
        }

        return self::DOC_TYPE_REFERENCE_TYPE[$draft->docType] ?? 'Invoice';
    }

    /**
     * See class docblock, resolved-gap #7: Transaction::$fillable predates the P1.1 columns, so
     * mass assignment via create() would throw. forceFill() is the standard, safe Eloquent
     * mechanism for system code writing fully engine-controlled data (no external/request input
     * ever reaches this array unmapped), and needs no change to the model file.
     */
    private function createTransactionHeader(array $attributes): Transaction
    {
        $transaction = new Transaction;
        $transaction->forceFill($attributes);
        $transaction->save();

        return $transaction;
    }

    /**
     * Shared idempotency lookup (BLOCKER 5 fix) — used both by step 1's sequential-path check and
     * by the header-insert catch's concurrent-path recovery, so the two paths can never disagree
     * about what "already posted under this key" means. Deliberately does NOT filter
     * posting_status: the DB unique index (company_id, idempotency_key) doesn't either, so any
     * status match here is exactly what would otherwise collide on INSERT. Excludes soft-deleted
     * rows explicitly (withoutGlobalScopes() drops SoftDeletingScope too).
     *
     * P1 FIX ROUND 3 (HIGH regression — REPEATABLE READ snapshot): $forUpdate switches this from a
     * plain consistent read to a locking read. See the header-insert catch block for the full
     * reasoning; in short, step 1's own call (this method with $forUpdate = false, the default)
     * establishes this transaction's InnoDB read-view snapshot the first time it runs, and every
     * PLAIN read for the rest of the transaction — including a second call to this same method —
     * is served from that SAME snapshot under REPEATABLE READ, no matter what has committed since.
     * The concurrent-race recovery path in the catch block cannot use that default: it MUST pass
     * $forUpdate = true so its re-query is a locking read, which InnoDB always services from the
     * latest COMMITTED data regardless of the transaction's snapshot.
     */
    private function findByIdempotencyKey(int $companyId, string $idempotencyKey, bool $forUpdate = false): ?Transaction
    {
        $query = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Narrow duplicate-key detection for the BLOCKER 5 fix.
     *
     * P1 FIX ROUND 3 (MEDIUM finding — the round-2 version of this check was not narrow enough):
     * the round-2 check accepted SQLSTATE 23000 alone as sufficient evidence of "duplicate key".
     * 23000 is MySQL's generic "Integrity constraint violation" class — it is ALSO the SQLSTATE
     * reported for a foreign-key violation (driver code 1216/1452), which `transactions` has
     * several of (company_id, branch_id, invoice_id, reversal_of_transaction_id — see migration
     * 2026_08_24_120004 for the last one). Accepting 23000 alone meant an FK failure on THIS SAME
     * INSERT (e.g. a caller-supplied $draft->invoiceId that does not exist) could be misread as
     * "the winner already posted, go fetch and return it" — a real integrity error swallowed and
     * masked as success, with findByIdempotencyKey() then either returning an unrelated document
     * (if the message's loose "idempotency_key" substring check happened to match something in an
     * unrelated FK error message) or hitting the "should be unreachable" branch and rethrowing the
     * FK error anyway (if it didn't) — correct only by accident, not by construction. Fixed here by
     * requiring the SPECIFIC duplicate-entry driver code (1062) FIRST — MySQL's FK-violation driver
     * codes are 1216/1452, never 1062, so this alone already rules out every FK case.
     *
     * Requiring only 1062 still is not enough on its own to name THIS index: `transactions` also
     * carries a second, differently-shaped unique index
     * (transactions_payment_id_reference_type_unique, from the P0 payment-race hotfix migration)
     * that a 1062 could in principle come from. UPDATE (W1.2 fix round, Task A): this class's own
     * INSERT genuinely CAN collide on that second index now that $headerAttributes writes
     * `payment_id` (see DocumentDraft::$paymentId) — e.g. a feeder bug that attempts to post two
     * distinct documents under the same (payment_id, reference_type) pair with two different
     * idempotency keys. When that happens this method correctly returns `false` here (the error
     * message names the OTHER index, not IDEMPOTENCY_KEY_UNIQUE_INDEX), so the original
     * QueryException propagates untouched rather than being misread as "the winner already
     * posted" — exactly the fix-round instruction below this method never swallows an unrelated
     * integrity error and returns a wrong document. Checking for the SPECIFIC index name — not
     * just the generic 1062 code — is what makes that distinction possible at all. Matched against
     * the FULL default index name
     * (IDEMPOTENCY_KEY_UNIQUE_INDEX = transactions_company_id_idempotency_key_unique, verified
     * against migration 2026_08_24_120004's literal `$table->unique(['company_id',
     * 'idempotency_key'])` call, not guessed) via str_contains rather than an exact match, because
     * MySQL 8.0.19+ table-qualifies the name in the error message (`for key
     * 'transactions.transactions_company_id_idempotency_key_unique'`) while earlier 8.0.x point
     * releases do not (`for key 'transactions_company_id_idempotency_key_unique'`) — a substring
     * check is satisfied by both formats, and the full index name (not merely the bare column name
     * "idempotency_key" the round-2 version matched on) cannot coincidentally appear in any other
     * constraint's error message on this or any other table.
     */
    private function isIdempotencyKeyRaceViolation(QueryException $e): bool
    {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
            return false;
        }

        return str_contains($e->getMessage(), self::IDEMPOTENCY_KEY_UNIQUE_INDEX);
    }

    /**
     * W2 fix (D3): sibling narrow-match check for the OTHER unique index this same header
     * INSERT can collide on — transactions_payment_id_reference_type_unique (P0 hotfix migration
     * 2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php). Same reasoning
     * as isIdempotencyKeyRaceViolation() throughout: requires the SPECIFIC duplicate-entry driver
     * code (1062) first — ruling out FK violations, which never report 1062 — AND the specific
     * index name via str_contains (not merely a bare column name), which is what lets this check
     * and isIdempotencyKeyRaceViolation() disagree correctly on the SAME 1062 error rather than
     * one of them guessing. Deliberately does NOT attempt any recovery of its own (unlike the
     * sibling method) — see DuplicatePaymentReferenceException's own docblock for why a collision
     * on this index can never be resolved by "return the existing document" the way an
     * idempotency-key race can.
     */
    private function isPaymentReferenceTypeRaceViolation(QueryException $e): bool
    {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
            return false;
        }

        return str_contains($e->getMessage(), self::PAYMENT_REFERENCE_TYPE_UNIQUE_INDEX);
    }

    /**
     * W3-prereq lane B fix (doc 17 §3.3/§4): sibling narrow-match check for the THIRD unique
     * index this same header INSERT can collide on — transactions_company_doctype_refnum_unique
     * (migration 2026_08_24_120008_add_unique_reference_number_to_transactions_table.php). Same
     * reasoning as isIdempotencyKeyRaceViolation() and isPaymentReferenceTypeRaceViolation()
     * throughout: requires the SPECIFIC duplicate-entry driver code (1062) first — ruling out FK
     * violations, which never report 1062 — AND the specific index name via str_contains (not
     * merely a bare column name), which is what lets all three checks disagree correctly on the
     * SAME 1062 error rather than one of them guessing. Unlike its two siblings, a true positive
     * here is always recoverable by construction: it fires only because $formattedNumber, minted
     * moments earlier by SequenceService::next() inside this same transaction, already belongs to
     * some other row (legacy or a prior engine post) under this exact (company_id, doc_type)
     * pair — reserving a fresh serial and retrying the INSERT is always a valid next step, unlike
     * a payment-reference-type collision (a genuinely different document, never resolved by
     * retrying).
     */
    private function isSerialCollisionViolation(QueryException $e): bool
    {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
            return false;
        }

        return str_contains($e->getMessage(), self::SERIAL_COLLISION_UNIQUE_INDEX);
    }

    /**
     * P1 FIX ROUND 4 (BLOCKING #2's admin-visibility half). Writes one IdempotencyKeyRejection row
     * for a post() attempt rejected because its idempotency key collided with a soft-deleted
     * transaction — called ONCE, immediately before the SupersededIdempotencyKeyException that
     * always accompanies it (see that call site).
     *
     * DURABILITY: IdempotencyKeyRejection::$connection is 'accounting_audit' — a SECOND, independent
     * database connection to the same physical database (see that model's own docblock, and
     * config/database.php's comment on the connection). This is what makes the row survive the
     * DB::transaction() rollback the caller triggers immediately after this method returns: the
     * INSERT this method issues commits synchronously, on a connection with no open transaction of
     * its own, before control ever reaches the `throw` at the call site — it is NOT part of, and
     * cannot be undone by, the rollback of post()'s own transaction on the DEFAULT connection.
     *
     * Best-effort on top of that guarantee, not instead of it: if the write itself fails (e.g. the
     * `accounting_audit` connection is unreachable for infrastructure reasons unrelated to this
     * logic), that failure is logged and swallowed here — it must never suppress, soften, or get
     * confused with the ORIGINAL QueryException the caller's catch block is already handling, and
     * the caller's own SupersededIdempotencyKeyException must still be thrown either way. A caller
     * that receives a null `$adminRecordId` on the exception knows the durable write itself failed
     * and can fall back to the log line this method also writes in that case.
     */
    private function recordSupersededIdempotencyKeyRejection(
        DocumentDraft $draft,
        Transaction $deadTransaction,
        float $attemptedAmount,
        ?int $userId
    ): ?IdempotencyKeyRejection {
        try {
            return IdempotencyKeyRejection::create([
                'company_id' => $draft->companyId,
                'idempotency_key' => $draft->idempotencyKey,
                'dead_transaction_id' => $deadTransaction->id,
                'dead_transaction_deleted_at' => $deadTransaction->deleted_at,
                'attempted_amount' => $attemptedAmount,
                'attempted_doc_type' => $draft->docType,
                'attempted_by' => $userId,
            ]);
        } catch (\Throwable $recordingError) {
            Log::error(
                'PostingService: failed to durably record an IdempotencyKeyRejection for a '
                    .'superseded idempotency key — the post is still being rejected via '
                    .'SupersededIdempotencyKeyException; only this secondary admin-visibility '
                    .'write failed.',
                [
                    'company_id' => $draft->companyId,
                    'idempotency_key' => $draft->idempotencyKey,
                    'dead_transaction_id' => $deadTransaction->id,
                    'attempted_amount' => $attemptedAmount,
                    'error' => $recordingError->getMessage(),
                ]
            );

            return null;
        }
    }

    /** Rebuilds a PostedDocument from an already-posted Transaction (idempotent-return paths). */
    private function toPostedDocument(Transaction $transaction): PostedDocument
    {
        // P1 FIX ROUND (HIGH soft-delete finding): withoutGlobalScopes() drops SoftDeletingScope
        // too — exclude soft-deleted lines explicitly so a rebuilt PostedDocument never silently
        // includes a leg that was deleted out from under it.
        $lines = JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('transaction_id', $transaction->id)
            ->get()
            ->all();

        return new PostedDocument($transaction, $lines, $transaction->reference_number);
    }

    /** Defensive width guard for the varchar(255) NOT NULL columns this service writes. */
    private function truncate(?string $value, int $length = 255): string
    {
        $value = $value ?? '';

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    /**
     * W5.L fix — same defensive intent as truncate() (P1 fix round 4's InvalidCurrencyCodeException
     * MEDIUM finding: an over-long value must never reach a strict-mode MySQL INSERT unchecked and
     * crash with a raw driver error), but preserves NULL instead of coercing a genuinely absent
     * value to '' — cheque_no/bank_info/auth_no are all nullable columns where "no cheque on this
     * line" is a real, distinct state from "a cheque with an empty number", unlike description/
     * voucher_number/name above, which always have a non-null fallback already.
     */
    private function truncateNullable(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }
}
