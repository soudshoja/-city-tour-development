<?php

namespace App\Enums;

use Illuminate\Support\Facades\Log;

/**
 * CT-A3 E7 (CT-F36 / CT-F31) — the ONE canonical vocabulary for `journal_entries.type`.
 *
 * BACKGROUND (see `.planning/phases/citytravelers-accounting-audit/CT-A2-ENGINE-REPLAY-2026-09-08.md`
 * §5 row 10 / §7.4, and `CT-A1-CENSUS-2026-09-08.md` §3.4): a single column carried two disjoint
 * vocabularies. `PostingService::post()` wrote `$line->ledgerType ?? $line->transactionType` verbatim
 * — the LEGACY report-vocabulary category (what `AccountingController`/`BankPaymentController`
 * filter on) when a feeder set it, else silently falling back to the ENGINE's own internal audit
 * label (`CUSTOMERDEBITED`, `SUPPLIERCREDITED`, `INCOME`, `COSTOFSALES`, …). CT-A1 §3.4 measured the
 * damage: revenue is credited under `type='income'` on 1,742 rows AND under `type='payable'` on
 * 1,935 rows — grouping revenue by `type='income'` returns 48% of revenue. Separately (CT-F31),
 * `AccountingController.php` filters the AP/"Expenses" screen on the literal plural `'expenses'`,
 * which is written ONLY by that screen's own hand-keyed form — every real expense writer stamps the
 * singular `'expense'` — so the screen only ever shows hand-keyed rows.
 *
 * WHY A BACKED ENUM (not a class of constants): the nine members below are exhaustive and closed —
 * this is exactly PHP's use case for a backed string enum over a constants bag. `self::cases()` and
 * `self::tryFrom()` give free, exhaustiveness-checked enumeration and validation that a constants
 * class would have to hand-roll, and `AccountingController`'s `in:` validation rule and `whereIn()`
 * filters both just want `array_column(self::cases(), 'value')` / `self::values()`.
 *
 * THE NINE CANONICAL VALUES — derived from what LIVE feeders actually emit as `LineDraft::$ledgerType`
 * (`grep -rn "ledgerType:\s*'" app/` — every literal value found, 2026-09-09) UNIONED with CT-A1
 * §1.3's measured distinct `journal_entries.type` values that are still genuine, ongoing business
 * categories (not one-off repair-script artefacts — see the "EXCLUDED" list below):
 *
 *   - RECEIVABLE  — client/agent AR movement (Dr/Cr against the receivable control).
 *   - PAYABLE     — supplier/agent AP movement (Dr/Cr against a payable control).
 *   - INCOME      — revenue and other-income recognition (including sign-aware contra-income debits
 *                   — see `SaleDraftBuilder`'s own convention: a negative-margin leg stays `income`,
 *                   it is never reclassified as an expense).
 *   - EXPENSE     — cost-of-sales, commission, salary, bank-charge and other P&L debit lines.
 *   - ADVANCE     — client credit / unapplied-receipt balances (2632-family).
 *   - BANK        — any leg that is itself a bank/cash/instrument movement (cheque, gateway payout,
 *                   manual transfer) rather than a party or P&L classification.
 *   - CHARGES     — gateway/card-processing fee lines (distinct from a general `EXPENSE` — this is
 *                   the CC/gateway-fee-specific bucket the legacy screens already filter on).
 *   - ASSET       — balance-sheet asset movements (fixed assets, prepaid/unbilled supplier cost,
 *                   gateway clearing).
 *   - LIABILITY   — balance-sheet liability movements that are not a plain AP/AR line (e.g. the
 *                   overpayment-held-as-credit disposition).
 *
 * CT-A1 §1.3's 15 measured distinct values, accounted for:
 *   payable, receivable, expense, bank, charges, advance, income, asset — already canonical above.
 *   `expenses` — CT-F31, mapped to `expense` below (`LEGACY_MAP`).
 *   `cash` — legacy synonym, mapped to `bank` below.
 *   `refund` — a real, still-live category written directly by the LEGACY `RefundController` (not
 *     through `LineDraft`/`PostingService` at all — the engine's own `RefundPostingService` never
 *     writes this literal, it decomposes a refund into income/expense/payable/receivable/asset/
 *     liability legs directly). Mapped to `expense` below as the closest single-bucket
 *     approximation for historical rows; not writable going forward once `RefundController`'s raw
 *     writes are retired (out of this ticket's scope — see the files-you-may-touch fence).
 *   `unbilled_cost` — legacy synonym for the engine's own `asset`-side deferred-cost concept (CT-A2
 *     §5 row 3: "the asset only exists because issuance and invoicing were separate events; the
 *     engine has no issuance event"). Mapped to `asset` below for historical-row readability.
 *   `p1a_backfill`, `suspense_adjustment`, `suspense_drawdown`, `cogs_reclass` — CT-A1 §1.3's own
 *     words: "repair-script artefacts", all written directly by `AccountingRepair.php` (a command
 *     `RefusesWhenPostingEngineEnabled` — CT-A2 §5 row 7) between 2026-07-07 and 2026-09-07, never
 *     by `LineDraft`/`PostingService`. Each one is a two-line reclass between two DIFFERENT account
 *     natures (e.g. `p1a_backfill` moves an amount OUT of a COGS-expense leaf INTO the 1430 asset
 *     leaf), so the single `type` value on either line cannot be mapped onto ONE canonical bucket
 *     without misclassifying the other leg. Deliberately left OUT of `LEGACY_MAP`: a lookup miss
 *     against one of these is expected, safe (falls to `resolve()`'s documented default), and
 *     correctly excludes them from the AP/AR "real expense/payable" filter, which is the whole
 *     point of CT-F31's fix — these were never real expense/payable rows to begin with.
 *
 * THE AUDIT-LABEL MAPPING — every `LineDraft::$transactionType` literal found in `app/`
 * (`grep -rn "transactionType:" app/`, 2026-09-09) is mapped in `LEGACY_MAP` to the canonical bucket
 * that matches the PURPOSE CODE / account each specific line actually touches (read from the call
 * site, not guessed from the label's English name — e.g. `AGENTFEELOSSCHARGED` reads as "an expense"
 * but the code raises a RECEIVABLE against the agent, so it is mapped `receivable`; a "*_RECOVERY"
 * label is, by the codebase's own existing convention at `InvoiceController.php:2553`/`:2917`
 * (`AGENTLOSSRECOVERY`/`AGENTFEELOSSRECOVERY` already ship with an explicit `ledgerType: 'income'`),
 * always `income`). Three deliberate simplifications, documented here rather than silently:
 *   - `REVENUE_RECOGNITION` labels FOUR different lines in `RevenueRecognitionService::postRelease()`
 *     (a DEFERRED_REVENUE liability leg, a SERVICE_REVENUE income leg, a PREPAID_SUPPLIER_COST asset
 *     leg, a SERVICE_COST expense leg) — two of the four already carry an explicit `ledgerType`
 *     (`income`/`expense`) and never reach this map; the other two (the liability and asset legs) do
 *     not, and `RevenueRecognitionService.php` is outside this ticket's touchable-files fence, so
 *     they cannot be given their own `ledgerType` here. `REVENUE_RECOGNITION` maps to `income` as the
 *     single best-effort default for those two legs — flagged for a follow-up ticket to add explicit
 *     `ledgerType` at that call site instead of relying on this fallback.
 *   - `BANK_CHARGE` and `MANUAL_JV_DEBIT`/`MANUAL_JV_CREDIT` are each reused for BOTH sides of their
 *     own journal at different call sites; only the side that never carries an explicit `ledgerType`
 *     needs this map, and that side is always the bank/cash leg (`MANUAL_JV_*`) or the
 *     `BANK_CHARGES_EXPENSE` debit leg (`BANK_CHARGE`) — see inline citations below.
 *   - `GATEWAYDEBITED` is used with two DIFFERENT explicit `ledgerType` values at its two existing
 *     call sites (`receivable` at `CheckMyFatoorahPayments.php:378`, `bank` at
 *     `PaymentController.php:7423`) — both already set `ledgerType` explicitly, so neither reaches
 *     this map today. `receivable` is recorded here as the single documented default should a THIRD,
 *     ledgerType-less call site ever appear.
 *   - `VOID_DISPOSITION_*` / `REISSUE_DISPOSITION_*` / `REFUND_DISPOSITION_*` are built by string
 *     concatenation (`'VOID_DISPOSITION_'.strtoupper($policy)`) and EVERY existing call site already
 *     pairs them with an explicit `ledgerType` (verified against `TaskStatusService.php:1619-1622`,
 *     `:2503-2506`, `RefundPostingService.php:1017-1021`) — they cannot reach this map today. Only
 *     the three concrete literals actually written elsewhere without a dynamic suffix
 *     (`*_DISPOSITION_RECEIVABLE`) are listed; the concatenated forms are intentionally NOT
 *     enumerated (there is no closed list of `$policy`/`$disposition` values to enumerate against),
 *     relying instead on `resolve()`'s logged default if that pairing is ever removed.
 *   - `YEAR_END_CLOSE` is deliberately left OUT of `LEGACY_MAP`. A YEC document moves P&L balances to
 *     equity/retained earnings — a movement this vocabulary has no bucket for (no writer emits an
 *     `equity` `ledgerType` today) — and every report that reads `type` already excludes YEC
 *     documents by `doc_type` instead (`TrialBalanceService.php:146-151`), so there is no reader this
 *     omission could silently break. It resolves through `resolve()`'s documented default (logged,
 *     `null`) rather than being force-fit into a misleading bucket.
 *
 * `resolve()`'s DEFAULT for a genuinely unmapped value is `null` (never the raw label): the column
 * is nullable (`2025_03_20_163653_add_type_column_in_general_ledgers_table.php`), so `null` is a
 * truthful "unclassified" signal that safely drops out of every `whereIn('type', [...])` report
 * filter, instead of polluting a real bucket with a guess. Every miss is logged
 * (`accounting.ledger_type.unmapped`) so it surfaces instead of failing silently — this is CT-F36's
 * "handled explicitly and loudly" requirement.
 *
 * NOT a data migration: per this ticket's own instruction (and CT-A2 §7.4's own framing), no
 * existing `journal_entries.type` row is ever rewritten. This class only changes (a) what
 * `PostingService::post()` writes for NEW postings and (b) what the AP/AR report queries filter
 * FOR — a historical row keeps its historical (possibly non-canonical) value forever; the mapping
 * table is what makes that historical value legible to a canonical-keyed filter.
 */
