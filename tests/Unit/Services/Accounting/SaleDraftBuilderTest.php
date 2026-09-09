<?php

namespace Tests\Unit\Services\Accounting;

use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Tests\TestCase;

/**
 * W3d (sale-shape-audit.md / w3d-brief.md). Pure unit tests for
 * {@see SaleDraftBuilder::buildLines()} — no DB, no PostingService, no controller. This class only
 * builds a `LineDraft[]`; the Feature-level tests in
 * {@see \Tests\Feature\Accounting\InvoiceControllerProfitLossPostingTest} and
 * {@see \Tests\Feature\Accounting\ChatControllerPostingTest} prove the same shapes post correctly
 * through the real engine against real seeders.
 */
class SaleDraftBuilderTest extends TestCase
{
    private function agentInput(float $sell, float $cost, string $serviceType = 'flight'): SaleDraftInput
    {
        return new SaleDraftInput(
            serviceType: $serviceType,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: 1,
            supplierId: 2,
            agentId: 3,
        );
    }

    /**
     * OWNER RULING R-CT1, 2026-09-09 — was `test_agent_basis_positive_margin_posts_three_lines`,
     * which asserted the NET shape (Cr SERVICE_REVENUE = margin). Gross is now the basis for
     * BOTH `BASIS_AGENT` and `BASIS_PRINCIPAL`: revenue is the FULL sell and the supplier cost
     * posts as cost of sales.
     */
    public function test_agent_basis_posts_gross_revenue_and_a_cost_of_sales_pair(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(130.0, 100.0));

        $this->assertCount(4, $lines);
        $this->assertSame(
            ['RECEIVABLE_CONTROL', 'SERVICE_REVENUE', 'SERVICE_COST', 'SERVICE_PAYABLE'],
            array_map(fn ($l) => $l->purposeCode, $lines)
        );

        $receivable = $lines[0];
        $this->assertSame('debit', $receivable->side);
        $this->assertEqualsWithDelta(130.0, $receivable->amount, 0.0005);

        $revenue = $lines[1];
        $this->assertSame('flight', $revenue->serviceType);
        $this->assertSame('credit', $revenue->side);
        $this->assertEqualsWithDelta(130.0, $revenue->amount, 0.0005, 'GROSS: revenue is the full sell, never the margin (R-CT1).');

        $cost = $lines[2];
        $this->assertSame('flight', $cost->serviceType);
        $this->assertSame('debit', $cost->side);
        $this->assertEqualsWithDelta(100.0, $cost->amount, 0.0005, 'GROSS: the supplier cost posts as cost of sales (R-CT1).');

        $payable = $lines[3];
        $this->assertSame('flight', $payable->serviceType);
        $this->assertSame('credit', $payable->side);
        $this->assertEqualsWithDelta(100.0, $payable->amount, 0.0005);

