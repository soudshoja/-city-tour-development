<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\SupplierChargeRule;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\SupplierChargeLineBuilder;
use App\Services\Accounting\SupplierChargeLineInput;
use Tests\TestCase;

/**
 * W6.C (w6-brief.md "W6.C — Supplier-side charges"). Pure unit tests for
 * {@see SupplierChargeLineBuilder} — no DB, no PostingService, matching
 * {@see \Tests\Unit\Services\Accounting\SaleDraftBuilderTest}'s own convention: every rule used
 * here has `once_per_reference=false` and no manual override is passed, so
 * {@see \App\Services\Accounting\SupplierChargeRuleResolver::hasAlreadyFired()} /
 * `applyManualOverride()` never touch the database (see those methods' own early-return guards).
 * Feature-level DB-backed coverage (precedence resolution, dedup, override approval gate) lives in
 * {@see \Tests\Feature\Accounting\SupplierChargeRuleResolverTest}.
 */
class SupplierChargeLineBuilderTest extends TestCase
{
    private function makeRule(array $overrides = []): SupplierChargeRule
    {
        $rule = new SupplierChargeRule(array_merge([
            'company_id' => 1,
            'supplier_id' => 2,
            'service_type' => null,
            'channel' => null,
            'charge_kind' => 'iata_fee',
            'basis' => SupplierChargeRule::BASIS_FIXED,
            'amount' => 1.500,
            'currency' => null,
            'cost_account' => null,
            'recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB,
            'commissionable' => false,
            'tax_code' => null,
            'rounding_rule' => null,
            'active' => true,
            'once_per_reference' => false,
            'label' => 'IATA round-up fee',
        ], $overrides));

        // Not persisted -- id is set directly (fillable guard doesn't apply to direct attribute
        // assignment) so hasAlreadyFired()'s dedup query (never reached for once_per_reference=
        // false rules, but exercised in some cases below) and error messages have something real.
        $rule->id = $overrides['id'] ?? 501;

        return $rule;
    }

    private function input(array $overrides = []): SupplierChargeLineInput
    {
        return new SupplierChargeLineInput(
            serviceType: $overrides['serviceType'] ?? 'flight',
            postingBasis: $overrides['postingBasis'] ?? SaleDraftInput::BASIS_AGENT,
            companyId: 1,
            reference: $overrides['reference'] ?? 'PNR-TEST-1',
            fareAmount: $overrides['fareAmount'] ?? 100.0,
            totalAmount: $overrides['totalAmount'] ?? 130.0,
            passengerCount: $overrides['passengerCount'] ?? 1,
            segmentCount: $overrides['segmentCount'] ?? 1,
            supplierId: 2,
            supplierName: 'Test Supplier',
            clientId: 3,
            clientName: 'Test Client',
            taskId: 42,
        );
    }

    private function sumBySide(array $lines, string $side): float
    {
        return array_sum(array_map(
            fn ($l) => $l->side === $side ? $l->amount : 0.0,
            $lines
        ));
    }

    // ── Amount computation per basis ──────────────────────────────────────────────────────────────

    public function test_fixed_basis_uses_amount_verbatim(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['basis' => SupplierChargeRule::BASIS_FIXED, 'amount' => 1.500]);