enum LedgerType: string
{
    case RECEIVABLE = 'receivable';
    case PAYABLE = 'payable';
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case ADVANCE = 'advance';
    case BANK = 'bank';
    case CHARGES = 'charges';
    case ASSET = 'asset';
    case LIABILITY = 'liability';

    /**
     * Legacy plural / audit-label (`LineDraft::$transactionType`) values -> canonical member value.
     * See the class docblock for how each row was derived and the deliberate omissions.
     */
    private const LEGACY_MAP = [
        // CT-F31 — the whole reason this ticket exists.
        'expenses' => 'expense',

        // CT-A1 §1.3 measured legacy synonyms (real, ongoing categories only — see class docblock
        // for why the four repair-script artefacts are NOT here).
        'cash' => 'bank',
        'refund' => 'expense',
        'unbilled_cost' => 'asset',

        // Agent loss / fee-loss family (InvoiceController.php:2536-2917).
        'AGENTFEELOSSCHARGED' => 'receivable',
        'AGENTFEELOSSRECOVERY' => 'income',
        'AGENTLOSSCHARGED' => 'receivable',
        'AGENTLOSSRECOVERY' => 'income',

        // Agent commission / salary / settlement family.
        'AGENT_COMMISSION_EXPENSE' => 'expense',
        'AGENT_COMMISSION_PAYABLE' => 'payable',
        'AGENT_LOSS_RECEIVABLE_CREDIT' => 'receivable',
        'AGENT_PROFIT_OFFSET_DEBIT' => 'payable',
        'AGENT_SALARY_EXPENSE' => 'expense',
        'AGENT_SALARY_PAYABLE' => 'payable', // AgentController.php:484 — no explicit ledgerType, reaches this map today.
        'AGENT_SETTLEMENT_GATEWAY_FEE' => 'charges',
        'AGENT_SETTLEMENT_GATEWAY_NET' => 'bank',

        // Bank / cheque / gateway instrument movements.
        'BANKSETTLED' => 'bank',
        'BANK_CHARGE' => 'expense', // BankPaymentController.php:827 debit leg — no ledgerType, reaches this map today.
        'CHEQUE_CLEARED' => 'bank', // ReceiptVoucherController.php:651/659 — no ledgerType, reaches this map today.
        'CHEQUE_ISSUED' => 'bank',
        'GATEWAYCLEARED' => 'bank',
        'GATEWAYDEBITED' => 'receivable', // documented default only — see class docblock.
        'GATEWAYFEERECOVERY' => 'income',
        'GATEWAYFEETRUEUP' => 'charges',
        'GATEWAYSETTLED' => 'bank',
        'PAYMENT' => 'bank',
        'RECEIPT' => 'bank', // ReceiptVoucherController.php / CreditController.php instrument legs — no ledgerType, reaches this map today.
        'RECONCILIATION_FIX' => 'bank', // ReconciliationFixDraftService.php:114/125 — generic gap-fix, defaults to bank/gateway.
        'MANUAL_JV_CREDIT' => 'bank', // AccountingController.php:828/1284 bank leg — no ledgerType, reaches this map today.
        'MANUAL_JV_DEBIT' => 'bank', // AccountingController.php:1082/1279 bank/transfer leg — no ledgerType, reaches this map today.
        'BOUNCE_FEE_RECOVERY' => 'income',
        'CCCHARGES' => 'charges',

        // Client advance / credit family.
        'CLIENT_ADVANCE' => 'advance', // ReceiptVoucherController.php / CreditController.php — no ledgerType, reaches this map today.
        'CLIENT_CREDIT_REFUND_ADVANCE' => 'advance',
        'CLIENT_CREDIT_REFUND_PAYOUT' => 'bank',

        // Company-level loss.
        'COMPANYFEELOSS' => 'expense',
        'COMPANYLOSS' => 'expense',

        // Sale / cost-of-sales core (matches this ticket's own worked examples).
        'CONTRA_INCOME' => 'income',
        'COSTOFSALES' => 'expense',
        'CUSTOMERCREDITED' => 'receivable',
        'CUSTOMERDEBITED' => 'receivable',
        'INCOME' => 'income',
        'SUPPLIERCREDITED' => 'payable',
        'SUPPLIERDEBITED' => 'payable',
        'SUPPLIER_CHARGE' => 'expense',
        'SUPPLIER_CHARGE_TAX' => 'expense',
        'UNBILLED_SUPPLIER_COST' => 'asset',

        // Fixed assets.
        'FIXED_ASSET_ACQUISITION' => 'asset', // no ledgerType, reaches this map today.
        'FIXED_ASSET_DEPRECIATION' => 'expense', // no ledgerType, reaches this map today.
        'FIXED_ASSET_DISPOSAL' => 'asset', // no ledgerType, reaches this map today.

        // Invoice charge lines.
        'INVOICECHARGEINCOME' => 'payable', // InvoiceController.php:8190 — trust the code, not the name.
        'INVOICECHARGERECEIVABLE' => 'receivable',

        // FX realisation (RealisedFxService.php almost always sets an explicit dynamic ledgerType).
        'REALISEDFX' => 'income',

        // Refund family (RefundPostingService.php).
        'REFUND_AGENT_COMMISSION_EXPENSE' => 'expense',
        'REFUND_AGENT_COMMISSION_PAYABLE' => 'payable',
        'REFUND_AIRLINE_CLAWBACK' => 'expense',
        'REFUND_AIRLINE_CLAWBACK_PAYABLE' => 'payable',
        'REFUND_CRN_RECEIVABLE_REVERSAL' => 'receivable',
        'REFUND_CRN_REVENUE_REVERSAL' => 'income',
        'REFUND_DISPOSITION_RECEIVABLE' => 'receivable',
        'REFUND_GATEWAY_PAYOUT_ADVANCE' => 'liability',
        'REFUND_GATEWAY_PAYOUT_AR' => 'receivable',
        'REFUND_GATEWAY_PAYOUT_CLEARING' => 'asset',
        'REFUND_RECHARGE_FEE_INCOME' => 'income',
        'REFUND_RECHARGE_RECEIVABLE' => 'receivable',
        'REFUND_RECHARGE_RECOVERY' => 'income',
        'REFUND_SUPPLIER_CREDIT_COGS' => 'expense',
        'REFUND_SUPPLIER_CREDIT_GAIN' => 'expense', // RefundPostingService.php:534 — trust the code, not the name.
        'REFUND_SUPPLIER_CREDIT_PAYABLE' => 'payable',
        'REFUND_SUPPLIER_CREDIT_PENALTY' => 'expense',

        // Reissue fee family (TaskStatusService.php).
        'REISSUE_DISPOSITION_RECEIVABLE' => 'receivable',
        'REISSUE_FEE_COMMISSION_EXPENSE' => 'expense',
        'REISSUE_FEE_COMMISSION_PAYABLE' => 'payable',
        'REISSUE_FEE_INCOME' => 'income',
        'REISSUE_FEE_RECEIVABLE' => 'receivable',

        // Deferred-revenue release (RevenueRecognitionService.php) — see class docblock's caveat.
        'REVENUE_RECOGNITION' => 'income',

        // Void fee family (TaskStatusService.php / DotwAI/AccountingService.php).
        'VOID_DISPOSITION_RECEIVABLE' => 'receivable',
        'VOID_FEE_COMMISSION_EXPENSE' => 'expense',
        'VOID_FEE_COMMISSION_PAYABLE' => 'payable',
        'VOID_FEE_INCOME' => 'income',
        'VOID_FEE_RECEIVABLE' => 'receivable',

        // Note: 'YEAR_END_CLOSE' is intentionally absent — see class docblock.
    ];

