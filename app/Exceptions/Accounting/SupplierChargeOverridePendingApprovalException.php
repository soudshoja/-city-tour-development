<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * W6.C manual per-task override gate — mirrors RefundController::applyRefundFeeSchedule()'s
 * 'free'|'needs_approval' vocabulary (Setting key `accounting.refund.fee_schedule.{type}.override`)
 * for the supplier-charge-rule case: `accounting.supplier_charge_override_policy`
 * ('free'|'needs_approval', default 'needs_approval' — see
 * App\Services\Accounting\SupplierChargeRuleResolver::applyManualOverride()).
 *
 * Thrown when an operator's per-task override amount differs (beyond the engine's balance
 * tolerance) from the rule's own resolved amount, the company's override policy is
 * 'needs_approval' (the shipped default), and the caller has not yet passed $approved=true — i.e.
 * "blocked pending approval until an approver acts" (w6-brief.md §W6.U verify criterion 2).
 *
 * Deliberately NOT a PostingException subclass: unlike AccountResolver/PostingService's own
 * exceptions, this is raised BEFORE any DocumentDraft/LineDraft exists — a pre-flight business
 * gate on the override amount itself, not a posting-pipeline violation.
 */
final class SupplierChargeOverridePendingApprovalException extends \RuntimeException
{
    public function __construct(
        public readonly int $ruleId,
        public readonly float $resolvedAmount,
        public readonly float $overrideAmount,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Supplier charge rule #%d override (%.3f) differs from the resolved amount (%.3f) and requires '
            .'approval before it can post (accounting.supplier_charge_override_policy=needs_approval).',
            $this->ruleId,
            $this->overrideAmount,
            $this->resolvedAmount
        ));
    }
}
