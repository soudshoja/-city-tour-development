<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA56R3;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Country;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\PurposeHealthService;
use App\Services\CompanyProvisioner;
use App\Services\SupplierActivationService;
use App\Support\CompanyRegistrationData;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
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
     * CT-D2b — the two onboarding probes below now provision through {@see CompanyProvisioner}
     * instead of `makeCompany()` + a hand-run `accounting:coa-linkage --apply`.
     *
     * Re-derived, not widened. R3 wrote them against PR #13's head, where the light fixture was
     * adequate. On the merged CT-D2 head PR #14 makes a NON-LEAF purpose mapping unconditionally
     * blocking in `CoaLinkage::verifyPurposes()` *regardless of purpose prefix* — deliberately, to
     * close the travelerp finding where `--apply` minted Knet/uPayment leaves under the `1300
     * Payment Gateway` pool and exited 0 with Hesabe/MyFatoorah/Tap left pointing at the
     * now-non-leaf pool. A fixture that stops short of `createGatewayCharges()` is exactly that
     * shape, so `--apply` now exits 1 on it and R3's own pre-condition assertion (`assertSame(0,
     * $exit)`) fails — which is the new ratchet biting the fixture, not a regression.
     *
     * `provision()` runs `createGatewayCharges()` before `mapAccountingPurposes()`, so every
     * GATEWAY_* purpose lands on a real leaf and the only non-leaf a probe can then produce is the
     * one supplier activation creates — which is the property these two probes exist to measure.
     * It is also the path the defect actually arrives by
     * (`SupplierCompanyController::activateSupplierProcess` on a provisioned company).
     */
    private function provisionCompany(array $supplierIds = []): Company
    {
        $unique = uniqid();

        $company = app(CompanyProvisioner::class)->provision(CompanyRegistrationData::fromArray([
            'company_name' => 'R3 Probe Co '.$unique,
            'company_code' => 'R3-'.$unique,
            'country_id' => Country::factory()->create()->id,
            'company_email' => "owner-{$unique}@example.test",
            'owner_name' => 'Test Owner',
            'owner_email' => "owner-{$unique}@example.test",
            'owner_password' => 'password12345',
            'currency' => 'KWD',
            'supplier_ids' => $supplierIds,
        ]));

        $company->forceFill(['posting_engine_enabled' => true])->save();

        return $company->fresh();
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
        // CT-D2b: provisioned with NO suppliers, so `Suppliers (Flights)` / `Flights Cost` are
        // still LEAVES when provision() maps the purposes onto them — the exact state R3-7 needs.
        // Tracked for the suite invariants now that CT-A4b's AccountCodeGenerator has closed R3-8:
        // activation no longer mints a duplicate code, so tearDown()'s
        // assertNoDuplicateAccountCodes() is a live guard here rather than a masked failure.
        $company = $this->provisionCompany([]);
        $this->trackCompanyForInvariants($company->id);

        // Scoped to the SERVICE_* family — the one supplier activation reshapes.
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
     * The seeded chart was already colliding before any supplier was activated — `CoaSeeder` seeded
     * BOTH `Suppliers (Hotels)` and `Suppliers (Ferry)` at code 2130, and `Suppliers (Visas)` at
     * 2121, which is `Suppliers (Flights)` (2120) + 1, i.e. the code the FIRST flight supplier
     * activation minted.
     *
     * CT-D2b — INVERTED on the merged CT-D2 head. R3-8 was a RISK reported and NOT fixed, pinned
     * here as a shrink-only tracked gap ("Delete this test the day the minting is fixed"). PR #14
     * (CT-A4b) is that day and lands on the same head: `AccountCodeGenerator` now allocates every
     * child code, and `CoaSeeder`'s own 2130 duplicate is gone (Ferry -> 2131). The measurement is
     * kept and its expectation flipped to the empty set, so the probe that quantified the defect
     * becomes the regression guard on the exact population that exposed it.
     */
    public function test_supplier_activation_no_longer_mints_colliding_account_codes_r3_8_closed(): void
    {
        $company = $this->provisionCompany([]);
        $this->trackCompanyForInvariants($company->id);

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

        // CT-D2b — RE-DERIVED, per this test's own standing instruction ("Delete this test the day
        // the minting is fixed. If it starts failing, the shape changed — re-derive, do not
        // widen."). PR #14 (CT-A4b) IS that day, and it lands on the same head as R3:
        //
        //   * `SupplierActivationService` no longer mints at `(int) $parent->code + 1`; every child
        //     goes through the single `AccountCodeGenerator` allocator, whose `codeExists()` guard
        //     refuses any code already taken anywhere in the company's chart. 2121 x4 and 5111 x4
        //     are gone.
        //   * `CoaSeeder`'s own duplicate is gone too — `Suppliers (Ferry)` moved 2130 -> 2131 —
        //     and `AccountingInvariants::assertNoDuplicateAccountCodes()` no longer tolerates the
        //     pair.
        //
        // The measurement is kept (not deleted) and inverted: R3-8 was a RISK reported and not
        // fixed, so the probe that quantified it becomes the regression guard that proves it stays
        // fixed on the very population that exposed it — three suppliers of ONE service type, the
        // case "parent code + 1" could never survive.
        $this->assertSame(
            [],
            $collisions,
            'R3-8 has regressed: activating three flight suppliers minted duplicate account codes '.
            'again ('.$detail.'). CT-A4b routed this through AccountCodeGenerator precisely so this '.
            'set stays empty.'
        );
    }
}