    /**
     * All canonical values, e.g. for a validation rule or a `whereIn()`.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * CT-F36's actual fix: resolve whatever `PostingService::post()` would otherwise have written
     * verbatim (`$ledgerType ?? $transactionType`) into the ONE canonical vocabulary.
     *
     * - Already-canonical values pass through unchanged (the common case for every feeder that
     *   already sets `LineDraft::$ledgerType` to one of the nine members).
     * - A known legacy/plural/audit-label value resolves through {@see self::LEGACY_MAP}.
     * - A genuinely unrecognised value is logged and resolved to `null` — never written raw. The
     *   column is nullable; `null` is a truthful "unclassified" signal, safe in every
     *   `whereIn('type', [...])` filter, instead of a guess that pollutes a real bucket.
     *
     * @param  string  $context  free-text call-site tag for the log line (e.g. 'PostingService::post').
     */
    public static function resolve(?string $ledgerType, ?string $transactionType, string $context = 'PostingService::post'): ?string
    {
        $raw = $ledgerType ?? $transactionType;

        if ($raw === null || $raw === '') {
            return null;
        }

        if (self::tryFrom($raw) !== null) {
            return $raw;
        }

        if (array_key_exists($raw, self::LEGACY_MAP)) {
            return self::LEGACY_MAP[$raw];
        }

        Log::warning('accounting.ledger_type.unmapped', [
            'raw' => $raw,
            'context' => $context,
        ]);

        return null;
    }

    /**
     * Every raw `journal_entries.type` value (canonical, legacy-mapped, or the deliberately
     * unmapped repair-script artefacts) that should count as "expense" for a report filter — i.e.
     * CT-F31's fix for the AP/"Expenses" screen: the plural legacy synonym plus the canonical value
     * itself, so both old, hand-keyed rows and every real expense writer show up together.
     *
     * @return list<string>
     */
    public static function expenseFilterValues(): array
    {
        return [self::EXPENSE->value, 'expenses'];
    }

    /**
     * Every raw `journal_entries.type` value that should count as "payable" for a report filter.
     *
     * @return list<string>
     */
    public static function payableFilterValues(): array
    {
        return [self::PAYABLE->value];
    }
}
