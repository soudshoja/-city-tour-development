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
 * service sale (this class's own `buildAgentBasisLines()`); `MARKUP_INCOME` (4132, a single
 * non-per-service leaf) is reserved for a genuinely DISTINCT economic event — an explicit markup
 * line added ON TOP OF a fare the invoice already separates from cost (e.g. a ticket priced at
 * IATA fare + an agency-added markup field) — which neither existing feeder currently models (both
 * only ever know one client-facing sell price and one supplier cost, never a separate "fare" and
 * "markup" pair). This class therefore NEVER posts to `MARKUP_INCOME`; a future feeder that really
 * does carry a fare/markup split posts that third figure to `MARKUP_INCOME` itself, independently
 * of this builder. The two purpose codes must never carry the same money on the same document.
 *
 * ── Agent (NET) basis — `SaleDraftInput::BASIS_AGENT` ─────────────────────────────────────────────
 * Blueprint 03 §3 / `ChatController`'s own pre-existing shape, generalized to any service type:
 *   - Dr `RECEIVABLE_CONTROL` = sell (client's open item)
 *   - Cr `SERVICE_PAYABLE`/{type} = cost (supplier's open item)
 *   - Cr `SERVICE_REVENUE`/{type} = margin (sell − cost), sign-aware:
 *     - margin > tolerance: Cr `SERVICE_REVENUE`/{type} = margin (ordinary case).
 *     - |margin| <= tolerance (sold at cost): the leg is OMITTED — the first two legs already
 *       balance on their own (W1.1 fix C2's own reasoning, generalized).
 *     - margin < -tolerance (sold below cost): Dr `SERVICE_REVENUE`/{type} = abs(margin) — a
 *       contra-income debit, same account, opposite side (sign carried by side, never by a
 *       different purpose code or a different `ledgerType`) — so the document still balances
 *       instead of the whole sale being refused. This is w3d-brief.md's own "W4.A rule: company-
 *       borne negative margin posts nothing extra" — the ONLY posting for a negative margin is
 *       this one sign-flipped leg; no separate loss JV belongs in this document.
 *
 * ── Principal (GROSS) basis — `SaleDraftInput::BASIS_PRINCIPAL` ───────────────────────────────────
 * Blueprint's "the agency IS the obligor" case (package tours, own-inventory hotel blocks):
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
 *     ({@see \App\Http\Controllers\InvoiceController}) — deleting those (once every agent-basis
 *     sale posts real cost at sale time, they lose their reason to exist for the negative-margin
 *     case this class already handles sign-aware) is W4.A's own scope per the sale-shape audit, not
 *     this lane's. Until W4.A ships, a negative-margin sale routed through `addJournalEntry()` still
 *     also gets a separate supplier-loss JV from that method's own, untouched `$isSupplierLoss`
 *     branch — a real double-booking of the same economic loss, called out in this lane's own
 *     report rather than silently fixed here (that fix would mean editing `addJournalEntries()`'s
 *     loss-branching logic, out of this lane's scope).
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
 *   - Agent basis: the sign-aware margin leg (`SERVICE_REVENUE`/{type}) becomes `DEFERRED_REVENUE`
 *     (global). `SERVICE_PAYABLE`/{type} is UNCHANGED — agent basis never posts a cost EXPENSE
 *     leg to defer in the first place (the "cost" already sits on the balance sheet as a real
 *     payable from day one, not a P&L line), so there is nothing for a `PREPAID_SUPPLIER_COST`
 *     substitution to apply to on this basis.
 *   - Principal basis: `SERVICE_REVENUE`/{type} (the full-sell credit) becomes `DEFERRED_REVENUE`
 *     (global); `SERVICE_COST`/{type} (the cost-of-sales debit, when the cost pair is posted at
 *     all — see the existing cost<=tolerance omission rule above, unchanged) becomes
 *     `PREPAID_SUPPLIER_COST` (global). `SERVICE_PAYABLE`/{type} is UNCHANGED — the agency's
 *     liability to the actual supplier is real and due regardless of when the AGENCY recognises
 *     its own revenue/cost.
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
     * @return LineDraft[] 2 or 3 lines (agent basis) / 2 or 4 lines (principal basis) — see class
     *                     docblock for exactly which lines are omitted and when.
     */
    public function buildLines(SaleDraftInput $input): array
    {
        $timing = $input->recognitionTiming ?? $this->resolveRecognitionDefault($input->serviceType);

        return $input->postingBasis === SaleDraftInput::BASIS_PRINCIPAL
            ? $this->buildPrincipalBasisLines($input, $timing)
            : $this->buildAgentBasisLines($input, $timing);
    }

    private function buildAgentBasisLines(SaleDraftInput $input, string $timing): array
    {
        $currency = $this->resolveCurrency($input);
        $tolerance = $this->resolveTolerance();

        // P2.5.D: agent basis never posts a SERVICE_COST expense leg (see class docblock), so
        // only the margin leg's purpose code is affected by recognition timing.
        $deferRevenue = $timing === SaleDraftInput::RECOGNITION_AT_TRAVEL;
        $marginPurposeCode = $deferRevenue ? 'DEFERRED_REVENUE' : 'SERVICE_REVENUE';
        $marginServiceType = $deferRevenue ? null : $input->serviceType;

        $lines = [
            new LineDraft(
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
            ),
            new LineDraft(
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
            ),
        ];

        $margin = $input->sellAmount - $input->costAmount;

        if (abs($margin) > $tolerance) {
            $isPositiveMargin = $margin > 0;

            $lines[] = new LineDraft(
                purposeCode: $marginPurposeCode,
                accountId: null,
                side: $isPositiveMargin ? 'credit' : 'debit',
                amount: abs($margin),
                currency: $currency,
                originalAmount: abs($margin),
                exchangeRate: $input->exchangeRate,
                transactionType: $isPositiveMargin ? 'INCOME' : 'CONTRA_INCOME',
                partyAccountRef: $input->agentId,
                description: $isPositiveMargin
                    ? $input->marginPositiveDescription
                    : $input->marginNegativeDescription,
                serviceType: $marginServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                // Both directions are 'income' (a contra-income debit is not a real expense leg) —
                // matches ChatController's own pre-existing convention for this same sign-aware
                // leg, carried over verbatim (see class docblock). Left 'income' even when
                // $deferRevenue swaps the purpose code to DEFERRED_REVENUE (a liability) — this is
                // the LEGACY report-vocabulary category (see LineDraft's own docblock on
                // $ledgerType vs $transactionType), and no existing legacy screen filters on this
                // brand new leaf either way.
                ledgerType: 'income',
                partyName: $input->agentName,
            );
        }

        return $lines;
    }

    private function buildPrincipalBasisLines(SaleDraftInput $input, string $timing): array
    {
        $currency = $this->resolveCurrency($input);
        $tolerance = $this->resolveTolerance();

        // P2.5.D: principal basis defers BOTH the revenue leg and (when a cost pair is posted at
        // all — see the cost<=tolerance omission below, unchanged) the cost leg.
        $deferRevenue = $timing === SaleDraftInput::RECOGNITION_AT_TRAVEL;
        $revenuePurposeCode = $deferRevenue ? 'DEFERRED_REVENUE' : 'SERVICE_REVENUE';
        $revenueServiceType = $deferRevenue ? null : $input->serviceType;
        $costPurposeCode = $deferRevenue ? 'PREPAID_SUPPLIER_COST' : 'SERVICE_COST';
        $costServiceType = $deferRevenue ? null : $input->serviceType;

        $lines = [
            new LineDraft(
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
            ),
            new LineDraft(
                purposeCode: $revenuePurposeCode,
                accountId: null,
                side: 'credit',
                amount: $input->sellAmount,
                currency: $currency,
                originalAmount: $input->sellAmount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'INCOME',
                description: $input->revenueDescription,
                serviceType: $revenueServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'income',
            ),
        ];

        // Cost-of-sales pair — OMITTED when cost isn't known/incurred yet (<=0), matching
        // PostingService's own amount > 0 rule rather than rejecting a sale with no cost figure
        // yet. See class docblock.
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
