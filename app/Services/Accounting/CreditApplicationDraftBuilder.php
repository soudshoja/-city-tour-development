<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\CreditApplicationTotalMismatchException;
use App\Exceptions\Accounting\UnresolvedBranchException;
use App\Http\Traits\CurrencyExchangeTrait;
use App\Models\Invoice;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Log;

/**
 * PROPOSED NAME (W2b build, KEY: draft-builder — design call E1; fixed W2c, orchestrator
 * rulings B-3/R-a/R-g). ONE shared engine draft builder for BOTH existing
 * `createCreditPaymentCOA()` implementations —
 * {@see \App\Http\Controllers\InvoiceController::createCreditPaymentCOA()} and
 * {@see \App\Services\PaymentApplicationService::createCreditPaymentCOA()} — which today
 * duplicate, and subtly diverge on, the same "apply an already-received client
 * credit/advance against an invoice" event. This class does not touch either legacy method: it
 * only produces the {@see DocumentDraft} a future cutover of either file would hand to
 * {@see PostingSeam::post()}.
 *
 * ── What it books (matches HEAD, both legacy copies) ──────────────────────────────────────────
 * N debit lines Dr `CLIENT_ADVANCE` (one per positive {@see CreditApplicationInput}, purpose-coded
 * — resolves to the same "Payment Gateway" leaf under Liabilities > Advances > Client, CoaSeeder
 * code 2632, both legacy copies walk by name/parent_id) + ONE credit line Cr
 * `RECEIVABLE_CONTROL` (resolves to the "Clients" leaf under Accounts Receivable, CoaSeeder code
 * 1351) for the SUM OF THE POSTED DEBITS — never the caller's own `$totalAmount`/`$creditApplied`
 * variable. A zero/negative {@see CreditApplicationInput::$amountApplied} is skipped, exactly
 * `if ($amountApplied <= 0) continue;` in both legacy copies.
 *
 * ── W2c fix (B-3): round PER LINE, before summing, not the other way round ─────────────────────
 * W2b's first cut pushed the UNROUNDED `$application->amountApplied` into each debit line, then
 * built the credit leg as `round(Σ unrounded debits, $decimals)`. `PostingService` rounds EACH
 * LINE independently before checking the header balance, so `Σ round(aᵢ) ≠ round(Σ aᵢ)` whenever
 * any amount carries more than `base_decimals` places — e.g. two applications of 1.2345 round to
 * 1.235 each (Σ = 2.470) but `round(2.469, 3) = 2.469`, a 0.001 gap against the engine's default
 * 0.0005 tolerance, so the engine rejected a document THIS builder itself had certified as
 * balanced (W2b lead report §5, B-3). Fixed by rounding each debit's amount to `$decimals`
 * BEFORE constructing its {@see LineDraft}, and building the credit leg from the SUM OF THOSE
 * ALREADY-ROUNDED values — so the credit leg is, by construction, exactly what the debit lines
 * sum to after `PostingService`'s own (now idempotent) per-line rounding.
 *
 * ── W2c fix (R-a): the FC pair, not a mislabelled base amount ──────────────────────────────────
 * W2b's first cut always wrote `currency: $baseCurrency` at `exchangeRate: 1.0` — correct only
 * when the invoice's own currency IS the base currency, and a silent mislabelling of a foreign
 * magnitude as base currency otherwise (both legacy copies write `$invoice->currency`, which
 * genuinely varies). Fixed: `$invoice->currency` is resolved ONCE per `build()` call (every line
 * in one document shares one invoice), compared case-insensitively against
 * `config('accounting.engine.base_currency')`:
 *   - Same as base -> `exchangeRate = 1.0`, `originalAmount === amount` (the FC-consistency rule
 *     `PostingService::post()` step 3f requires for a base-currency line).
 *   - Different from base -> the invoice's rate is resolved via
 *     {@see CurrencyExchangeTrait::getExchangeRate()} (the app's one existing FX-lookup
 *     mechanism — see `PostingService`'s own docblock, "the app's one real dual-currency
 *     conversion path"), FC-unit -> base-unit, company-scoped. When no rate row exists for that
 *     pair, `exchangeRate` falls back to `1.0` and `Log::warning('accounting.fx_rate_missing', …)`
 *     fires — a loud, observable degradation rather than a silent one, since neither legacy copy
 *     ever needed an FX lookup at all (they always assumed base currency).
 * Each debit's `originalAmount` is the applied amount in the INVOICE's own currency (rounded per
 * the B-3 fix above); `amount` (base) is `round(originalAmount × exchangeRate, $decimals)`. The
 * credit leg's FC pair is the SUM of the per-line rounded original/base amounts, in the same
 * currency/rate — never re-derived independently.
 *
 * ── Why the credit leg is never the caller's total ────────────────────────────────────────────
 * The two legacy implementations compute their own `$totalAmount`/`$creditApplied` independently
 * of the debit loop (`InvoiceController::savePartial()`:
 * `array_sum(array_column($appliedPayments, 'amount_applied'))`;
 * `PaymentApplicationService::applyPaymentsToInvoice()`: its own running `$creditApplied`), so a
 * caller bug (or a future caller that computes the total a third way) can hand this builder a
 * total that silently disagrees with what the debit loop would actually post — legacy just
 * writes both numbers into two different columns of the SAME header row
 * (`Transaction::amount` = caller's total; the credit `JournalEntry::credit` = the SAME caller's
 * total) and never notices. This builder refuses to reproduce that: the credit leg's ORIGINAL
 * (invoice-currency) total is ALWAYS built from what it actually posted (the sum of the rounded
 * per-line original amounts), and a caller-supplied `$callerTotalAmount` — itself in the
 * invoice's own currency, matching what every caller today computes — that disagrees with that
 * sum throws {@see CreditApplicationTotalMismatchException} — a loud data error, never an
 * unbalanced or silently-adjusted document. See W2 lead report §7, trap 3.
 *
 * ── Header attribution (W2 lead report §7, traps 1/2) ─────────────────────────────────────────
 *   - `paymentId` is left NULL on every draft this builder produces. Both legacy copies write
 *     `reference_type => 'Payment'` with NO `payment_id` at all — the exact reason today's
 *     `unique(payment_id, reference_type)` index never collides with `ClientController::
 *     addCredit()`'s success document or `PaymentController`'s own B3 failure rows, all of which
 *     share the SAME `('Payment')` namespace. Setting `paymentId` here would put this document
 *     in that namespace for the first time and risk exactly that collision — trap 1.
 *   - `sourceType` is PINNED to the literal string `'Payment'`, never left to
 *     `PostingService::DOC_TYPE_REFERENCE_TYPE`'s own docType fallback map — this builder's
 *     `docType` ('JV' — see below) would otherwise fall back to `'Invoice'`, which is wrong for
 *     this event (no invoice is being generated; an existing credit is being applied).
 *   - `docType` is `'JV'` (Journal Voucher) — this event moves no cash and issues no invoice; it
 *     reallocates an already-recorded liability against an already-recorded receivable, which is
 *     what a JV is for. Pinning `sourceType` above (rather than relying on `docType` to imply the
 *     right `reference_type`) is exactly why the docType choice here does not need to match
 *     either legacy's `reference_type` value on its own.
 *   - `voucherLabel`/`$defaultVoucherLabel` reproduce each legacy file's own literal default
 *     verbatim: callers should pass `'Client Credit'` for `InvoiceController` parity and
 *     `'N/A'` for `PaymentApplicationService` parity (see each file's own `$voucherNumber ??`
 *     fallback).
 *   - `ledgerType` is set explicitly on every line (`'payable'` for the debits, `'receivable'`
 *     for the credit) — HEAD's own `journal_entries.type` values for this event on BOTH legacy
 *     copies (trap 4). `partyName`/`voucherNumber` are deliberately left null: neither legacy
 *     copy writes a party name onto these two JournalEntry rows (both write the resolved
 *     ACCOUNT's own name, e.g. `$liabilityAccount->name`) or a `voucher_number` column value at
 *     all, and {@see LineDraft}'s own documented fallback for a null `$partyName`/`$voucherNumber`
 *     (the resolved account's name / the document's own formatted number) is the closest
 *     available match to "don't set anything legacy didn't set" for the name half — there is no
 *     engine-side equivalent of "leave the column NULL" for `voucher_number`, so the fallback is
 *     accepted here as a documented, additive divergence rather than guessed around.
 *   - `branchId` is resolved from `$invoice->agent?->branch_id` and MUST be a real, positive id —
 *     see the W2c fix (R-g) below.
 *
 * ── W2c fix (R-g): refuse a null/0 branch, never post the 0 sentinel ──────────────────────────
 * W2b's first cut wrote `branchId: (int) $invoice->agent?->branch_id` — a null chain casts
 * silently to `0`, and `PostingService::post()`'s document-numbering step
 * (`SequenceService::next($docType, $companyId, $branchId, $docDate)`) would then reserve a real
 * sequence value under a phantom "branch 0", rather than failing at the one place that already
 * knows the chain is broken. Fixed: a null/non-positive branch now throws
 * {@see UnresolvedBranchException} before any line is built.
 *
 * ── What this class deliberately does NOT do ──────────────────────────────────────────────────
 *   - It does not call {@see PostingSeam} or {@see PostingService} — it only builds the draft.
 *     Wiring either legacy method onto the seam is a separate, not-yet-authorised cutover.
 *   - It does not resolve `$companyId`/`$userId` from `Auth::user()` or `Auth::id()` — both are
 *     required, already-resolved constructor... (method) arguments, so this class stays
 *     queue/webhook-safe like every other engine-layer class, and each caller keeps its own
 *     `if (!$companyId) { Log::warning(...); return null; }` guard shape (E3) rather than this
 *     class re-implementing it.
 *   - It does not touch `FixCreditInvoiceCOA`'s private copy — that command is explicitly out of
 *     this cutover's scope (design call E4).
 */
