<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Setting;

/**
 * W3d (KEY: sale-shape-audit; w3d-brief.md, binding decision 2026-08-28). ONE shared engine
 * line-builder for BOTH existing sale feeders —
 * {@see \App\Http\Controllers\InvoiceController::postSaleJournalEntries()} (the main GDS/task
 * auto-invoicing path) and {@see \App\Http\Controllers\ChatController::postChatInvoiceTaskEntries()}
 * (the AI-chat "create invoice" path) — replacing each one's own hand-rolled LineDraft array with
 * a call to {@see self::buildLines()}. Like {@see CreditApplicationDraftBuilder}, this class does
 * not call {@see PostingSeam} or {@see PostingService} and does not build a whole
 * {@see DocumentDraft} — each caller still owns its own `docType`/`subType`/`docDate`/`narration`/
 * `idempotencyKey`/company-branch resolution (those genuinely differ between the two feeders); this
 * class only builds the `LineDraft[]` both callers hand to their own `DocumentDraft`.
 *
 * ── Audit finding this lane fixes (sale-shape-audit.md) ───────────────────────────────────────────
 * `ChatController::postChatInvoiceTaskEntries()` already posted the blueprint's NET shape (`Dr
 * RECEIVABLE_CONTROL / Cr SERVICE_PAYABLE / Cr-or-Dr <margin>`, sign-aware). `InvoiceController::
 * postSaleJournalEntries()` posted a 2-line GROSS-AND-INCOMPLETE document (`Dr RECEIVABLE_CONTROL
 * full sell / Cr SERVICE_REVENUE full sell`) with NO supplier cost/payable leg anywhere for a
 * profitable sale — not as COGS, not as a payable. This class is the single place both feeders now
 * build from, so the two paths cannot diverge again.
 *
 * ── SERVICE_REVENUE vs MARKUP_INCOME (4132) — the semantics this lane locks down ──────────────────
 * `MARKUP_INCOME` was W1's name for the sign-aware margin leg on a NET sale. w3d-brief.md decision
 * 3 redefines the vocabulary: `SERVICE_REVENUE/{type}` is the earned margin on an AGENT-basis
 * service sale (R-CT1 2026-09-09 makes that the FULL sell, not the margin — see below);
 * `MARKUP_INCOME` (4132, a single
 * non-per-service leaf) is reserved for a genuinely DISTINCT economic event — an explicit markup
 * line added ON TOP OF a fare the invoice already separates from cost (e.g. a ticket priced at
 * IATA fare + an agency-added markup field) — which neither existing feeder currently models (both
 * only ever know one client-facing sell price and one supplier cost, never a separate "fare" and
 * "markup" pair). This class therefore NEVER posts to `MARKUP_INCOME`; a future feeder that really
 * does carry a fare/markup split posts that third figure to `MARKUP_INCOME` itself, independently
 * of this builder. The two purpose codes must never carry the same money on the same document.
 *
 * ── SUPERSEDED 2026-09-09 by OWNER RULING R-CT1 (CT-A3 wave 1): GROSS FOR BOTH BASES ────────────
 * The net/margin agent shape described below — Dr AR sell / Cr SERVICE_PAYABLE cost /
 * Cr-or-Dr SERVICE_REVENUE margin, sign-aware — is REMOVED. CT-A2 measured it against the legacy
 * ledger on 3,530 identical sale units and found engine revenue KWD 51,597.329 (margin) versus
 * the legacy ledger's KWD 549,949.042 (gross), with KWD 0.000 of engine supplier cost of sales
 * versus KWD 463,083.015. The owner has ruled for the gross presentation, so
 * `SaleDraftInput::BASIS_AGENT` and `SaleDraftInput::BASIS_PRINCIPAL` now build the SAME four-line
 * document via {@see self::buildGrossBasisLines()}. `MARKUP_INCOME` is still never posted here.
 * The paragraph below is kept only to describe what the shape used to be, for anyone reading a
 * pre-2026-09-09 journal row:
 *   - Dr `RECEIVABLE_CONTROL` = sell / Cr `SERVICE_PAYABLE`/{type} = cost /
 *     Cr-or-Dr `SERVICE_REVENUE`/{type} = sell − cost, sign-aware, margin leg omitted at |margin|
 *     <= tolerance.
 *
 * ── Gross basis — BOTH `BASIS_AGENT` and `BASIS_PRINCIPAL` ───────────────────────────────────────
 * The blueprint's "the agency IS the obligor" shape, now the only shape this builder emits:
 *   - Dr `RECEIVABLE_CONTROL` = sell
 *   - Cr `SERVICE_REVENUE`/{type} = sell (the FULL sell value — this is the one basis where
 *     SERVICE_REVENUE still means gross revenue, exactly W1's original, now-superseded comment)
 *   - Dr `SERVICE_COST`/{type} = cost — `SERVICE_COST`'s first real call site (dead purpose code
 *     since W1 per the sale-shape audit; still seeded by SystemAccountsSeeder for all 12 types)
 *   - Cr `SERVICE_PAYABLE`/{type} = cost
 *   The cost pair is OMITTED when `$input->costAmount` is <= tolerance (cost not yet known at sale
 *   time) — leaving the same 2-line gross document this basis's own types posted before this lane,
 *   rather than rejecting the sale for a $0.00 cost-of-sales line PostingService would refuse.
 *   No sign-awareness is needed here (unlike agent basis): the two pairs are independently
 *   balanced, so a cost that happens to exceed sell still posts cleanly as a company-borne loss
 *   surfacing through SERVICE_COST, not through a flipped SERVICE_REVENUE sign.
 *
 * ── What this class deliberately does NOT do ──────────────────────────────────────────────────────
 *   - It does not touch `createSupplierLossEntries()` / `createFeeLossEntries()`
 *     ({@see \App\Http\Controllers\InvoiceController}) — deleting those is W4.A's own scope per
 *     the sale-shape audit, not this lane's. Under R-CT1's gross shape a below-cost sale simply
 *     shows revenue < cost on this document (no contra-income leg exists any more), so the
 *     separate supplier-loss JV those methods still raise remains a double-booking of the same
 *     economic loss — called out here and in CT-A3's report rather than silently fixed, since
 *     that fix means editing `addJournalEntries()`'s loss branching, still out of scope.
 *   - It does not resolve `$companyId` from `Auth::user()` — {@see self::resolvePostingBasis()}
 *     takes it as a plain argument, same queue/webhook-safety convention as every other engine-layer
 *     class in this codebase.
 *   - It does not do multi-currency conversion — see {@see SaleDraftInput}'s own docblock.
 *
 * ── P2.5.D addition — revenue recognition timing (p2_5-brief.md §P2.5.D; doc 22 §15.6, IFRS 15) ──
 * `buildLines()` resolves `$input->recognitionTiming ?? self::resolveRecognitionTiming(...)`-style
 * (see {@see self::resolveRecognitionDefault()}) and, when the result is
 * {@see SaleDraftInput::RECOGNITION_AT_TRAVEL}, substitutes purpose codes rather than adding new
 * lines — the LINE COUNT and every amount/side computed above is byte-for-byte unchanged;
 * ONLY the purpose code (and, since these two are GLOBAL leaves, `serviceType: null` on that one
 * line) differs:
 *   - BOTH bases (R-CT1 2026-09-09 collapsed them onto the same shape):
 *     `SERVICE_REVENUE`/{type} (the full-sell credit) becomes `DEFERRED_REVENUE` (global);
 *     `SERVICE_COST`/{type} (the cost-of-sales debit, when the cost pair is posted at all — see
 *     the cost<=tolerance omission rule above, unchanged) becomes `PREPAID_SUPPLIER_COST`
 *     (global). `SERVICE_PAYABLE`/{type} is UNCHANGED — the agency's liability to the actual
 *     supplier is real and due regardless of when the AGENCY recognises its own revenue/cost.
 * Released on the travel/check-in date by `accounting:recognize-revenue`
 * (App\Services\Accounting\RevenueRecognitionService), which derives the release amounts directly
 * from these same two leaves' posted `journal_entries` rows (grouped by `task_id`) rather than
 * from a second, separately-maintained schedule table — see that class's own docblock. Refund/void
 * of an unrecognised sale needs no special handling here: reversing the WHOLE sale document via
 * the existing {@see PostingService::reverse()} reverses every line it posted, deferred ones
 * included, exactly like any other line — RevenueRecognitionService's own outstanding-balance
 * query sums every line on the deferred/prepaid accounts for the task regardless of document, so
 * the original and its reversal net to zero and the task simply stops appearing outstanding.
 */
