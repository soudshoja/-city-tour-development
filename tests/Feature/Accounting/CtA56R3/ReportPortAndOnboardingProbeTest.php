<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA56R3;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\PurposeHealthService;
use App\Services\SupplierActivationService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A56 R3 probes — the two things the CT-A6 balance-sheet port and the CT-A5a onboarding fix
 * each get right in the fixture they were written against and wrong on the population they will
 * actually meet.
 */
class ReportPortAndOnboardingProbeTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function makeCompany(bool $trackInvariants = true): Company
    {
        $company = tap(Company::factory()->create(), fn (Company $c) => $c
            ->forceFill(['posting_engine_enabled' => true])->save());
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        if ($trackInvariants) {
            $this->trackCompanyForInvariants($company->id);
        }

        return $company;
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function postRevenue(Company $company, Branch $branch, Carbon $when, float $amount, string $key): void
    {
        app(PostingService::class)->post(new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: $when,
            narration: 'R3 balance-sheet probe',
            lines: [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'debit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0, transactionType: 'R3_BS',
                ),
                new LineDraft(
                    purposeCode: 'MARKUP_INCOME', accountId: null, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0, transactionType: 'R3_BS',
                ),
            ],
            idempotencyKey: $key,
            allowLockedPeriods: true,
            overrideReason: 'R3 probe fixture',
        ));
    }

    /**
     * DEFECT R3-6 — {@see BalanceSheetService} sweeps only the CALENDAR YEAR of `$asOf` into its
     * synthetic "Current Period Profit / (Loss)" Equity line, and excludes the `Income`/`Expenses`
     * roots from the listing entirely. Every prior year's net profit is therefore represented
     * nowhere unless a `doc_type='YEC'` year-end close has swept it into Retained Earnings.
     *
     * The City Travelers ledger has no year-end close of any kind (CT-A1 §7; the replay posts
     * INV/RV/PV/JV/CRN/DBN/OJV/REV and never a YEC), and the engine ledger spans multiple years —
     * 13,603 documents, gross income KWD 561,505.310. So the very first balance sheet this port
     * renders on the real chart cannot foot, by exactly the cumulative pre-`$asOf`-year profit.
     *
     * The port's own fixture test (`CtA6ReconciliationFixtureTest`) posts one document inside one
     * year, which is the single case in which the omission is invisible.
     */
    public function test_the_balance_sheet_foots_across_a_year_boundary_with_no_year_end_close(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $thisYear = Carbon::now()->startOfYear()->addMonths(2);
        $lastYear = Carbon::now()->subYear()->startOfYear()->addMonths(2);

        $this->postRevenue($company, $branch, $lastYear, 300.000, 'r3-bs:prior-year');
        $this->postRevenue($company, $branch, $thisYear, 100.000, 'r3-bs:current-year');

        $sheet = app(BalanceSheetService::class)->generate($company->id, Carbon::now()->endOfYear());

        $this->assertTrue(
            $sheet['totals']['is_balanced'],
            sprintf(
                'Balance sheet does not foot: assets %.3f vs liabilities+equity %.3f, difference '.
                '%.3f — which is exactly the PRIOR year net profit (300.000) that is in neither the '.
                'Equity listing (Income/Expenses roots are excluded) nor the synthetic '.
                '"Current Period Profit" line (calendar year of $asOf only), and that no year-end '.
                'close has swept into Retained Earnings.',
                $sheet['totals']['assets'],
                $sheet['totals']['liabilities_and_equity'],
                $sheet['totals']['difference'],
            )
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // CT-A5a Part B — onboarding
    // ────────────────────────────────────────────────────────────────────────────────────────

    private function makeSupplier(string $name, string $flag): Supplier
    {
        return Supplier::factory()->create([
            'name' => $name,
            'has_flight' => $flag === 'has_flight',
            'has_hotel' => $flag === 'has_hotel',
            'has_visa' => $flag === 'has_visa',
            'has_ferry' => $flag === 'has_ferry',
        ]);
    }

    /**
     * DEFECT R3-7 — CT-A5a's onboarding fix asserts every purpose resolves to a LEAF **once**,
     * inside `CompanyProvisioner::provision()`. `SupplierActivationService::activate()` carries no
     * equivalent assertion and no re-map, and it is callable at any time afterwards
     * (`SupplierCompanyController::activateSupplierProcess`).
     *
     * A company provisioned with no FLIGHT supplier leaves `Suppliers (Flights)` a leaf, so
     * `accounting:coa-linkage --apply` correctly maps `SERVICE_PAYABLE/flight` onto it. Activating
     * a flight supplier later mints a CHILD under it — turning the mapped account into a group and
     * reintroducing, verbatim, the `NonLeafAccountException` the §0.5 item was closed to prevent.
     * The next flight sale for that company cannot post.
     */
    public function test_activating_a_supplier_after_provisioning_does_not_make_a_mapped_purpose_non_leaf(): void
    {
        // Deliberately NOT tracked for the suite invariants. `SupplierActivationService` mints every
        // child at "parent code + 1" unconditionally, so ANY activation on this chart produces a
        // duplicate account code (2121 for flight, 5122 for ferry, and so on) — a SEPARATE, still-
        // open defect measured on its own below (R3-8). Tracking this company would report that one
        // from tearDown() and mask the ordering property this probe exists to pin.
        $company = $this->makeCompany(trackInvariants: false);

        // The provisioning-time state: purposes mapped against the chart as it stands with no
        // flight supplier activated. This is exactly what provision() does at its step 10.
        $exit = Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);
        $this->assertSame(0, $exit, Artisan::output());

        // Scoped to the SERVICE_* family — the one supplier activation reshapes. (A fixture that
        // stops short of `createGatewayCharges()` leaves the three GATEWAY_CLEARING_* purposes
        // mapped to the non-leaf 'Payment Gateway' group; that is its own, separate ordering
        // observation, reported in the R3 findings, not what this probe is measuring.)
        $serviceNonLeaf = fn (array $health) => array_values(array_filter(
            $health['non_leaf'],
            fn (array $row) => str_starts_with((string) $row['purpose'], 'SERVICE_')
        ));

        $before = app(PurposeHealthService::class)->inspect($company->id);
        $this->assertSame(
            [],
            $serviceNonLeaf($before),
            'Pre-condition: provisioning-time mapping of the SERVICE_* purposes is clean. '.json_encode($before['non_leaf'])
        );

        // ... and then, later, an operator activates a flight supplier.
        app(SupplierActivationService::class)->activate($this->makeSupplier('R3 Flight Supplier', 'has_flight'), $company);

        $after = app(PurposeHealthService::class)->inspect($company->id);

        $this->assertSame(
            [],
            $serviceNonLeaf($after),
            'Activating a supplier AFTER provisioning turned a purpose-mapped leaf into a group: '.
            json_encode($serviceNonLeaf($after)).' — the CT-A4 G1 / TE-1 shape, arriving by the one door '.
            'the provisioning-time assertion cannot watch. The next sale for that service type '.
            'throws NonLeafAccountException.'
        );
    }

    /**
     * FINDING R3-8, quantified — `SupplierActivationService::activate()` mints every new supplier
     * leaf at `(int) $parent->code + 1`, unconditionally. Three suppliers of the same service type
     * therefore all get the SAME code. CT-A5a reported the symptom ("3 accounts sharing code
     * 2121") without a count; this measures it.
     *
     * The seeded chart is already colliding before any supplier is activated — `CoaSeeder` seeds
     * BOTH `Suppliers (Hotels)` and `Suppliers (Ferry)` at code 2130, and `Suppliers (Visas)` at
     * 2121, which is `Suppliers (Flights)` (2120) + 1, i.e. the code the FIRST flight supplier
     * activation mints.
     */
    public function test_supplier_activation_still_mints_colliding_account_codes_tracked_gap(): void
    {
        // Deliberately NOT tracked for the suite invariants: this fixture exists to MEASURE the
        // duplicate-code defect, and AccountingInvariants::assertNoDuplicateAccountCodes() would
        // otherwise report it from tearDown() instead of from the assertion below, where it can
        // carry the count and the names.
        $company = $this->makeCompany(trackInvariants: false);

        foreach (['R3 Flight A', 'R3 Flight B', 'R3 Flight C'] as $name) {
            app(SupplierActivationService::class)->activate($this->makeSupplier($name, 'has_flight'), $company);
        }

        $collisions = DB::table('accounts')
            ->where('company_id', $company->id)
            ->select('code', DB::raw('COUNT(*) as n'))
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->code => (int) $row->n])
            ->sortKeys()
            ->all();

        $detail = collect($collisions)->map(function (int $n, string $code) use ($company) {
            $names = Account::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->pluck('name')
                ->implode(', ');

            return "{$code} x{$n} ({$names})";
        })->implode(' | ');

        // A TRACKED GAP, pinned to its exact current shape rather than left red: the code-minting
        // convention is the owner's call (a collision-free scheme cannot stay inside the seeded
        // 2120-2130 / 5110-5121 bands), so R3 measures it and does not change it.
        //
        //   * 2121 x4 — Suppliers (Visas) (seeded 2121) plus all three flight suppliers, because
        //     SupplierActivationService mints at `(int) $parent->code + 1` UNCONDITIONALLY, so N
        //     suppliers of one service type produce N accounts on one code, colliding on top of
        //     whichever sibling the seeder already put there.
        //   * 5111 x4 — the same thing on the cost side (Visa Cost is 'Flights Cost' 5110 + 1).
        //   * 2130 x2 — CoaSeeder's OWN duplicate: it seeds both `Suppliers (Hotels)` and
        //     `Suppliers (Ferry)` at 2130. Nothing to do with activation; already allow-listed by
        //     AccountingInvariants as an explicitly deferred pair.
        //
        // Delete this test the day the minting is fixed. If it starts failing, the shape changed —
        // re-derive, do not widen.
        $this->assertSame(
            ['2121' => 4, '2130' => 2, '5111' => 4],
            $collisions,
            'The known duplicate-account-code shape after three flight-supplier activations has '.
            'changed: '.$detail
        );
    }
}
