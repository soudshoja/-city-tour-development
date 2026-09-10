<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * OWNER RULING R-CT1, 2026-09-09 (was PENDING through CT-A2) — **the revenue basis is GROSS**.
 *
 * Verbatim: "REVENUE BASIS = GROSS. Agent-basis sales post the full sell price as revenue
 * (Dr AR / Cr Revenue = sell) and the supplier cost as cost of sales (Dr COGS or 1430 / Cr
 * supplier payable = cost)."
 *
 * CT-A2 §4.2 measured the net (margin) shape this replaces, on 3,530 identical sale units:
 *
 * | Measure                          | engine (NET) | legacy ledger (GROSS) |
 * |----------------------------------|--------------|-----------------------|
 * | Revenue credited                 |   51,597.329 |           549,949.042 |
 * | Supplier cost of sales debited   |        0.000 |           463,083.015 |
 *
 * The scaled fixture below reproduces that same relationship in miniature: on a population whose
 * sells sum to the same shape, revenue must land on the GROSS figure and a matching cost-of-sales
 * total must appear. Margin-only posting fails both assertions, which is the mutation proof the
 * ruling asked for.
 */
class RCt1GrossRevenueBasisTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function input(float $sell, float $cost, string $basis, string $serviceType = 'flight'): SaleDraftInput
    {
        return new SaleDraftInput(
            serviceType: $serviceType,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: $basis,
            clientId: 1,
            supplierId: 2,
            agentId: 3,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_ISSUE,
        );
    }

    public function test_both_bases_build_the_same_gross_document(): void
    {
        $agent = (new SaleDraftBuilder)->buildLines($this->input(130.0, 100.0, SaleDraftInput::BASIS_AGENT));
        $principal = (new SaleDraftBuilder)->buildLines($this->input(130.0, 100.0, SaleDraftInput::BASIS_PRINCIPAL));

        $shape = fn (array $lines) => array_map(
            fn ($l) => [$l->purposeCode, $l->side, round($l->amount, 3)],
            $lines
        );

        $this->assertSame(
            $shape($principal),
            $shape($agent),
            'R-CT1: BASIS_AGENT and BASIS_PRINCIPAL now build the identical gross document.'
        );

        $this->assertSame(
            [
                ['RECEIVABLE_CONTROL', 'debit', 130.0],
                ['SERVICE_REVENUE', 'credit', 130.0],
                ['SERVICE_COST', 'debit', 100.0],
                ['SERVICE_PAYABLE', 'credit', 100.0],
            ],
            $shape($agent)
        );
    }

    public function test_revenue_is_the_full_sell_not_the_margin(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->input(130.0, 100.0, SaleDraftInput::BASIS_AGENT));

        $revenue = array_values(array_filter($lines, fn ($l) => $l->purposeCode === 'SERVICE_REVENUE'))[0];

        $this->assertEqualsWithDelta(130.0, $revenue->amount, 0.0005);
        $this->assertNotEqualsWithDelta(
            30.0,
            $revenue->amount,
            0.0005,
            'The net/margin shape (30.0 = sell − cost) is exactly what R-CT1 replaces.'
        );
    }

    public function test_a_supplier_cost_of_sales_line_always_accompanies_a_costed_sale(): void
    {
        $lines = (new SaleDraftBuilder)->buildLines($this->input(130.0, 100.0, SaleDraftInput::BASIS_AGENT));

        $cost = array_values(array_filter($lines, fn ($l) => $l->purposeCode === 'SERVICE_COST'));

        $this->assertCount(
            1,
            $cost,
            'The net shape posted KWD 0.000 of supplier cost of sales on the whole 3,530-unit '
            .'population (CT-A2 §4.2). Under gross, every costed sale carries one.'
        );
        $this->assertSame('debit', $cost[0]->side);
        $this->assertEqualsWithDelta(100.0, $cost[0]->amount, 0.0005);
        $this->assertSame('expense', $cost[0]->ledgerType);
    }

    /**
     * The population-level proof, posted through the real engine: a scaled model of CT-A2's
     * 3,530-unit replay set. Gross revenue and cost of sales must both appear on the ledger in
     * the ratio the legacy ledger showed (549,949 : 463,083), not the net shape's 51,597 : 0.
     */
    public function test_replay_shaped_population_posts_gross_revenue_and_matching_cost_of_sales(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10));
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        // CT-A2 §4.2's own figures, scaled by 1/1000 to keep the fixture small while preserving
        // the exact gross:cost relationship the ruling is about.
        $units = [
            ['sell' => 437.603, 'cost' => 368.500],
            ['sell' => 111.874, 'cost' => 94.583],
            ['sell' => 0.472, 'cost' => 0.000],   // a pure-fee sale (CT-F34's population)
        ];

        $expectedRevenue = 0.0;
        $expectedCost = 0.0;

        foreach ($units as $i => $u) {
            $lines = (new SaleDraftBuilder)->buildLines(
                $this->input($u['sell'], $u['cost'], SaleDraftInput::BASIS_AGENT)
            );

            app(PostingService::class)->post(new DocumentDraft(
                companyId: $company->id,
                branchId: 0,
                docType: 'INV',
                subType: 'SALE',
                docDate: Carbon::create(2026, 6, 15),
                narration: 'R-CT1 replay-shaped unit '.$i,
                lines: $lines,
                idempotencyKey: 'ct-a3-rct1:unit:'.$i,
            ));

            $expectedRevenue += $u['sell'];
            $expectedCost += $u['cost'];
        }

        $revenueRootId = Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->whereNull('parent_id')->where('name', 'Income')->value('id');
        $expenseRootId = Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->whereNull('parent_id')->where('name', 'Expenses')->value('id');

        $revenue = (float) DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->where('je.company_id', $company->id)
            ->whereNotNull('je.posting_date')
            ->where('a.root_id', $revenueRootId)
            ->sum(DB::raw('je.credit - je.debit'));

        $cost = (float) DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->where('je.company_id', $company->id)
            ->whereNotNull('je.posting_date')
            ->where('a.root_id', $expenseRootId)
            ->sum(DB::raw('je.debit - je.credit'));

        $this->assertEqualsWithDelta(
            $expectedRevenue,
            $revenue,
            0.002,
            'GROSS: posted revenue must equal the sum of the SELL prices (the legacy ledger\'s '
            .'549,949 order of magnitude), not the sum of the margins (the net shape\'s 51,597).'
        );

        $this->assertEqualsWithDelta(
            $expectedCost,
            $cost,
            0.002,
            'GROSS: a matching cost-of-sales total must be posted. The net shape posted 0.000 '
            .'here — that is the failure mode this assertion exists to catch.'
        );

        $this->assertGreaterThan(
            0.0,
            $cost,
            'Margin-only posting books no supplier cost at all and must fail this test.'
        );
    }
}