        $this->assertEqualsWithDelta(1.500, $builder->computeAmount($rule, $this->input()), 0.0005);
    }

    public function test_percent_of_fare_basis(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['basis' => SupplierChargeRule::BASIS_PERCENT_OF_FARE, 'amount' => 5.0]);

        $this->assertEqualsWithDelta(5.0, $builder->computeAmount($rule, $this->input(['fareAmount' => 100.0])), 0.0005);
    }

    public function test_percent_of_total_basis(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['basis' => SupplierChargeRule::BASIS_PERCENT_OF_TOTAL, 'amount' => 10.0]);

        $this->assertEqualsWithDelta(13.0, $builder->computeAmount($rule, $this->input(['totalAmount' => 130.0])), 0.0005);
    }

    public function test_per_passenger_basis(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['basis' => SupplierChargeRule::BASIS_PER_PASSENGER, 'amount' => 2.000]);

        $this->assertEqualsWithDelta(6.000, $builder->computeAmount($rule, $this->input(['passengerCount' => 3])), 0.0005);
    }

    public function test_per_segment_basis(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['basis' => SupplierChargeRule::BASIS_PER_SEGMENT, 'amount' => 0.750]);

        $this->assertEqualsWithDelta(1.500, $builder->computeAmount($rule, $this->input(['segmentCount' => 2])), 0.0005);
    }

    // ── Line shape: never blended into the base sell/cost pair ──────────────────────────────────────

    public function test_agent_basis_cost_pair_debits_supplier_charge_expense(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['amount' => 1.500, 'recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB]);

        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input(['postingBasis' => SaleDraftInput::BASIS_AGENT]));

        $this->assertCount(2, $lines, 'Absorb policy: exactly the cost pair, nothing else.');

        $expense = $lines[0];
        $this->assertSame('SUPPLIER_CHARGE_EXPENSE', $expense->purposeCode);
        $this->assertNull($expense->serviceType, 'SUPPLIER_CHARGE_EXPENSE is a global purpose code -- no per-service dimension.');
        $this->assertSame('debit', $expense->side);
        $this->assertEqualsWithDelta(1.500, $expense->amount, 0.0005);

        $payable = $lines[1];
        $this->assertSame('SERVICE_PAYABLE', $payable->purposeCode);
        $this->assertSame('flight', $payable->serviceType, 'The payable leg still carries the TASK\'s real service type.');
        $this->assertSame('credit', $payable->side);
        $this->assertEqualsWithDelta(1.500, $payable->amount, 0.0005);

        $this->assertEqualsWithDelta($this->sumBySide($lines, 'debit'), $this->sumBySide($lines, 'credit'), 0.0005);
    }

    public function test_principal_basis_cost_pair_debits_service_cost_for_the_task_type(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['amount' => 2.000]);

        $lines = $builder->buildLines(
            ['iata_fee' => $rule],
            $this->input(['postingBasis' => SaleDraftInput::BASIS_PRINCIPAL, 'serviceType' => 'tour'])
        );

        $this->assertCount(2, $lines);
        $this->assertSame('SERVICE_COST', $lines[0]->purposeCode);
        $this->assertSame('tour', $lines[0]->serviceType);
        $this->assertSame('debit', $lines[0]->side);
        $this->assertSame('SERVICE_PAYABLE', $lines[1]->purposeCode);
        $this->assertSame('credit', $lines[1]->side);
    }

    public function test_explicit_cost_account_override_is_honoured(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['cost_account' => 'SERVICE_COST']);

        // Even on AGENT basis, an explicit override always wins over the basis default.
        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input(['postingBasis' => SaleDraftInput::BASIS_AGENT]));

        $this->assertSame('SERVICE_COST', $lines[0]->purposeCode);
        $this->assertSame('flight', $lines[0]->serviceType, 'Per-service override still carries the real task service type.');
    }

    // ── recharge_policy gates the recharge pair ──────────────────────────────────────────────────────

    public function test_absorb_never_posts_a_recharge_pair(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB]);

        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input());

        $this->assertCount(2, $lines);
        $this->assertNotContains('SUPPLIER_CHARGE_RECHARGE_INCOME', array_map(fn ($l) => $l->purposeCode, $lines));
    }

    public function test_recharge_agent_never_posts_a_second_jv_here(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['recharge_policy' => SupplierChargeRule::RECHARGE_AGENT]);

        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input());

        $this->assertCount(2, $lines, 'recharge_agent is deducted via AgentCharge\'s own bearer mechanism -- no second pair from this builder.');
    }

    public function test_recharge_client_posts_receivable_and_recharge_income_pair(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['recharge_policy' => SupplierChargeRule::RECHARGE_CLIENT, 'amount' => 1.500]);

        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input());

        $this->assertCount(4, $lines, 'Cost pair + recharge pair.');

        $receivable = $lines[2];
        $this->assertSame('RECEIVABLE_CONTROL', $receivable->purposeCode);
        $this->assertSame('debit', $receivable->side);
        $this->assertEqualsWithDelta(1.500, $receivable->amount, 0.0005);

        $income = $lines[3];
        $this->assertSame('SUPPLIER_CHARGE_RECHARGE_INCOME', $income->purposeCode);
        $this->assertSame('credit', $income->side);
        $this->assertEqualsWithDelta(1.500, $income->amount, 0.0005);

        $this->assertEqualsWithDelta($this->sumBySide($lines, 'debit'), $this->sumBySide($lines, 'credit'), 0.0005, 'Full 4-line set must self-balance.');
    }

    // ── Rule 1e: never touches SERVICE_REVENUE, regardless of commissionable ────────────────────────

    public function test_never_emits_a_service_revenue_line_whether_or_not_commissionable(): void
    {
        $builder = new SupplierChargeLineBuilder();

        foreach ([true, false] as $commissionable) {
            $rule = $this->makeRule(['commissionable' => $commissionable, 'recharge_policy' => SupplierChargeRule::RECHARGE_CLIENT]);
            $lines = $builder->buildLines(['iata_fee' => $rule], $this->input());

            $this->assertNotContains(
                'SERVICE_REVENUE',
                array_map(fn ($l) => $l->purposeCode, $lines),
                'commissionable='.($commissionable ? 'true' : 'false').' must never produce a SERVICE_REVENUE line from this builder.'
            );
        }
    }

    // ── Tax line: separate pair, never folded into the fee amount ───────────────────────────────────

    public function test_tax_code_with_supplied_amount_posts_a_separate_pair(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['tax_code' => 'VAT5', 'amount' => 1.500]);

        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input(), taxAmounts: [$rule->id => 0.075]);

        $this->assertCount(4, $lines, 'Cost pair + tax pair.');
        $feeLine = $lines[0];
        $this->assertEqualsWithDelta(1.500, $feeLine->amount, 0.0005, 'Tax must never be folded into the fee\'s own line amount.');

        $taxLine = $lines[2];
        $this->assertSame('SUPPLIER_CHARGE_TAX', $taxLine->transactionType);
        $this->assertEqualsWithDelta(0.075, $taxLine->amount, 0.0005);
    }

    public function test_tax_code_set_but_no_amount_supplied_is_a_documented_no_op(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['tax_code' => 'VAT5']);

        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input());

        $this->assertCount(2, $lines, 'No tax rate exists in today\'s schema (Kuwait: no VAT) -- no amount supplied means no tax line, per class docblock.');
    }

    // ── once_per_reference=false rules from multiple charge_kinds all post independently ────────────

    public function test_multiple_charge_kinds_each_post_their_own_pair(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $iata = $this->makeRule(['id' => 601, 'charge_kind' => 'iata_fee', 'amount' => 1.000]);
        $card = $this->makeRule(['id' => 602, 'charge_kind' => 'card_surcharge', 'amount' => 2.500]);

        $lines = $builder->buildLines(['iata_fee' => $iata, 'card_surcharge' => $card], $this->input());

        $this->assertCount(4, $lines);
        $this->assertEqualsWithDelta($this->sumBySide($lines, 'debit'), $this->sumBySide($lines, 'credit'), 0.0005);
    }

    // ── Zero-amount rule posts nothing ────────────────────────────────────────────────────────────────

    public function test_zero_amount_rule_posts_no_lines(): void
    {
        $builder = new SupplierChargeLineBuilder();
        $rule = $this->makeRule(['amount' => 0.0]);

        $lines = $builder->buildLines(['iata_fee' => $rule], $this->input());

        $this->assertSame([], $lines);
    }
}