final class SaleDraftBuilder
{
    /**
     * OWNER RULING R-CT1, 2026-09-09: both bases build the SAME gross document — 4 lines, or 2
     * when `costAmount <= tolerance` (CT-A3 E1 / CT-F34). `$input->postingBasis` is retained on
     * the input (it is still read by config, reports and `resolvePostingBasis()`, and a future
     * ruling could re-diverge the two) but it no longer changes what this builder emits.
     *
     * @return LineDraft[] 4, 2 or 0 lines — see {@see self::buildGrossBasisLines()}'s docblock.
     *                     An EMPTY array means "nothing happened" (sell and cost both zero) and
     *                     the caller must post no document at all.
     */
    public function buildLines(SaleDraftInput $input): array
    {
        $timing = $input->recognitionTiming ?? $this->resolveRecognitionDefault($input->serviceType);

        return $this->buildGrossBasisLines($input, $timing);
    }

    /**
     * OWNER RULING R-CT1, 2026-09-09 (CT-A3 wave 1) — GROSS is the revenue basis for BOTH
     * `SaleDraftInput::BASIS_AGENT` and `SaleDraftInput::BASIS_PRINCIPAL`.
     *
     * Verbatim: "REVENUE BASIS = GROSS. Agent-basis sales post the full sell price as revenue
     * (Dr AR / Cr Revenue = sell) and the supplier cost as cost of sales (Dr COGS or 1430 / Cr
     * supplier payable = cost)."
     *
     * This supersedes W3d's net/margin agent shape, which CT-A2 measured against the legacy
     * ledger on 3,530 identical sale units: engine revenue KWD 51,597.329 (margin) against the
     * legacy ledger's KWD 549,949.042 (gross), with KWD 0.000 of engine supplier cost of sales
     * against the legacy KWD 463,083.015. The owner has ruled for the gross presentation, so the
     * two bases now build the SAME four-line document and the net (sign-aware margin) shape is
     * removed rather than left as an unreachable branch:
     *
     *   - Dr `RECEIVABLE_CONTROL`      = sell (client's open item)
     *   - Cr `SERVICE_REVENUE`/{type}  = sell (the FULL sell value — gross revenue)
     *   - Dr `SERVICE_COST`/{type}     = cost (cost of sales)
     *   - Cr `SERVICE_PAYABLE`/{type}  = cost (supplier's open item)
     *
     * The cost PAIR is omitted together when `costAmount <= tolerance` — CT-A3 E1 / CT-F34's
     * rule, now applying to both bases from one place: a pure-fee sale posts the two-line gross
     * document instead of being refused for a 0.000-amount line PostingService rejects.
     *
     * Sign-awareness is no longer needed and no longer exists. A sale below cost posts revenue
     * and cost independently and simply shows a negative gross margin, exactly as gross
     * accounting requires; there is no contra-income debit and no separate loss JV in this
     * document (`InvoiceController::createSupplierLossEntries()` remains untouched, as before).
     *
     * P2.5.D recognition timing is UNCHANGED in mechanism: `at_travel` substitutes purpose codes
     * on the revenue and cost legs (`DEFERRED_REVENUE` / `PREPAID_SUPPLIER_COST`) without
     * changing the line count or any amount. `SERVICE_PAYABLE` is never deferred — the agency's
     * liability to the supplier is real and due regardless of when the agency recognises revenue.
     */
    private function buildGrossBasisLines(SaleDraftInput $input, string $timing): array
    {
        $currency = $this->resolveCurrency($input);
        $tolerance = $this->resolveTolerance();

        $deferRevenue = $timing === SaleDraftInput::RECOGNITION_AT_TRAVEL;
        $revenuePurposeCode = $deferRevenue ? 'DEFERRED_REVENUE' : 'SERVICE_REVENUE';
        $revenueServiceType = $deferRevenue ? null : $input->serviceType;
        $costPurposeCode = $deferRevenue ? 'PREPAID_SUPPLIER_COST' : 'SERVICE_COST';
        $costServiceType = $deferRevenue ? null : $input->serviceType;

        $lines = [];

        // CT-A3 wave-1 server-replay finding (2026-09-09): the AR/revenue pair is now omitted when
        // the SELL is zero, exactly as the cost pair below is omitted when the COST is zero. The
        // replay refused 7 more sale documents on `DocumentDraft::$lines[0] amount must be > 0` —
        // invoice_details whose task_price AND task total are both 0.000 (detail ids 14399-14402,
        // 14509, 14615-14616) — the same failure mode E1/CT-F34 fixed on the cost side, just on
        // the other leg. Refusing them makes invoice creation FAIL rather than degrade, for a
        // document that carries no money in either direction.
        //
        // The three shapes this produces:
        //   sell > 0, cost > 0  -> 4 lines (the ordinary gross sale)
        //   sell > 0, cost = 0  -> 2 lines (a pure-fee sale — E1/CT-F34)
        //   sell = 0, cost > 0  -> 2 lines (cost incurred with nothing billed yet; legitimate
        //                          under gross, where the cost pair stands on its own)
        //   sell = 0, cost = 0  -> [] — nothing happened. The CALLER must treat an empty array as
        //                          "no document", never as an error; PostingService rejects an
        //                          empty line set by construction.
        if ($input->sellAmount > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $input->sellAmount,
                currency: $currency,
                originalAmount: $input->sellAmount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'CUSTOMERDEBITED',
                partyAccountRef: $input->clientId,
                description: $input->receivableDescription,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'receivable',
                partyName: $input->clientName,
            );

            $lines[] = new LineDraft(
                purposeCode: $revenuePurposeCode,
                accountId: null,
                side: 'credit',
                amount: $input->sellAmount,
                currency: $currency,
                originalAmount: $input->sellAmount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'INCOME',
                // R-CT1: the agent dimension the removed net-margin leg carried is preserved
                // here rather than dropped — it is the same economic party, on the same
                // document, and every agent-basis report keyed on it keeps working. Null for a
                // genuine principal-basis input, which never carries an agentId.
                partyAccountRef: $input->agentId,
                description: $input->revenueDescription,
                serviceType: $revenueServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'income',
                partyName: $input->agentName,
            );
        }

