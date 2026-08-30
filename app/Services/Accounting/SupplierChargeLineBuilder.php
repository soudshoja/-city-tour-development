<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\SupplierChargeRule;
use Illuminate\Support\Facades\Log;

/**
 * W6.C (w6-brief.md "W6.C — Supplier-side charges" item 4; supplier-charges-design.md Table 4).
 * Turns a set of resolved, active {@see SupplierChargeRule}s
 * ({@see SupplierChargeRuleResolver::resolveApplicable()}) into the extra {@see LineDraft}s a
 * sale document must carry — NEVER blended into {@see SaleDraftBuilder}'s own base sell/cost pair
 * (w6-brief.md: "emit each matching rule as its OWN separate LineDraft pair").
 *
 * ── Shape per rule ─────────────────────────────────────────────────────────────────────────────────
 *   - `Dr {cost purpose} / Cr SERVICE_PAYABLE` — the cost itself. `{cost purpose}` is
 *     `SUPPLIER_CHARGE_EXPENSE` (5128, global — no per-service leaf exists for this cost family)
 *     on AGENT basis, or `SERVICE_COST`/{type} (the same per-service COGS leaf
 *     {@see SaleDraftBuilder::buildPrincipalBasisLines()} already uses for the base cost) on
 *     PRINCIPAL basis — unless `$rule->cost_account` names an explicit purpose-code override, in
 *     which case that string is used verbatim (still resolved by {@see AccountResolver}, never by
 *     account id).
 *   - `Dr RECEIVABLE_CONTROL / Cr SUPPLIER_CHARGE_RECHARGE_INCOME` (4137) — ONLY when
 *     `recharge_policy=recharge_client`. `recharge_policy=recharge_agent` posts NOTHING here — it
 *     is deducted via {@see \App\Models\AgentCharge}'s existing `charge_bearer` mechanism instead,
 *     a downstream concern this class does not touch (w6-brief.md: "no second bearer matrix").
 *     `recharge_policy=absorb` also posts nothing beyond the cost pair — the fee is a pure company
 *     cost.
 *
 * ── Rule 1e (commission base excludes the fee) ───────────────────────────────────────────────────
 * This class NEVER emits a `SERVICE_REVENUE`-purpose line for a supplier charge rule, regardless
 * of `$rule->commissionable` — that flag is stored on the rule for a downstream commission
 * calculator to consult (not built in this sub-wave), but structurally, by construction, no
 * per-service commission-margin JV that reads only `SERVICE_REVENUE`-purpose lines can ever
 * include a supplier-charge-rule amount as commissionable income from THIS builder's own output —
 * see the class's own test suite for the exact assertion.
 *
 * ── Tax (`$rule->tax_code`) ───────────────────────────────────────────────────────────────────────
 * `supplier_charge_rules` carries a `tax_code` column but, matching Table 3's own note ("Kuwait:
 * no VAT today ... rules still carry a tax_code, default null/exempt"), NO tax-rate column — there
 * is nothing in today's schema to compute a tax AMOUNT from. This method therefore accepts the tax
 * amount as an explicit, caller-supplied figure (`$taxAmounts`, keyed by rule id) rather than
 * inventing a rate; when a rule's tax_code is set AND a positive amount is supplied for it, a
 * SEPARATE 2-line pair posts (`Dr {cost purpose} / Cr SERVICE_PAYABLE`, tagged
 * `SUPPLIER_CHARGE_TAX`) — never folded into the fee's own line. No caller in this codebase
 * supplies one yet (no VAT lane exists), so in practice this is a documented no-op today, ready
 * for a future VAT lane to populate `$taxAmounts` without changing this method's contract.
 */
final class SupplierChargeLineBuilder
{
    public function __construct(
        private readonly SupplierChargeRuleResolver $resolver = new SupplierChargeRuleResolver(),
    ) {}

