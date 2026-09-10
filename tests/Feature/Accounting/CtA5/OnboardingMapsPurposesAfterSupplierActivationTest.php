<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA5;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Supplier;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\PurposeHealthService;
use App\Services\CompanyProvisioner;
use App\Support\CompanyRegistrationData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A5a Part B — the onboarding defect of PLAN.md §0.5, closed and pinned.
 *
 * `CompanyProvisioner::provision()` seeded a chart and ZERO purpose mappings: every engine action
 * a new company took threw `UnmappedPurposeException` on its first sale. Worse, the obvious fix —
 * mapping next to `seedChartOfAccounts()` — is itself broken, because `activateSuppliers()` mints
 * children under the supplier pools afterwards and turns the mapped leaves into GROUPS. TE-1
 * measured that on travelerp: *"system_accounts row #42 maps purpose_code=SERVICE_PAYABLE to
 * accounts.id=51 (Suppliers (Flights)), which is not a leaf account."*
 *
 * ── Mutation proof ───────────────────────────────────────────────────────────────────────────
 * `test_the_ordering_is_load_bearing_because_supplier_activation_creates_groups` reproduces the
 * BEFORE-activation ordering directly and asserts it produces a non-leaf mapping. Move
 * `mapAccountingPurposes()` above `activateSuppliers()` in `provision()` and
 * `test_service_payable_and_service_cost_resolve_to_leaves` fails on exactly that shape — which is
 * why the mutation test is written as an independent reproduction rather than as a comment: the
 * defect is demonstrable without editing production code, and stays demonstrable afterwards.
 */