        $debitTotal = array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $lines));
        $creditTotal = array_sum(array_map(fn ($l) => $l->side === 'credit' ? $l->amount : 0.0, $lines));
        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.0005, 'Every line set this builder produces must self-balance.');
    }

    /**
     * R-CT1: the sign-aware contra-income leg is GONE. A below-cost sale posts revenue and cost
     * independently and simply shows a negative gross margin — never a flipped revenue side.
     */
    public function test_agent_basis_below_cost_sale_posts_revenue_and_cost_independently(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(70.0, 100.0));

        $this->assertCount(4, $lines);

        $revenue = $lines[1];
        $this->assertSame('SERVICE_REVENUE', $revenue->purposeCode);
        $this->assertSame('credit', $revenue->side, 'Revenue is ALWAYS a credit under gross — no contra-income debit exists any more.');
        $this->assertEqualsWithDelta(70.0, $revenue->amount, 0.0005);

        $cost = $lines[2];
        $this->assertSame('SERVICE_COST', $cost->purposeCode);
        $this->assertSame('debit', $cost->side);
        $this->assertEqualsWithDelta(100.0, $cost->amount, 0.0005);

        $debitTotal = array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $lines));
        $creditTotal = array_sum(array_map(fn ($l) => $l->side === 'credit' ? $l->amount : 0.0, $lines));
        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.0005);
    }

    /**
     * R-CT1: there is no margin line to omit any more. Sold exactly at cost still posts the full
     * four-line gross document — revenue = cost = sell, which is the economically correct
     * presentation and what a gross P&L must show.
     */
    public function test_agent_basis_sold_at_cost_still_posts_the_full_gross_document(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(100.0, 100.0));

        $this->assertCount(4, $lines);
        $this->assertSame(
            ['RECEIVABLE_CONTROL', 'SERVICE_REVENUE', 'SERVICE_COST', 'SERVICE_PAYABLE'],
            array_map(fn ($l) => $l->purposeCode, $lines)
        );
        $this->assertEqualsWithDelta(100.0, $lines[1]->amount, 0.0005);
        $this->assertEqualsWithDelta(100.0, $lines[2]->amount, 0.0005);
    }

    /**
     * CT-A3 E1 / CT-F34, now applying to both bases from one place.
     */
    public function test_agent_basis_omits_the_whole_cost_pair_when_cost_is_zero(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(30.0, 0.0));

        $this->assertCount(2, $lines, 'A pure-fee sale posts Dr AR / Cr SERVICE_REVENUE only — never a 0.000 cost or payable line.');
        $this->assertSame(['RECEIVABLE_CONTROL', 'SERVICE_REVENUE'], array_map(fn ($l) => $l->purposeCode, $lines));
        $this->assertEqualsWithDelta(30.0, $lines[1]->amount, 0.0005);
    }

    public function test_agent_basis_never_uses_markup_income(): void
    {
        $positive = (new SaleDraftBuilder)->buildLines($this->agentInput(130.0, 100.0));
        $negative = (new SaleDraftBuilder)->buildLines($this->agentInput(70.0, 100.0));

        foreach ([...$positive, ...$negative] as $line) {
            $this->assertNotSame('MARKUP_INCOME', $line->purposeCode, 'SaleDraftBuilder must never post to MARKUP_INCOME — reserved for a distinct, not-yet-modeled event.');
        }
    }

    public function test_principal_basis_posts_gross_revenue_and_cost_of_sales_pair(): void
    {
        $input = new SaleDraftInput(
            serviceType: 'tour',
            sellAmount: 250.0,
            costAmount: 150.0,
            postingBasis: SaleDraftInput::BASIS_PRINCIPAL,
            clientId: 1,
            supplierId: 2,
            // P2.5.D (p2_5-brief.md §P2.5.D): 'tour' now DEFAULTS to at_travel (doc 22 §15.6) —
            // this test is about the GROSS posting shape (W3d), not recognition timing, so it
            // pins at_issue explicitly. See SaleDraftBuilderTest's own
            // "P2.5.D — revenue recognition timing" section below for the at_travel coverage.
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_ISSUE,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);

        $this->assertCount(4, $lines);

        $purposeCodes = array_map(fn ($l) => $l->purposeCode, $lines);
        $this->assertSame(['RECEIVABLE_CONTROL', 'SERVICE_REVENUE', 'SERVICE_COST', 'SERVICE_PAYABLE'], $purposeCodes);

        $revenue = $lines[1];
        $this->assertSame('credit', $revenue->side);
        $this->assertEqualsWithDelta(250.0, $revenue->amount, 0.0005, 'Principal basis: SERVICE_REVENUE holds the FULL sell price.');

        $cost = $lines[2];
        $this->assertSame('debit', $cost->side);
        $this->assertEqualsWithDelta(150.0, $cost->amount, 0.0005);

        $payable = $lines[3];
        $this->assertSame('credit', $payable->side);
        $this->assertEqualsWithDelta(150.0, $payable->amount, 0.0005);

        $debitTotal = array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $lines));
        $creditTotal = array_sum(array_map(fn ($l) => $l->side === 'credit' ? $l->amount : 0.0, $lines));
        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.0005);
    }

    public function test_principal_basis_omits_cost_pair_when_cost_is_zero(): void
    {
        $input = new SaleDraftInput(
            serviceType: 'tour',
            sellAmount: 250.0,
            costAmount: 0.0,
            postingBasis: SaleDraftInput::BASIS_PRINCIPAL,
            clientId: 1,
            // P2.5.D: see the sibling test above for why this pins at_issue explicitly.
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_ISSUE,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);

        $this->assertCount(2, $lines, 'No cost known yet: only the gross receivable/revenue pair posts.');
        $this->assertSame('RECEIVABLE_CONTROL', $lines[0]->purposeCode);
        $this->assertSame('SERVICE_REVENUE', $lines[1]->purposeCode);
    }

    public function test_invalid_posting_basis_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 100.0,
            costAmount: 50.0,
            postingBasis: 'bogus',
        );
    }

    public function test_resolve_posting_basis_falls_back_to_configured_default_with_no_company(): void
    {
        // companyId <= 0 must never attempt a Setting lookup — falls straight to config defaults.
        $this->assertSame(SaleDraftInput::BASIS_AGENT, SaleDraftBuilder::resolvePostingBasis(0, 'flight'));
        $this->assertSame(SaleDraftInput::BASIS_AGENT, SaleDraftBuilder::resolvePostingBasis(0, 'hotel'));
        $this->assertSame(SaleDraftInput::BASIS_PRINCIPAL, SaleDraftBuilder::resolvePostingBasis(0, 'tour'));
        $this->assertSame(SaleDraftInput::BASIS_AGENT, SaleDraftBuilder::resolvePostingBasis(0, 'not-a-real-service-type'), 'An unrecognised service type must fall back to the safe default (agent), never principal.');
    }

    // ── P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6) — revenue recognition timing ─────────────────

    public function test_agent_basis_at_issue_default_is_unchanged(): void
    {
        // 'flight' defaults to at_issue (doc 22 §15.6) — SaleDraftBuilder must produce the
        // byte-for-byte pre-P2.5.D shape when $recognitionTiming is left null.
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(130.0, 100.0, 'flight'));

        $this->assertSame('SERVICE_REVENUE', $lines[1]->purposeCode);
        $this->assertSame('flight', $lines[1]->serviceType);
        $this->assertSame('SERVICE_COST', $lines[2]->purposeCode);
        $this->assertSame('flight', $lines[2]->serviceType);
    }

    public function test_agent_basis_at_travel_defers_margin_to_deferred_revenue(): void
    {
        $input = new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 130.0,
            costAmount: 100.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_TRAVEL,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);

        $this->assertCount(4, $lines, 'Line count/amounts are unchanged by recognition timing — only the purpose codes differ.');
        $this->assertSame(
            ['RECEIVABLE_CONTROL', 'DEFERRED_REVENUE', 'PREPAID_SUPPLIER_COST', 'SERVICE_PAYABLE'],
            array_map(fn ($l) => $l->purposeCode, $lines)
        );

        $revenue = $lines[1];
        $this->assertNull($revenue->serviceType, 'DEFERRED_REVENUE is a GLOBAL purpose code — never carries a per-service serviceType.');
        $this->assertSame('credit', $revenue->side);
        $this->assertEqualsWithDelta(130.0, $revenue->amount, 0.0005, 'R-CT1: the deferred amount is the full sell, not the margin.');

        $this->assertNull($lines[2]->serviceType);
        $this->assertSame('debit', $lines[2]->side);
        $this->assertEqualsWithDelta(100.0, $lines[2]->amount, 0.0005);

        $this->assertSame('SERVICE_PAYABLE', $lines[3]->purposeCode, 'SERVICE_PAYABLE (the real supplier liability) is never deferred.');
    }

    public function test_agent_basis_at_travel_below_cost_sale_keeps_both_legs_on_their_natural_side(): void
    {
        $input = new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 70.0,
            costAmount: 100.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_TRAVEL,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);

        $revenue = $lines[1];
        $this->assertSame('DEFERRED_REVENUE', $revenue->purposeCode);
        $this->assertSame('credit', $revenue->side, 'R-CT1 removed the sign-aware flip — deferred revenue is always a credit.');
        $this->assertEqualsWithDelta(70.0, $revenue->amount, 0.0005);

        $cost = $lines[2];
        $this->assertSame('PREPAID_SUPPLIER_COST', $cost->purposeCode);
        $this->assertSame('debit', $cost->side);
        $this->assertEqualsWithDelta(100.0, $cost->amount, 0.0005);
    }

    public function test_principal_basis_at_issue_default_is_unchanged(): void
    {
        // 'tour' defaults to at_travel (doc 22 §15.6) — but an EXPLICIT at_issue override on the
        // input must still produce the ordinary, undeferred shape.
        $input = new SaleDraftInput(
            serviceType: 'tour',
            sellAmount: 250.0,
            costAmount: 150.0,
            postingBasis: SaleDraftInput::BASIS_PRINCIPAL,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_ISSUE,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);
        $purposeCodes = array_map(fn ($l) => $l->purposeCode, $lines);

        $this->assertSame(['RECEIVABLE_CONTROL', 'SERVICE_REVENUE', 'SERVICE_COST', 'SERVICE_PAYABLE'], $purposeCodes);
    }

    public function test_principal_basis_defaults_to_at_travel_and_defers_revenue_and_cost(): void
    {
        // 'tour' defaults to at_travel (doc 22 §15.6) — with $recognitionTiming left null, the
        // config default already applies, with NO call-site change (see SaleDraftBuilder's own
        // "P2.5.D addition" docblock note).
        $input = new SaleDraftInput(
            serviceType: 'tour',
            sellAmount: 250.0,
            costAmount: 150.0,
            postingBasis: SaleDraftInput::BASIS_PRINCIPAL,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);

        $this->assertCount(4, $lines, 'Line count/amounts are unchanged by recognition timing.');
        $purposeCodes = array_map(fn ($l) => $l->purposeCode, $lines);
        $this->assertSame(['RECEIVABLE_CONTROL', 'DEFERRED_REVENUE', 'PREPAID_SUPPLIER_COST', 'SERVICE_PAYABLE'], $purposeCodes);

        $revenue = $lines[1];
        $this->assertNull($revenue->serviceType);
        $this->assertSame('credit', $revenue->side);
        $this->assertEqualsWithDelta(250.0, $revenue->amount, 0.0005);

        $cost = $lines[2];
        $this->assertNull($cost->serviceType);
        $this->assertSame('debit', $cost->side);
        $this->assertEqualsWithDelta(150.0, $cost->amount, 0.0005);

        $payable = $lines[3];
        $this->assertSame('SERVICE_PAYABLE', $payable->purposeCode, 'SERVICE_PAYABLE (the real supplier liability) is never deferred — see class docblock.');
        $this->assertSame('tour', $payable->serviceType);

        $debitTotal = array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $lines));
        $creditTotal = array_sum(array_map(fn ($l) => $l->side === 'credit' ? $l->amount : 0.0, $lines));
        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.0005);
    }

    public function test_principal_basis_at_travel_omits_prepaid_cost_pair_when_cost_is_zero(): void
    {
        $input = new SaleDraftInput(
            serviceType: 'tour',
            sellAmount: 250.0,
            costAmount: 0.0,
            postingBasis: SaleDraftInput::BASIS_PRINCIPAL,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_TRAVEL,
        );

        $lines = (new SaleDraftBuilder)->buildLines($input);

        $this->assertCount(2, $lines, 'No cost known yet: the cost/prepaid pair stays omitted exactly like the at_issue case.');
        $this->assertSame(['RECEIVABLE_CONTROL', 'DEFERRED_REVENUE'], array_map(fn ($l) => $l->purposeCode, $lines));
    }

    public function test_invalid_recognition_timing_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SaleDraftInput(
            serviceType: 'flight',
            sellAmount: 100.0,
            costAmount: 50.0,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            recognitionTiming: 'bogus',
        );
    }

    public function test_resolve_recognition_timing_falls_back_to_configured_default_with_no_company(): void
    {
        $this->assertSame(SaleDraftInput::RECOGNITION_AT_ISSUE, SaleDraftBuilder::resolveRecognitionTiming(0, 'flight'));
        $this->assertSame(SaleDraftInput::RECOGNITION_AT_TRAVEL, SaleDraftBuilder::resolveRecognitionTiming(0, 'tour'));
        $this->assertSame(SaleDraftInput::RECOGNITION_AT_ISSUE, SaleDraftBuilder::resolveRecognitionTiming(0, 'not-a-real-service-type'), 'An unrecognised service type must fall back to the safe default (at_issue), never at_travel.');
    }
}
