<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\SupplierChargeOverridePendingApprovalException;
use App\Models\Setting;
use App\Models\SupplierChargeRule;
use App\Models\SupplierChargeRuleFiring;
use Illuminate\Support\Carbon;

/**
 * W6.C (w6-brief.md "W6.C — Supplier-side charges"; supplier-charges-design.md Table 4).
 *
 * Resolves the ACTIVE `supplier_charge_rules` that apply to one task's
 * supplier/service_type/channel at issue time, per the precedence rule the migration's own
 * docblock documents: `supplier+service_type` row beats `supplier`-only beats `service_type`-only
 * beats company-wide, evaluated INDEPENDENTLY per distinct `charge_kind` (a task can be subject to
 * more than one charge_kind at once — e.g. an `iata_fee` rule AND a `card_surcharge` rule both
 * active for the same supplier — each resolved to its own single winning row by the same
 * precedence tiers).
 *
 * `channel` is a plain FILTER, not a precedence tier: a rule with a non-null `channel` only
 * matches a task presenting that exact channel; a rule with `channel` NULL matches any channel.
 *
 * ── Dedup (`once_per_reference`) ──────────────────────────────────────────────────────────────────
 * This class is deliberately a PURE resolver plus two narrow, explicit side-effecting methods
 * ({@see self::hasAlreadyFired()} read, {@see self::recordFiring()} write) — it never writes a
 * firing row itself as a side effect of {@see self::resolveApplicable()}, because this class (like
 * {@see SaleDraftBuilder}/{@see SupplierCostCorrectionDraftBuilder}) does not call
 * {@see PostingSeam}/{@see PostingService} and has no way to know whether the document its
 * LineDraft[] ends up in actually posts. The eventual caller (W6.I's `TaskStatusService::issue()`)
 * must call {@see self::recordFiring()} INSIDE the same DB transaction as the
 * `PostingSeam::post()` call it guards, AFTER that post succeeds — never before, and never outside
 * that transaction — so a rolled-back post never leaves a stale firing record that would silently
 * suppress a real future occurrence of the same rule.
 */
final class SupplierChargeRuleResolver
{
    /**
     * @return array<string, SupplierChargeRule> keyed by charge_kind — one winning rule per
     *                                            charge_kind, precedence-resolved.
     */
    public function resolveApplicable(
        int $companyId,
        ?int $supplierId,
        string $serviceType,
        ?string $channel,
        \DateTimeInterface $asOf
    ): array {
        $candidates = SupplierChargeRule::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->where(function ($query) use ($supplierId) {
                $query->whereNull('supplier_id')->orWhere('supplier_id', $supplierId);
            })
            ->where(function ($query) use ($serviceType) {
                $query->whereNull('service_type')->orWhere('service_type', $serviceType);
            })
            ->where(function ($query) use ($channel) {
                $query->whereNull('channel')->orWhere('channel', $channel);
            })
            ->get()
            ->filter(fn (SupplierChargeRule $rule) => $rule->isEffectiveOn($asOf));

        $winners = [];

        foreach ($candidates->groupBy('charge_kind') as $chargeKind => $rulesForKind) {
            /** @var \Illuminate\Support\Collection<int, SupplierChargeRule> $rulesForKind */
            $winner = $rulesForKind
                // Highest specificity first; a channel-specific row breaks a tie over a
                // channel-agnostic one at the same specificity rank; lowest id last as a
                // deterministic, documented tiebreak (no ambiguity test in this build's brief
                // exercises a genuine tie beyond this).
                ->sortByDesc(fn (SupplierChargeRule $r) => sprintf(
                    '%d%d%010d',
                    $r->specificityRank(),
                    $r->channel !== null ? 1 : 0,
                    PHP_INT_MAX - $r->id
                ))
                ->first();

            if ($winner !== null) {
                $winners[(string) $chargeKind] = $winner;
            }
        }

        return $winners;
    }

    /**
     * Read half of the once_per_reference dedup contract (class docblock). A rule with
     * `once_per_reference=false` never has anything to check — it fires on every task
     * unconditionally, so this always returns false for it.
     */
    public function hasAlreadyFired(SupplierChargeRule $rule, string $reference): bool
    {
        if (! $rule->once_per_reference) {
            return false;
        }

        return SupplierChargeRuleFiring::query()
            ->where('supplier_charge_rule_id', $rule->id)
            ->where('reference', $reference)
            ->exists();
    }