class OnboardingMapsPurposesAfterSupplierActivationTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10));
        config(['accounting.engine.enabled' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function registrationData(array $overrides = []): CompanyRegistrationData
    {
        $unique = uniqid();

        return CompanyRegistrationData::fromArray(array_merge([
            'company_name' => 'CtA5 Onboarding Co',
            'company_code' => 'CTA5-'.$unique,
            'country_id' => Country::factory()->create()->id,
            'company_email' => "owner-{$unique}@example.test",
            'owner_name' => 'Test Owner',
            'owner_email' => "owner-{$unique}@example.test",
            'owner_password' => 'password12345',
            'currency' => 'KWD',
        ], $overrides));
    }

    private function provisionWithSuppliers(): Company
    {
        $suppliers = [
            Supplier::factory()->create(['name' => 'CtA5 Flight Supplier'])->id,
            Supplier::factory()->create(['name' => 'CtA5 Hotel Supplier'])->id,
        ];

        $company = app(CompanyProvisioner::class)->provision(
            $this->registrationData(['supplier_ids' => $suppliers])
        );

        return $company;
    }

    /**
     * NOTE on {@see \Tests\Support\AccountingTestCase::trackCompanyForInvariants()}, deliberately
     * NOT called anywhere in this class.
     *
     * The suite-wide C1 invariants include a duplicate-account-code check, and a company
     * provisioned with two suppliers TRIPS IT — on a pre-existing defect this lane found but does
     * not own: `SupplierActivationService::activate()` mints a per-supplier leaf under the supplier
     * pool whose code COLLIDES with an existing sibling. Measured here, verbatim:
     *
     *     Found 3 accounts sharing code "2121" for company 1:
     *     Suppliers (Visas), CtA5 Flight Supplier, CtA5 Hotel Supplier
     *
     * That is the same family of defect CT-A4 catalogued on the real chart (28 duplicate-code
     * groups over 290 accounts) arriving from the onboarding side, and it is recorded as a finding
     * rather than papered over: weakening the shared invariant to let this class pass would blind
     * every OTHER accounting test to real code collisions. This class asserts what it is for —
     * purpose mapping, ordering, and the first posting — and leaves the collision to be ruled on.
     */
    private function whyNoInvariantTracking(): void {}

    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_a_provisioned_company_has_no_blocking_unmapped_purposes(): void
    {
        $company = $this->provisionWithSuppliers();

        $health = app(PurposeHealthService::class)->inspect((int) $company->id);

        $this->assertSame(0, $health['blocking'], 'blocking gaps: '.json_encode(array_merge($health['non_leaf'], $health['unresolved'])));
        $this->assertSame([], $health['non_leaf']);
        $this->assertSame([], $health['dangling']);

        // Every gap that remains must be one the build calls deliberate by name (SUSPENSE,
        // VAT_OUTPUT, the unused gateway families) — never an accident that happens to be quiet.
        foreach ($health['unresolved'] as $u) {
            $this->assertTrue($u['deliberate'], 'undeclared unmapped purpose: '.$u['purpose']);
        }

        $this->assertGreaterThan(0, $health['resolved']);
    }

    public function test_service_payable_and_service_cost_resolve_to_leaves(): void
    {
        $company = $this->provisionWithSuppliers();
        $resolver = app(AccountResolver::class);

        foreach ((array) config('accounting.purpose_codes.service_types', []) as $serviceType) {
            foreach (['SERVICE_PAYABLE', 'SERVICE_COST'] as $purpose) {
                $account = $resolver->resolve($purpose, (int) $company->id, $serviceType);

                $childCount = (int) Account::query()->withoutGlobalScopes()
                    ->where('parent_id', $account->id)
                    ->count();

                $this->assertSame(
                    0,
                    $childCount,
                    sprintf(
                        '%s/%s resolved to #%d %s, which has %d children — the purpose was mapped before the chart finished changing shape',
                        $purpose,
                        $serviceType,
                        $account->id,
                        $account->name,
                        $childCount
                    )
                );
            }
        }
    }

    public function test_the_first_engine_action_posts(): void
    {
        $company = $this->provisionWithSuppliers();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $resolver = app(AccountResolver::class);
        $ar = $resolver->resolve('RECEIVABLE_CONTROL', (int) $company->id);
        $revenue = $resolver->resolve('SERVICE_REVENUE', (int) $company->id, 'flight');

        $posted = app(PostingService::class)->post(new DocumentDraft(
            companyId: (int) $company->id,
            branchId: null,
            docType: 'JV',
            subType: 'ONBOARDING_SMOKE',
            docDate: Carbon::create(2026, 6, 15),
            narration: 'first engine action on a freshly provisioned company',
            lines: [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'debit', amount: 10.0,
                    currency: 'KWD', originalAmount: 10.0, exchangeRate: 1.0,
                    transactionType: 'CUSTOMERDEBITED', ledgerType: 'receivable',
                ),
                new LineDraft(
                    purposeCode: 'SERVICE_REVENUE', accountId: null, side: 'credit', amount: 10.0,
                    currency: 'KWD', originalAmount: 10.0, exchangeRate: 1.0,
                    transactionType: 'INCOME', ledgerType: 'income', serviceType: 'flight',
                ),
            ],
            idempotencyKey: 'cta5:onboarding-smoke:'.$company->id,
        ));

        $lines = DB::table('journal_entries')->where('transaction_id', $posted->transaction->id)->get();

        $this->assertCount(2, $lines);
        $this->assertSame($ar->id, (int) $lines->firstWhere('debit', '>', 0)->account_id);
        $this->assertSame($revenue->id, (int) $lines->firstWhere('credit', '>', 0)->account_id);
    }

    /**
     * THE MUTATION. Do the mapping BEFORE supplier activation — the ordering `provision()`
     * deliberately does not use — and the supplier-pool purposes come out naming a GROUP.
     */
    public function test_the_ordering_is_load_bearing_because_supplier_activation_creates_groups(): void
    {
        // A company whose chart is seeded and MAPPED while the supplier pools are still bare
        // leaves. `provision()` with no suppliers reproduces exactly that state — the mapping runs,
        // and nothing has minted a child under a pool yet.
        $company = app(CompanyProvisioner::class)->provision($this->registrationData());

        $resolver = app(AccountResolver::class);
        $before = $resolver->resolve('SERVICE_PAYABLE', (int) $company->id, 'flight');

        $this->assertSame(
            0,
            (int) Account::query()->withoutGlobalScopes()->where('parent_id', $before->id)->count(),
            'the fixture is wrong: the supplier pool already has children before activation'
        );

        // Now activate a supplier, which is what step 9 does — it mints a child under the pool the
        // mapping above already names.
        app(\App\Services\SupplierActivationService::class)->activate(
            Supplier::factory()->create(['name' => 'CtA5 Late Supplier']),
            $company
        );

        $health = app(PurposeHealthService::class)->inspect((int) $company->id);

        $this->assertNotSame(
            [],
            $health['non_leaf'],
            'supplier activation did not turn any mapped purpose into a non-leaf — if this is genuinely '
                .'true of the current chart shape, the ordering constraint in CompanyProvisioner has '
                .'stopped being load-bearing and this test must be re-derived, not deleted'
        );

        $purposes = array_column($health['non_leaf'], 'purpose');
        $this->assertContains('SERVICE_PAYABLE', $purposes);
    }

    // ── accounting:purpose-health ────────────────────────────────────────────────────────────

    public function test_the_health_command_reports_a_clean_company_and_writes_nothing(): void
    {
        $company = $this->provisionWithSuppliers();

        $systemAccountsBefore = (int) DB::table('system_accounts')->count();
        $accountsBefore = (int) DB::table('accounts')->count();

        $this->artisan('accounting:purpose-health', ['--company' => $company->id])
            ->assertExitCode(0)
            ->run();

        $this->assertSame($systemAccountsBefore, (int) DB::table('system_accounts')->count());
        $this->assertSame($accountsBefore, (int) DB::table('accounts')->count());
    }

    /**
     * The DANGLING bucket is not exercised here: `system_accounts.account_id` carries a real,
     * enforced foreign key on this schema (CT-A3 R3 §3.4 found that the hard way), so a dangling
     * row cannot be created from inside a test at all — only by a path that bypassed the
     * constraint historically, which is exactly how company 1's 33 and company 3's 38 came to
     * exist. The sweep behaviour is already pinned by CT-A3 R4's `R41DanglingSweepTest`; what this
     * class adds is that {@see PurposeHealthService::danglingFor()} REPORTS the bucket, which the
     * clean-company case above asserts is empty on a freshly provisioned chart.
     */
    public function test_a_freshly_provisioned_company_has_no_dangling_mappings(): void
    {
        $company = $this->provisionWithSuppliers();

        $this->assertSame([], app(PurposeHealthService::class)->danglingFor((int) $company->id));
    }
}