final class CreditApplicationDraftBuilder
{
    use CurrencyExchangeTrait;

    public function __construct(
        // accounting-builds T1 (F1/Q2 fix, see build()'s own docblock note below): resolves the
        // invoice's own POSTED INV-document RECEIVABLE_CONTROL rate. Plain constructor DI, same
        // convention every other engine-layer class in this directory uses (no explicit binding —
        // see PostingSeam's own docblock).
        private readonly AccountResolver $accounts = new AccountResolver(),
    ) {}

    /**
     * @param  CreditApplicationInput[]  $applications  The full set submitted for this event —
     *                                                   including any this method goes on to skip for being zero/negative. Must be
     *                                                   non-empty (a credit-application event with nothing applied is not a
     *                                                   representable document).
     * @param  float  $callerTotalAmount  The caller's own computed total, IN THE INVOICE'S OWN
     *                                    CURRENCY (never pre-converted to base) — matching what every caller today
     *                                    computes (`array_sum()` of the same invoice-currency `amount_applied`
     *                                    figures handed to this method). Compared against the ACTUAL sum of posted
     *                                    (i.e. positive, non-skipped) debits' rounded ORIGINAL amounts, with the
     *                                    same tolerance PostingService itself uses for header balance
     *                                    (`config('accounting.engine.balance_tolerance')`); a mismatch throws rather
     *                                    than posting either number.
     * @param  int  $companyId  Tenant of record — already resolved by the caller (E3: from
     *                          `$invoice->agent?->branch?->company_id`) and already validated non-null there.
     *                          This class does not re-derive or re-validate it against `Auth`.
     * @param  int|null  $userId  `Auth::id()` at the caller's own call site (E3 — these paths run under a
     *                            real authenticated session, unlike every W2 gateway feeder), or null for a
     *                            console/queue caller. Written straight to `DocumentDraft::$userId`.
     * @param  string  $defaultVoucherLabel  `'Client Credit'` for `InvoiceController` parity, `'N/A'` for
     *                                       `PaymentApplicationService` parity — see class docblock.
     *
     * @throws CreditApplicationTotalMismatchException When `$callerTotalAmount` does not equal
     *                                                  the sum of the (rounded) original-currency debit amounts actually built
     *                                                  (see class docblock).
     * @throws UnresolvedBranchException When `$invoice->agent?->branch_id` is null or not a
     *                                   positive integer (see class docblock, W2c fix R-g).
     * @throws \InvalidArgumentException When `$applications` is empty, or contains something
     *                                   other than a {@see CreditApplicationInput}.
     */
    public function build(
        Invoice $invoice,
        array $applications,
        float $callerTotalAmount,
        int $companyId,
        ?int $userId = null,
        string $defaultVoucherLabel = 'Client Credit',
    ): DocumentDraft {
        if ($applications === []) {
            throw new \InvalidArgumentException(
                'CreditApplicationDraftBuilder::build() requires at least one CreditApplicationInput.'
            );
        }

        $branchId = $invoice->agent?->branch_id;
        if ($branchId === null || (int) $branchId <= 0) {
            throw new UnresolvedBranchException($invoice->id, $companyId);
        }
        $branchId = (int) $branchId;

        $baseCurrency = (string) config('accounting.engine.base_currency');
        $decimals = (int) config('accounting.engine.base_decimals', 3);
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        // W2c fix (R-a): resolve the invoice's own currency/rate ONCE — every line in this
        // document shares one invoice, so the FX pair is a document-level fact, not a per-line
        // one. See class docblock.
        $invoiceCurrency = ($invoice->currency !== null && $invoice->currency !== '')
            ? $invoice->currency
            : $baseCurrency;
        $isBaseCurrency = strtoupper(trim($invoiceCurrency)) === strtoupper(trim($baseCurrency));

        $exchangeRate = 1.0;
        if (! $isBaseCurrency) {
            // accounting-builds T1 (F1/Q2 fix — owner ruling, 2026-09-02: "Applies must use the
            // POSTED invoice rate"). The original W2c cut resolved a CURRENT lookup rate here via
            // CurrencyExchangeTrait::getExchangeRate() — the app's one rate table
            // (`currency_exchanges`) is a single MUTABLE row with no effective dating, so "the
            // rate as of the invoice's own posting" cannot be recovered from it later; a fresh
            // lookup at apply time can silently disagree with whatever rate the invoice's own
            // `INV` document was actually posted at, leaving a residual balance in
            // RECEIVABLE_CONTROL for this invoice/client that this credit-apply document itself
            // was supposed to zero out. The ONLY reliable posted rate is the one frozen on the
            // invoice's own journal line (`journal_entries.exchange_rate`, written verbatim by
            // PostingService step 8) — resolved here, never re-derived.
            $resolvedRate = $this->resolvePostedInvoiceRate($invoice, $companyId);

            if ($resolvedRate === null || $resolvedRate <= 0) {
                // No posted INV-document rate exists (a legacy-era invoice whose receivable line
                // was never engine-posted, or one with exchange_rate = 0) — fall back to the
                // original live-lookup behaviour, but logged DISTINCTLY from the "truly missing"
                // case below so the two causes are never confused on the 'accounting' channel.
                Log::info('accounting.credit_apply_rate_fallback_live_lookup', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $companyId,
                    'currency' => $invoiceCurrency,
                    'base_currency' => $baseCurrency,
                ]);

                $resolvedRate = $this->getExchangeRate($companyId, $invoiceCurrency, $baseCurrency);
            }

            if ($resolvedRate === null || $resolvedRate <= 0) {
                Log::warning('accounting.fx_rate_missing', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $companyId,
                    'currency' => $invoiceCurrency,
                    'base_currency' => $baseCurrency,
                ]);
            } else {
                $exchangeRate = $resolvedRate;
            }
        }

        $debitLines = [];
        $postedOriginalTotal = 0.0; // invoice-currency sum of ROUNDED per-line amounts (B-3)
        $postedBaseTotal = 0.0;     // base-currency sum of ROUNDED per-line amounts (B-3)

        foreach ($applications as $application) {
            if (! $application instanceof CreditApplicationInput) {
                throw new \InvalidArgumentException(sprintf(
                    'CreditApplicationDraftBuilder::build() expects CreditApplicationInput instances; got %s.',
                    get_debug_type($application)
                ));
            }

            if ($application->amountApplied <= 0.0) {
                // Both legacy copies: `if ($amountApplied <= 0) continue;`. The application's
                // id/source still counts toward the idempotency key — see $applications passed
                // to PaymentIdempotencyKey::forCreditApplication() below, whole and unfiltered.
                continue;
            }

            // W2c fix (B-3): round EACH debit's amounts to $decimals BEFORE constructing its
            // LineDraft, and accumulate the totals from those ALREADY-ROUNDED values — never the
            // other way round. See class docblock.
            $originalAmount = round($application->amountApplied, $decimals);
            $baseAmount = round($originalAmount * $exchangeRate, $decimals);

            $postedOriginalTotal += $originalAmount;
            $postedBaseTotal += $baseAmount;

            $debitLines[] = new LineDraft(
                purposeCode: 'CLIENT_ADVANCE',
                accountId: null,
                side: 'debit',
                amount: $baseAmount,
                currency: $invoiceCurrency,
                originalAmount: $originalAmount,
                exchangeRate: $exchangeRate,
                transactionType: 'CUSTOMERDEBITED',
                partyAccountRef: $invoice->client_id,
                description: sprintf(
                    'Apply Client Credit from %s',
                    $application->voucherLabel ?? $defaultVoucherLabel
                ),
                invoiceId: $invoice->id,
                ledgerType: 'payable', // HEAD's own value on both legacy debit lines — trap 4.
            );
        }

        $postedOriginalTotal = round($postedOriginalTotal, $decimals);
        $postedBaseTotal = round($postedBaseTotal, $decimals);

        if (abs(round($callerTotalAmount, $decimals) - $postedOriginalTotal) >= $tolerance) {
            throw new CreditApplicationTotalMismatchException($invoice->id, $callerTotalAmount, $postedOriginalTotal);
        }

        $creditLine = new LineDraft(
            purposeCode: 'RECEIVABLE_CONTROL',
            accountId: null,
            side: 'credit',
            amount: $postedBaseTotal,
            currency: $invoiceCurrency,
            originalAmount: $postedOriginalTotal,
            exchangeRate: $exchangeRate,
            transactionType: 'CUSTOMERCREDITED',
            partyAccountRef: $invoice->client_id,
            description: sprintf('Invoice %s paid via Client Credit', $invoice->invoice_number),
            invoiceId: $invoice->id,
            ledgerType: 'receivable', // HEAD's own value on both legacy credit lines — trap 4.
        );

        return new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId, // resolved and validated above — W2c fix R-g.
            docType: 'JV', // Journal Voucher — see class docblock for why, and why sourceType is
            // pinned explicitly below rather than relying on this docType's own fallback.
            subType: null,
            docDate: now(),
            narration: sprintf('Credit Payment for %s', $invoice->invoice_number),
            lines: [...$debitLines, $creditLine],
            // W2c fix (B-2): the FULL, unfiltered $applications array is passed through so the
            // key can namespace on each element's own CreditApplicationInput::$idSource — see
            // PaymentIdempotencyKey::forCreditApplication()'s own docblock.
            idempotencyKey: PaymentIdempotencyKey::forCreditApplication($invoice->id, $applications),
            sourceType: 'Payment', // pinned — trap 1. Matches both legacy copies' own
            // reference_type verbatim; never left to docType's fallback map.
            sourceId: $invoice->id,
            invoiceId: $invoice->id,
            userId: $userId,
            paymentId: null, // trap 1 — keeps this row out of the (payment_id, reference_type)
            // namespace ClientController::addCredit()'s success document and PaymentController's
            // own B3 failure rows already occupy.
        );
    }

    /**
     * accounting-builds T1 (F1/Q2 fix — see build()'s own docblock note). Reads the invoice's own
     * posted `INV`-document `RECEIVABLE_CONTROL` line's `exchange_rate` — the SAME line
     * {@see \App\Services\Accounting\RealisedFxService::resolveAppliedLineId()} (private, mirrored
     * logic) resolves as its own "applied line". Deliberately excludes the credit-apply JV this
     * very method is building (that document does not exist yet at call time, so there is nothing
     * to exclude in practice — noted only so a future caller of this helper from elsewhere does
     * not assume otherwise) by filtering to `transactions.doc_type = 'INV'`. Returns null when no
     * such line exists (legacy-era invoice, or one never engine-posted) — the caller falls back to
     * a live lookup in that case, logged distinctly.
     *
     * accounting-builds T1 POST-FIX RE-VERIFY (defect V-6, the same defect and the same fix as
     * `RealisedFxService::resolveAppliedLineId()` — see that method's docblock). Without the
     * `posting_status = 'posted'` / `deleted_at` filters this read returns the REVERSED original
     * after an invoice repost (it keeps `doc_type = 'INV'`, keeps its lines' `invoice_id`, and
     * holds the lowest `journal_entries.id`, so "earliest wins" picks it), clearing the receivable
     * at a rate the invoice no longer carries — re-opening the exact AR residual this F1/Q2 fix
     * exists to close, and doing it SILENTLY: a row was found, so the distinct
     * 'accounting.credit_apply_rate_fallback_live_lookup' log never fires. Legacy rows still match:
     * the `posting_status` column's migration default is `'posted'`. Pinned by
     * RealisedFxRepostChainTest::test_credit_apply_jv_uses_the_live_invoice_rate_after_a_repost().
     */
    private function resolvePostedInvoiceRate(Invoice $invoice, int $companyId): ?float
    {
        try {
            $receivableAccountId = $this->accounts->resolve('RECEIVABLE_CONTROL', $companyId)->id;
        } catch (\Throwable) {
            // RECEIVABLE_CONTROL unmapped for this company -- the caller's own post() call is
            // about to fail loudly for the same reason; nothing useful to resolve here.
            return null;
        }

        $line = JournalEntry::withoutGlobalScopes()
            ->whereNull('journal_entries.deleted_at')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.posting_status', 'posted')
            ->where('transactions.doc_type', 'INV')
            ->where('transactions.company_id', $companyId)
            ->where('journal_entries.invoice_id', $invoice->id)
            ->where('journal_entries.account_id', $receivableAccountId)
            ->orderBy('journal_entries.id')
            ->select('journal_entries.exchange_rate')
            ->first();

        if ($line === null) {
            return null;
        }

        $rate = (float) $line->exchange_rate;

        return $rate > 0 ? $rate : null;
    }
}
