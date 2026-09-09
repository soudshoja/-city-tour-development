<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Company;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 E1 — CT-F34: a zero-supplier-cost sale on AGENT basis was refused outright.
 *
 * `SaleDraftBuilder::buildAgentBasisLines()` emitted the `SERVICE_PAYABLE` leg unconditionally,
 * so a task with `tasks.total = 0` produced a line with `amount = 0.000`, which
 * `PostingService::post()` step 3c rejects with `NonNegativeAmountException`. That exception
 * propagates out of `InvoiceController::postSaleJournalEntries()` uncaught by its own documented
 * contract, so on the live path **invoice creation fails**, it does not degrade. CT-A2's replay
 * measured 31 live documents refused on exactly this (KWD 206.000 of sell; 21 flight + 10 visa).
 *
 * The principal branch already guarded the identical leg (`SaleDraftBuilder.php:266`,
 * `if ($input->costAmount > $tolerance)`); this lane applies the same rule to the agent branch.
 */
class E1ZeroCostSalePostsTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();

        parent::tearDown();
    }

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
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_ISSUE,
        );
    }

    public function test_agent_basis_zero_cost_omits_the_supplier_payable_leg(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(30.0, 0.0));

        $this->assertCount(
            2,
            $lines,
            'A pure-fee sale posts Dr RECEIVABLE_CONTROL / Cr SERVICE_REVENUE only — the '
            .'zero-amount SERVICE_PAYABLE leg that made PostingService refuse the whole document '
            .'must not be emitted at all.'
        );

        $this->assertSame(
            ['RECEIVABLE_CONTROL', 'SERVICE_REVENUE'],
            array_map(fn ($l) => $l->purposeCode, $lines)
        );

        $this->assertNotContains(
            'SERVICE_PAYABLE',
            array_map(fn ($l) => $l->purposeCode, $lines)
        );
        $this->assertNotContains(
            'SERVICE_COST',
            array_map(fn ($l) => $l->purposeCode, $lines)
        );

        $this->assertSame('debit', $lines[0]->side);
        $this->assertEqualsWithDelta(30.0, $lines[0]->amount, 0.0005);
        $this->assertSame('credit', $lines[1]->side);
        $this->assertEqualsWithDelta(
            30.0,
            $lines[1]->amount,
            0.0005,
            'With no cost pair, the gross revenue leg carries the full sell and the two-line '
            .'document balances by construction.'
        );
    }

    public function test_agent_basis_still_posts_the_cost_pair_when_a_real_cost_exists(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(130.0, 100.0));

        $this->assertCount(4, $lines, 'Owner ruling R-CT1: gross — 4 lines whenever a real cost exists.');
        $this->assertSame(
            ['RECEIVABLE_CONTROL', 'SERVICE_REVENUE', 'SERVICE_COST', 'SERVICE_PAYABLE'],
            array_map(fn ($l) => $l->purposeCode, $lines)
        );
        $this->assertEqualsWithDelta(130.0, $lines[1]->amount, 0.0005);
        $this->assertEqualsWithDelta(100.0, $lines[2]->amount, 0.0005);
        $this->assertEqualsWithDelta(100.0, $lines[3]->amount, 0.0005);
    }

    public function test_a_cost_below_tolerance_is_treated_as_no_cost(): void
    {
        // The guard is `> tolerance`, not `> 0` — a sub-fils residue must not resurrect a leg
        // PostingService would then round to 0.000 and refuse.
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(30.0, $tolerance / 2));

        $this->assertCount(2, $lines);
        $this->assertNotContains('SERVICE_PAYABLE', array_map(fn ($l) => $l->purposeCode, $lines));
    }

    /**
     * CT-A3 wave-1 server-replay finding (2026-09-09): the same failure mode on the OTHER leg.
     * After E1 fixed the zero-COST case, the replay refused 7 more documents on
     * "`DocumentDraft::$lines[0]` amount must be > 0" — invoice_details whose task_price AND task
     * total are both 0.000 (detail ids 14399-14402, 14509, 14615-14616). The AR/revenue pair is
     * now omitted when the SELL is zero, exactly as the cost pair is when the COST is zero.
     */
    public function test_a_zero_sell_zero_cost_sale_builds_no_lines_at_all(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(0.0, 0.0));

        $this->assertSame(
            [],
            $lines,
            'Nothing happened — no money in either direction — so there is no document. The '
            .'caller must treat an empty array as "no document", never as an error.'
        );
    }

    public function test_a_zero_sell_sale_that_still_has_a_cost_posts_only_the_cost_pair(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(0.0, 100.0));

        $this->assertSame(
            ['SERVICE_COST', 'SERVICE_PAYABLE'],
            array_map(fn ($l) => $l->purposeCode, $lines),
            'Cost incurred with nothing billed yet is legitimate under gross — the cost pair '
            .'stands on its own and still balances.'
        );
        $this->assertEqualsWithDelta(100.0, $lines[0]->amount, 0.0005);
        $this->assertEqualsWithDelta(100.0, $lines[1]->amount, 0.0005);
    }

    /**
     * The real proof: the whole document goes through `PostingService::post()` and comes back a
     * `PostedDocument` instead of throwing `NonNegativeAmountException`.
     */
    public function test_zero_cost_agent_sale_posts_through_the_engine_instead_of_being_refused(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10));
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $lines = (new SaleDraftBuilder)->buildLines($this->agentInput(30.0, 0.0));

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: 0,
            docType: 'INV',
            subType: 'SALE',
            docDate: Carbon::create(2026, 6, 15),
            narration: 'CT-A3 E1 pure-fee sale',
            lines: $lines,
            idempotencyKey: 'ct-a3-e1:zero-cost-sale',
        );

        $posted = app(PostingService::class)->post($draft);

        $this->assertInstanceOf(
            PostedDocument::class,
            $posted,
            'A pure-fee sale must post, not throw NonNegativeAmountException (CT-F34).'
        );

        $this->assertCount(2, $posted->lines);
        $this->assertEqualsWithDelta(
            0.0,
            (float) $posted->transaction->total_debit - (float) $posted->transaction->total_credit,
            0.0005
        );
        $this->assertEqualsWithDelta(30.0, (float) $posted->transaction->total_debit, 0.0005);
    }
}