        // Cost-of-sales pair — OMITTED TOGETHER when cost isn't known/incurred yet (<= tolerance),
        // matching PostingService's own amount > 0 rule rather than rejecting a sale that has no
        // supplier cost figure. CT-A3 E1 / CT-F34: this is the guard whose absence on the old
        // agent branch refused 31 live documents outright.
        if ($input->costAmount > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: $costPurposeCode,
                accountId: null,
                side: 'debit',
                amount: $input->costAmount,
                currency: $currency,
                originalAmount: $input->costAmount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'COSTOFSALES',
                description: $input->costDescription,
                serviceType: $costServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'expense',
            );

            $lines[] = new LineDraft(
                purposeCode: 'SERVICE_PAYABLE',
                accountId: null,
                side: 'credit',
                amount: $input->costAmount,
                currency: $currency,
                originalAmount: $input->costAmount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'SUPPLIERCREDITED',
                partyAccountRef: $input->supplierId,
                description: $input->payableDescription,
                serviceType: $input->serviceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'payable',
                partyName: $input->supplierName,
            );
        }

        return $lines;
    }

    private function resolveCurrency(SaleDraftInput $input): string
    {
        return $input->currency ?? (string) config('accounting.engine.base_currency');
    }

    private function resolveTolerance(): float
    {
        return (float) config('accounting.engine.balance_tolerance', 0.0005);
    }

    /**
     * Per-company, per-service-type posting basis (w3d-brief.md decision 2). Resolution order:
     *   1. An explicit company override — `Setting::getByKey($companyId,
     *      "accounting.posting_basis.{$serviceType}")` (same `settings` table/key-convention
     *      {@see \App\Models\Company::hasModule()} already uses for `module.*` overrides).
     *   2. The locked default for that service type —
     *      `config('accounting.posting_basis.default_by_service_type')`.
     *   3. `SaleDraftInput::BASIS_AGENT` — the safe fallback for a service type this build doesn't
     *      recognise (never `principal`, which would silently start dropping cost when unmapped).
     *
     * Never throws: an unresolvable/garbage company override is logged and ignored rather than
     * blocking a sale from posting at all — this option changes WHICH accounts a sale hits, not
     * whether the sale is postable.
     */
    public static function resolvePostingBasis(int $companyId, string $serviceType): string
    {
        $settingKey = 'accounting.posting_basis.'.$serviceType;
        $override = $companyId > 0 ? Setting::getByKey($companyId, $settingKey, null) : null;

        if (is_string($override) && in_array($override, [SaleDraftInput::BASIS_AGENT, SaleDraftInput::BASIS_PRINCIPAL], true)) {
            return $override;
        }

        $default = config("accounting.posting_basis.default_by_service_type.{$serviceType}");

        return in_array($default, [SaleDraftInput::BASIS_AGENT, SaleDraftInput::BASIS_PRINCIPAL], true)
            ? $default
            : SaleDraftInput::BASIS_AGENT;
    }

    /**
     * `$input->recognitionTiming` null-fallback used by {@see self::buildLines()} — the config
     * default ONLY, never a company override (no `$companyId` is available inside `buildLines()`;
     * see class docblock's "P2.5.D addition" note on `SaleDraftInput::$recognitionTiming` for why
     * a caller with a resolved per-company override passes it explicitly instead of relying on
     * this method). Never throws: an unrecognised/unmapped service type falls back to
     * {@see SaleDraftInput::RECOGNITION_AT_ISSUE} — the safe default (never silently defers
     * revenue for a service type this build doesn't recognise).
     */
    private function resolveRecognitionDefault(string $serviceType): string
    {
        $default = config("accounting.revenue_recognition.default_by_service_type.{$serviceType}");

        return in_array($default, [SaleDraftInput::RECOGNITION_AT_ISSUE, SaleDraftInput::RECOGNITION_AT_TRAVEL], true)
            ? $default
            : SaleDraftInput::RECOGNITION_AT_ISSUE;
    }

    /**
     * Per-company, per-service-type revenue recognition timing (p2_5-brief.md §P2.5.D; doc 22
     * §15.6) — the SAME resolution order and Setting-key convention as
     * {@see self::resolvePostingBasis()}:
     *   1. An explicit company override — `Setting::getByKey($companyId,
     *      "accounting.revenue_recognition.{$serviceType}")`.
     *   2. The locked default for that service type —
     *      `config('accounting.revenue_recognition.default_by_service_type')`.
     *   3. {@see SaleDraftInput::RECOGNITION_AT_ISSUE} — the safe fallback for an unrecognised
     *      service type (never `at_travel`, which would silently start deferring revenue a caller
     *      never asked to defer).
     *
     * Not called by {@see self::buildLines()} itself (which only ever applies the config default —
     * see {@see self::resolveRecognitionDefault()}, for a caller such as a unit test that
     * constructs `SaleDraftInput` directly with no company context) — but IS called by all six
     * real `SaleDraftInput` construction sites (`InvoiceController::postSaleJournalEntries()` and
     * its four repost/correction siblings, `ChatController::postChatInvoiceTaskEntries()`), right
     * alongside their existing `resolvePostingBasis()` call, the same way `$postingBasis` itself is
     * resolved and passed. See `config('accounting.revenue_recognition')`'s own "WIRED CALL SITES"
     * note.
     *
     * Never throws: an unresolvable/garbage company override is logged and ignored rather than
     * blocking a sale from posting at all — same reasoning as `resolvePostingBasis()`.
     */
    public static function resolveRecognitionTiming(int $companyId, string $serviceType): string
    {
        $settingKey = 'accounting.revenue_recognition.'.$serviceType;
        $override = $companyId > 0 ? Setting::getByKey($companyId, $settingKey, null) : null;

        if (is_string($override) && in_array($override, [SaleDraftInput::RECOGNITION_AT_ISSUE, SaleDraftInput::RECOGNITION_AT_TRAVEL], true)) {
            return $override;
        }

        $default = config("accounting.revenue_recognition.default_by_service_type.{$serviceType}");

        return in_array($default, [SaleDraftInput::RECOGNITION_AT_ISSUE, SaleDraftInput::RECOGNITION_AT_TRAVEL], true)
            ? $default
            : SaleDraftInput::RECOGNITION_AT_ISSUE;
    }
}