    /**
     * @param  array<string, SupplierChargeRule>  $rules  keyed by charge_kind, as returned by
     *                                                     {@see SupplierChargeRuleResolver::resolveApplicable()}.
     * @param  array<int, float>  $overrideAmounts  keyed by rule id — manual per-task override
     *                                              (see {@see SupplierChargeRuleResolver::applyManualOverride()}).
     * @param  array<int, float>  $taxAmounts  keyed by rule id — see class docblock's "Tax" section.
     * @param  bool  $overridesApproved  passed through to applyManualOverride() for every override
     *                                   present in $overrideAmounts — see that method's own docblock.
     * @return LineDraft[]
     *
     * @throws \App\Exceptions\Accounting\SupplierChargeOverridePendingApprovalException propagated
     *                                                                                    from applyManualOverride() when an override needs approval that hasn't happened yet.
     */
    public function buildLines(
        array $rules,
        SupplierChargeLineInput $input,
        array $overrideAmounts = [],
        array $taxAmounts = [],
        bool $overridesApproved = false
    ): array {
        $lines = [];

        foreach ($rules as $rule) {
            if ($this->resolver->hasAlreadyFired($rule, $input->reference)) {
                Log::info('accounting.supplier_charge_rule_skipped_once_per_reference', [
                    'supplier_charge_rule_id' => $rule->id,
                    'reference' => $input->reference,
                    'task_id' => $input->taskId,
                ]);

                continue;
            }

            $resolvedAmount = $this->computeAmount($rule, $input);

            $overrideAmount = $overrideAmounts[$rule->id] ?? null;
            $amount = $this->resolver->applyManualOverride(
                $rule,
                $resolvedAmount,
                $overrideAmount,
                $input->companyId,
                $overridesApproved
            );

            $amount = round($amount, $this->resolveDecimals());

            if ($amount <= $this->resolveTolerance()) {
                continue;
            }

            array_push($lines, ...$this->buildCostPair($rule, $input, $amount));

            if ($rule->recharge_policy === SupplierChargeRule::RECHARGE_CLIENT) {
                array_push($lines, ...$this->buildRechargePair($rule, $input, $amount));
            }

            $taxAmount = round((float) ($taxAmounts[$rule->id] ?? 0.0), $this->resolveDecimals());

            if ($rule->tax_code !== null && $taxAmount > $this->resolveTolerance()) {
                array_push($lines, ...$this->buildTaxPair($rule, $input, $taxAmount));
            }
        }

        return $lines;
    }

    public function computeAmount(SupplierChargeRule $rule, SupplierChargeLineInput $input): float
    {
        $amount = match ($rule->basis) {
            SupplierChargeRule::BASIS_FIXED => $rule->amount,
            SupplierChargeRule::BASIS_PERCENT_OF_FARE => $rule->amount / 100 * $input->fareAmount,
            SupplierChargeRule::BASIS_PERCENT_OF_TOTAL => $rule->amount / 100 * $input->totalAmount,
            SupplierChargeRule::BASIS_PER_PASSENGER => $rule->amount * $input->passengerCount,
            SupplierChargeRule::BASIS_PER_SEGMENT => $rule->amount * $input->segmentCount,
            default => throw new \InvalidArgumentException("Unknown supplier_charge_rules.basis '{$rule->basis}' for rule #{$rule->id}."),
        };

        return round($amount, $this->resolveDecimals());
    }

    /**
     * @return LineDraft[] exactly 2 -- Dr {cost purpose} / Cr SERVICE_PAYABLE.
     */
    private function buildCostPair(SupplierChargeRule $rule, SupplierChargeLineInput $input, float $amount): array
    {
        $currency = $this->resolveCurrency($input);
        [$costPurposeCode, $costServiceType] = $this->resolveCostPurpose($rule, $input->postingBasis, $input->serviceType);

        $reference = $this->describeRule($rule);

        return [
            new LineDraft(
                purposeCode: $costPurposeCode,
                accountId: null,
                side: 'debit',
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'SUPPLIER_CHARGE',
                description: "Supplier charge ({$rule->charge_kind}) — {$reference}",
                serviceType: $costServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'expense',
            ),
            new LineDraft(
                purposeCode: 'SERVICE_PAYABLE',
                accountId: null,
                side: 'credit',
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'SUPPLIERCREDITED',
                partyAccountRef: $input->supplierId,
                description: "Supplier charge payable ({$rule->charge_kind}) — {$reference}",
                serviceType: $input->serviceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'payable',
                partyName: $input->supplierName,
            ),
        ];
    }