    /**
     * Write half of the dedup contract — see class docblock's transactional ordering requirement.
     * A rule with `once_per_reference=false` records nothing (nothing to dedup), matching
     * {@see self::hasAlreadyFired()}'s own early return. Idempotent by the table's own unique
     * constraint: a second call for the same (rule, reference) is caught and logged as a no-op
     * rather than propagating a constraint-violation exception into the caller's posting
     * transaction — this method is a best-effort dedup marker, not itself a source of posting
     * failures.
     */
    public function recordFiring(SupplierChargeRule $rule, string $reference, int $companyId, ?int $taskId, \DateTimeInterface $firedAt): void
    {
        if (! $rule->once_per_reference) {
            return;
        }

        try {
            SupplierChargeRuleFiring::query()->create([
                'supplier_charge_rule_id' => $rule->id,
                'company_id' => $companyId,
                'reference' => $reference,
                'task_id' => $taskId,
                'fired_at' => Carbon::instance($firedAt),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::warning('accounting.supplier_charge_rule_firing_duplicate', [
                'supplier_charge_rule_id' => $rule->id,
                'reference' => $reference,
                'task_id' => $taskId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manual per-task override gate — mirrors RefundController::applyRefundFeeSchedule()'s
     * 'free'|'needs_approval' Setting-key pattern (see
     * {@see SupplierChargeOverridePendingApprovalException}'s own docblock).
     *
     * @param  float|null  $overrideAmount  null = no override requested; returns $resolvedAmount
     *                                      unchanged.
     * @param  bool  $approved  Must be true for a real (beyond-tolerance) override to take effect
     *                          when the company's policy is 'needs_approval' (the shipped
     *                          default) — the caller's own approval-workflow gate (W6.U) sets this
     *                          only after an approver has acted.
     *
     * @throws SupplierChargeOverridePendingApprovalException when the policy is 'needs_approval',
     *                                                         the override genuinely differs from
     *                                                         the resolved amount, and $approved
     *                                                         is false.
     */
    public function applyManualOverride(
        SupplierChargeRule $rule,
        float $resolvedAmount,
        ?float $overrideAmount,
        int $companyId,
        bool $approved = false
    ): float {
        if ($overrideAmount === null) {
            return $resolvedAmount;
        }

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        if (abs($overrideAmount - $resolvedAmount) <= $tolerance) {
            // Not a real override -- the operator typed back the same figure.
            return $resolvedAmount;
        }

        $policy = (string) Setting::getByKey(
            $companyId,
            'accounting.supplier_charge_override_policy',
            config('accounting.supplier_charges.override_policy_default', 'needs_approval')
        );

        if ($policy === 'free' || $approved) {
            return $overrideAmount;
        }

        throw new SupplierChargeOverridePendingApprovalException($rule->id, $resolvedAmount, $overrideAmount);
    }

    /**
     * W6.C item 6 ("this sub-wave is only its [SupplierCostCorrectionDraftBuilder's] caller for
     * the supplier-charge-rule case, do not rebuild that builder"). Wraps the construction of a
     * {@see SupplierCostCorrectionInput} for a supplier-charge-rule cost discovered/corrected
     * AFTER the sale already posted — the actual document-building/period logic stays entirely
     * inside {@see SupplierCostCorrectionDraftBuilder}, untouched.
     */
    public function buildCostCorrectionInput(
        SupplierChargeRule $rule,
        string $serviceType,
        string $postingBasis,
        float $originalAmount,
        float $correctedAmount,
        int $companyId,
        int $branchId,
        \DateTimeInterface $saleDocDate,
        \DateTimeInterface $correctionDate,
        ?int $invoiceId = null,
        ?int $invoiceDetailId = null,
        ?int $taskId = null,
        ?string $taskReference = null,
        ?string $currency = null,
        float $exchangeRate = 1.0
    ): SupplierCostCorrectionInput {
        // $serviceType is the TASK's own actual service type -- never $rule->service_type, which
        // may legitimately be NULL on a company-wide/supplier-wide rule (see class docblock on
        // SupplierChargeRule and the migration's own resolution-order note). Matches
        // SupplierChargeLineBuilder's identical convention (LineDraft::$serviceType is always the
        // task's real type, independent of how broadly the matched rule itself was scoped).
        return new SupplierCostCorrectionInput(
            serviceType: $serviceType,
            postingBasis: $postingBasis,
            originalCostAmount: $originalAmount,
            correctedCostAmount: $correctedAmount,
            companyId: $companyId,
            branchId: $branchId,
            saleDocDate: $saleDocDate,
            correctionDate: $correctionDate,
            invoiceId: $invoiceId,
            invoiceDetailId: $invoiceDetailId,
            taskId: $taskId,
            supplierId: $rule->supplier_id,
            supplierName: null,
            taskReference: $taskReference,
            currency: $currency,
            exchangeRate: $exchangeRate,
        );
    }
}