    /**
     * @return LineDraft[] exactly 2 -- Dr RECEIVABLE_CONTROL / Cr SUPPLIER_CHARGE_RECHARGE_INCOME.
     */
    private function buildRechargePair(SupplierChargeRule $rule, SupplierChargeLineInput $input, float $amount): array
    {
        $currency = $this->resolveCurrency($input);
        $reference = $this->describeRule($rule);

        return [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'CUSTOMERDEBITED',
                partyAccountRef: $input->clientId,
                description: "Supplier charge recharge ({$rule->charge_kind}) — {$reference}",
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'receivable',
                partyName: $input->clientName,
            ),
            new LineDraft(
                purposeCode: 'SUPPLIER_CHARGE_RECHARGE_INCOME',
                accountId: null,
                side: 'credit',
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'INCOME',
                description: "Supplier charge recharge income ({$rule->charge_kind}) — {$reference}",
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'income',
            ),
        ];
    }

    /**
     * @return LineDraft[] exactly 2 -- same shape as the cost pair, tagged SUPPLIER_CHARGE_TAX.
     *                     See class docblock's "Tax" section for why this is caller-supplied, not
     *                     computed from a rate.
     */
    private function buildTaxPair(SupplierChargeRule $rule, SupplierChargeLineInput $input, float $taxAmount): array
    {
        $currency = $this->resolveCurrency($input);
        [$costPurposeCode, $costServiceType] = $this->resolveCostPurpose($rule, $input->postingBasis, $input->serviceType);
        $reference = $this->describeRule($rule);

        return [
            new LineDraft(
                purposeCode: $costPurposeCode,
                accountId: null,
                side: 'debit',
                amount: $taxAmount,
                currency: $currency,
                originalAmount: $taxAmount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'SUPPLIER_CHARGE_TAX',
                description: "Supplier charge tax ({$rule->tax_code}) — {$reference}",
                serviceType: $costServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'expense',
            ),
            new LineDraft(
                purposeCode: 'SERVICE_PAYABLE',
                accountId: null,
                side: 'credit',
                amount: $taxAmount,
                currency: $currency,
                originalAmount: $taxAmount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'SUPPLIERCREDITED',
                partyAccountRef: $input->supplierId,
                description: "Supplier charge tax payable ({$rule->tax_code}) — {$reference}",
                serviceType: $input->serviceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'payable',
                partyName: $input->supplierName,
            ),
        ];
    }

    /**
     * @return array{0: string, 1: ?string} [purposeCode, serviceType-or-null].
     */
    private function resolveCostPurpose(SupplierChargeRule $rule, string $postingBasis, string $taskServiceType): array
    {
        if ($rule->cost_account !== null && $rule->cost_account !== '') {
            // Explicit override -- per_service purpose codes still need the task's real
            // serviceType; global ones (e.g. SUPPLIER_CHARGE_EXPENSE) ignore it (LineDraft/
            // AccountResolver treat a non-null serviceType as a no-op for a purpose code with no
            // service_type dimension).
            $isPerService = in_array($rule->cost_account, config('accounting.purpose_codes.per_service', []), true);

            return [$rule->cost_account, $isPerService ? $taskServiceType : null];
        }

        return $postingBasis === SaleDraftInput::BASIS_PRINCIPAL
            ? ['SERVICE_COST', $taskServiceType]
            : ['SUPPLIER_CHARGE_EXPENSE', null];
    }

    private function describeRule(SupplierChargeRule $rule): string
    {
        return $rule->label ?? "rule #{$rule->id}";
    }

    private function resolveCurrency(SupplierChargeLineInput $input): string
    {
        return $input->currency ?? (string) config('accounting.engine.base_currency');
    }

    private function resolveTolerance(): float
    {
        return (float) config('accounting.engine.balance_tolerance', 0.0005);
    }

    private function resolveDecimals(): int
    {
        return (int) config('accounting.engine.base_decimals', 3);
    }
}
