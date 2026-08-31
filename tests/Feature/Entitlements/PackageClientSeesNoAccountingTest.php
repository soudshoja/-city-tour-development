<?php

namespace Tests\Feature\Entitlements;

use App\Models\Account;
use App\Models\BankPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Support\Entitlements\ApplyCompanyModulePreset;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * A5 — the regression gate for the accounting-invisibility hardening: a
 * company sold the 5-module TravelERP package (task_uploader,
 * payment_gateway, crm, agent_profit, resayil) but never `module.accounting`
 * must see ZERO trace of accounting anywhere a real user can look, while
 * every module it actually bought keeps working exactly as before.
 *
 * Deliberately adversarial rather than a formality:
 *
 *   1. Every GET route carrying `EnsureModuleEnabled:accounting` is
 *      enumerated from the live router (never hand-listed) and asserted to
 *      404 — not 403 (which would confirm the route exists) and not 200.
 *   2/3. The dashboard body is scanned as raw text for accounting labels,
 *      accounting hrefs, and a planted ledger VALUE (a JournalEntry with a
 *      distinctive `balance`) — not just "the card element is absent".
 *   4. The two GLOBAL layout partials (header dropdown, mobile drawer) are
 *      proven clean on a page that is NOT the dashboard, since they render
 *      on every authenticated page.
 *   5. The same package client is proven to still have full, working access
 *      to all 5 sold modules — a hidden accounting module must not have
 *      broken what was actually bought.
 *   6. Every assertion above is repeated against a SECOND company that HAS
 *      been granted `module.accounting`, and must show the accounting
 *      surface present — proving the "invisible" result isn't just "nothing
 *      rendered for anyone".
 *
 * See this class's own report for two assertions the author judged
 * impractical to make non-vacuous in a feature test, and why.
 */
class PackageClientSeesNoAccountingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    private const ACCOUNTING_PERMISSIONS = [
        'view coa',
        'view account',
        'view credit',
        'view currency exchange',
        'view profit loss',
        'view report',
        'view supplier',
    ];

    private const PACKAGE_PERMISSIONS = [
        'view task',
        'view payment',
        'view client',
        'view agent',
    ];

    /**
     * A canary VALUE (never a canary presence/absence flag) planted as a
     * real `journal_entries.balance` row named exactly like the dashboard's
     * own `JournalEntry::where('name', 'Jazeera Airways Credit')` query
     * (DashboardController::index()). The dashboard blade echoes this raw,
     * unformatted (`{{ $jazeeraCredit->sum('balance') }}`, no
     * number_format/grouping), so this exact digit string is what a leak
     * would look like verbatim in the response body.
     */
    private const CANARY_LEDGER_BALANCE = '918273.456';

    /**
     * Route names whose OWN controller logic returns a business-logic 404
     * for a not-found resource/key, independent of the module gate:
     *
     *   - accounting.reconciliation.row-detail: ReconciliationController::
     *     rowDetail() returns `response()->json([...], 404)` itself whenever
     *     $rowKey doesn't match a row in the LIVE reconciliation grid — a
     *     grid state that depends on real unreconciled ledger data far
     *     beyond this test's scope to fabricate. A "not 404" assertion here
     *     would be indistinguishable from "the module gate let me through"
     *     without also proving that fabricated state, so it is excluded
     *     from the GRANTED-company not-404 loop (it is NOT excluded from
     *     the DENIED-company must-404 loop below — that assertion holds
     *     regardless of which 404 fires).
     */
    private const EXCLUDED_FROM_GRANTED_NOT_404 = [
        'accounting.reconciliation.row-detail',
        // These 5 AccountingController ajax helpers each return their OWN
        // `response()->json([...], 404)` whenever their query finds zero
        // rows (App\Http\Controllers\AccountingController::
        // getSupplierByCompany/getAgentByBranchCompany/
        // getAgentClientByCompany/getBankAccountByCompany/
        // getInvoicesByJournalEntry) -- entirely independent of the module
        // gate. Root-caused via a failing run of this very test:
        // get.suppliers.by.company 404d for the GRANTED company because it
        // requires a real Account whose PARENT NAME contains "Payable";
        // the other 4 have the same shape (a specific account-name
        // convention, a `branch_id` query string this test's path-param
        // substitution does not cover, or a JournalEntry->Invoice linkage)
        // -- fabricating that is unrelated domain-fixture engineering, not
        // proof of the module gate. Still fully covered by the DENIED-
        // company must-404 loop above (correct there either way -- see
        // that test).
        'get.suppliers.by.company',
        'get.agents.by.branch.company',
        'get.agents.clients.by.company',
        'get.bank.accounts.by.company',
        'get.invoices.by.JournalEntry',
    ];

    /** The 7 "hero" routes AccountingRouteGateTest already proves render a
     *  genuine 200 (not just "not a 404") for a fully-permissioned company —
     *  reused here as the strong, deep half of the control check. */
    private const HERO_ROUTES_ASSERT_OK = [
        'coa.index',
        'reports.trial-balance',
        'reports.profit-loss',
        'journal-entries.index',
        'receipt-voucher.index',
        'bank-payments.index',
        'reports.settlements',
    ];

    protected function tearDown(): void
    {
        Company::forgetModuleCache();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * Builds a full Company -> Branch -> Agent -> Client tenant (via
     * CreatesTenantFixtures::createTenant(), the shared fixture builder
     * tests/Feature/Security/*TenantIsolationTest.php already use), applies
     * the real 5-module package preset, and explicitly decides the
     * accounting flag — exactly the two shapes that exist in production:
     * a package client (accounting off) and a company someone deliberately
     * granted it to (accounting on).
     *
     * Also seeds the minimal Chart of Accounts TaskController::index() hard
     * -requires before it renders at all (see AccountingRouteGateTest::
     * createCompanyOwner()'s own docblock for the root-caused reason) —
     * every scenario below needs tasks.index to work regardless of the
     * module gate.
     *
     * @return array{user: \App\Models\User, company: Company, branch: \App\Models\Branch, agent: \App\Models\Agent, client: \App\Models\Client, liabilities: Account, creditors: Account}
     */
    private function makeCompany(bool $grantAccounting): array
    {
        // createTenant([]) with an EMPTY permission list deliberately skips
        // that trait's own role creation (which names the Spatie role
        // 'company-{id}', suffixed to stay collision-safe across multiple
        // tenants in one test — not needed here, since every test method
        // below builds at most one tenant). dashboard.blade.php's stats
        // section (line ~235) gates on the LITERAL Spatie role name
        // `hasRole('company')`, not the legacy `role_id` column, so this
        // test creates that exact role itself — same pattern
        // ModuleEntitlementPolicyTest/AccountingRouteGateTest already use.
        $tenant = $this->createTenant([]);
        $company = $tenant['company'];

        $role = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);
        $tenant['user']->assignRole($role);
        $role->givePermissionTo([...self::ACCOUNTING_PERMISSIONS, ...self::PACKAGE_PERMISSIONS]);

        (new ApplyCompanyModulePreset())->apply($company, array_merge(
            config('modules.package_preset', []),
            [Modules::ACCOUNTING => $grantAccounting]
        ));

        $liabilities = Account::factory()->group()->create([
            'company_id' => $company->id,
            'name' => 'Liabilities',
        ]);
        $creditors = Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Creditors',
            'parent_id' => $liabilities->id,
            'root_id' => $liabilities->id,
        ]);
        foreach (['Assets', 'Equity', 'Income', 'Expenses'] as $rootName) {
            Account::factory()->group()->create(['company_id' => $company->id, 'name' => $rootName]);
        }

        Company::forgetModuleCache();

        return [...$tenant, 'liabilities' => $liabilities, 'creditors' => $creditors];
    }

    /**
     * Seeds one real record for every parameterized accounting GET route
     * (Account/Supplier/Invoice+InvoiceReceipt/BankPayment, plus a fake
     * cheque-image file on the faked 'local' disk) so the GRANTED company's
     * "surfaces present" loop hits real findOrFail()/Gate::authorize()
     * paths instead of manufacturing a 404 of its own that has nothing to
     * do with the module gate.
     *
     * @return array<string, mixed>
     */
    private function seedRouteParamFixtures(array $tenant): array
    {
        $company = $tenant['company'];

        $supplier = Supplier::factory()->create();

        Storage::fake('local');
        $chequePath = 'cheques/'.$company->id.'/canary-cheque.png';
        Storage::disk('local')->put($chequePath, 'not-a-real-image-just-a-canary');

        $bankPayment = BankPayment::create([
            'company_id' => $company->id,
            'branch_id' => $tenant['branch']->id,
            'status' => BankPayment::STATUS_PENDING,
            'amount' => 100,
            'cheque_image_path' => $chequePath,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
        ]);

        $invoiceReceipt = InvoiceReceipt::create([
            'type' => 'account',
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'branch_id' => $tenant['branch']->id,
            'status' => InvoiceReceipt::STATUS_PENDING,
            'amount' => 50,
            'cheque_image_path' => $chequePath,
        ]);

        return [
            'account' => $tenant['creditors'],
            'client' => $tenant['client'],
            'supplier' => $supplier,
            'bankPayment' => $bankPayment,
            'invoiceReceipt' => $invoiceReceipt,
        ];
    }

    /**
     * Plants the ledger-VALUE canary described on CANARY_LEDGER_BALANCE.
     * Uses JournalEntry::create() directly (normal mass-assignment against
     * the model's real $fillable list) rather than JournalEntryFactory,
     * whose definition() sets stale columns (`entry_date`, `user_id`) that
     * no longer exist on `journal_entries` and fail the insert outright —
     * this write only needs the columns the dashboard's own query and view
     * actually read (name/balance/company_id + the FK/date columns every
     * row needs), so it does not need that factory at all.
     */
    private function plantLedgerCanary(Company $company, Account $account): void
    {
        JournalEntry::create([
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'Jazeera Airways Credit',
            'description' => 'A5 regression-gate canary row',
            'balance' => self::CANARY_LEDGER_BALANCE,
            'debit' => 0,
            'credit' => self::CANARY_LEDGER_BALANCE,
            'transaction_date' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Router enumeration (requirement 1) — never hand-listed
    // ------------------------------------------------------------------

    /**
     * Pulls every GET route carrying the `module:accounting` middleware
     * straight from the live route collection. Matches on the middleware
     * STRING as attached to the route (`'module:accounting'`), the same
     * form `->middleware('module:accounting')` calls in routes/web.php use
     * — i.e. before alias resolution, so this does not need to know the
     * alias points at App\Http\Middleware\EnsureModuleEnabled.
     *
     * Also excludes any route that carries a matching
     * `->withoutMiddleware('module:accounting')` — `Route::gatherMiddleware()`
     * (unlike the Router's own request-time `gatherRouteMiddleware()`) does
     * NOT apply that exclusion on its own, so without this a route
     * deliberately carved OUT of the module gate (e.g.
     * receipt-voucher.show, the customer-facing
     * `/receipt-voucher/{companyId}/{voucherNumber}` public link, which
     * explicitly does `->withoutMiddleware(['auth', 'module:accounting'])`)
     * would be wrongly swept into this enumeration — found via a failing
     * run of this very test asserting a route the gate was never meant to
     * cover.
     *
     * @return Collection<int, RoutingRoute>
     */
    private function accountingGetRoutes(): Collection
    {
        return collect(app('router')->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true))
            ->filter(fn (RoutingRoute $route) => in_array('module:accounting', $route->gatherMiddleware(), true))
            ->reject(fn (RoutingRoute $route) => in_array('module:accounting', $route->excludedMiddleware(), true))
            ->values();
    }

    /**
     * Per-route parameter overrides keyed by route NAME (not by parameter
     * name — several routes share a parameter name like `id` against
     * completely different tables, e.g. bank-payments.edit vs
     * receipt-voucher.edit). Anything not listed here needs no override:
     * either it has no route parameters at all, or a bare '1' is a safe,
     * constraint-satisfying placeholder (plain numeric ids/dates with no
     * `where()` regex).
     */
    private function routeOverridesFor(string $routeName, array $fixtures): array
    {
        return match ($routeName) {
            'accounting.statements.show', 'accounting.statements.pdf' => [
                'partyType' => 'client',
                'partyId' => (string) $fixtures['client']->id,
            ],
            'journal-entries.show', 'journal-entries.export.pdf' => [
                'accountId' => (string) $fixtures['account']->id,
            ],
            'journal-entries.index' => ['transactionId' => '1'],
            'bank-payments.edit', 'bank-payments.cheque-image' => [
                'id' => (string) $fixtures['bankPayment']->id,
            ],
            'receipt-voucher.edit', 'receipt-voucher.cheque-image' => [
                'id' => (string) $fixtures['invoiceReceipt']->id,
            ],
            'suppliers.total-ledger' => [
                'supplierId' => (string) $fixtures['supplier']->id,
                'endDate' => '2026-12-31',
            ],
            'suppliers.suppliers.ledger-by-date' => [
                'supplierId' => (string) $fixtures['supplier']->id,
            ],
            default => [],
        };
    }

    private function concreteUri(RoutingRoute $route, array $overrides): string
    {
        $uri = $route->uri();
        foreach ($route->parameterNames() as $name) {
            $value = $overrides[$name] ?? '1';
            $uri = str_replace(['{'.$name.'}', '{'.$name.'?}'], $value, $uri);
        }

        return '/'.ltrim($uri, '/');
    }

    // ------------------------------------------------------------------
    // 1. Every accounting GET route 404s for the DENIED company
    // ------------------------------------------------------------------

    public function test_every_enumerated_accounting_route_404s_for_a_company_without_the_module(): void
    {
        $denied = $this->makeCompany(grantAccounting: false);
        $fixtures = $this->seedRouteParamFixtures($denied);

        $routes = $this->accountingGetRoutes();
        // Sanity: the sweep actually found the accounting surface, so this
        // test cannot pass by iterating zero routes.
        $this->assertGreaterThanOrEqual(60, $routes->count(), 'Router enumeration found far fewer module:accounting GET routes than expected — the filter itself may be broken.');

        $this->actingAs($denied['user']);

        foreach ($routes as $route) {
            $uri = $this->concreteUri($route, $this->routeOverridesFor($route->getName() ?? '', $fixtures));
            $response = $this->get($uri);

            $this->assertSame(
                404,
                $response->getStatusCode(),
                sprintf(
                    'Route [%s] (%s) returned %d for a company with no module.accounting row — expected 404 (never 403, never 200).',
                    $route->getName() ?? '(unnamed)',
                    $uri,
                    $response->getStatusCode()
                )
            );
        }
    }

    // ------------------------------------------------------------------
    // 6a. Control: the SAME enumeration, GRANTED company, must NOT 404
    // ------------------------------------------------------------------

    public function test_every_enumerated_accounting_route_is_reachable_once_the_module_is_granted(): void
    {
        $granted = $this->makeCompany(grantAccounting: true);
        $fixtures = $this->seedRouteParamFixtures($granted);

        $routes = $this->accountingGetRoutes()
            ->reject(fn (RoutingRoute $route) => in_array($route->getName(), self::EXCLUDED_FROM_GRANTED_NOT_404, true));

        $this->actingAs($granted['user']);

        foreach ($routes as $route) {
            $uri = $this->concreteUri($route, $this->routeOverridesFor($route->getName() ?? '', $fixtures));
            $response = $this->get($uri);

            $this->assertNotSame(
                404,
                $response->getStatusCode(),
                sprintf(
                    'Route [%s] (%s) 404d for a company WITH module.accounting granted — the enumeration/control fixture is broken (it would let the denied-company test above pass vacuously).',
                    $route->getName() ?? '(unnamed)',
                    $uri
                )
            );
        }

        // The strong half: a curated subset genuinely renders (200), not
        // merely "some non-404 status" — same 7 routes
        // AccountingRouteGateTest already proves this for.
        foreach (self::HERO_ROUTES_ASSERT_OK as $name) {
            $params = $name === 'journal-entries.index' ? ['transactionId' => 1] : [];
            $this->get(route($name, $params))->assertOk();
        }
    }

    // ------------------------------------------------------------------
    // 2/3. Dashboard: no accounting content, no ledger VALUES, no stat
    //      tiles that could ever say "Error"
    // ------------------------------------------------------------------

    public function test_dashboard_has_no_accounting_content_for_the_denied_company(): void
    {
        $denied = $this->makeCompany(grantAccounting: false);
        $this->plantLedgerCanary($denied['company'], $denied['creditors']);

        $this->actingAs($denied['user']);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $html = $response->getContent();

        foreach ([
            'Payable Supplier',
            'Total Receivable',
            'Total Bank',
            'Gateway Receivable',
            'Jazeera',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "Dashboard leaked accounting label: \"{$needle}\"");
        }

        // The planted ledger VALUE itself — not just the label around it.
        $this->assertStringNotContainsString(
            self::CANARY_LEDGER_BALANCE,
            $html,
            'Dashboard leaked a raw ledger balance value into the response body.'
        );

        foreach ([
            route('reports.payable-supplier'),
            route('reports.total-receivable'),
            route('reports.total-bank'),
            route('reports.gateway-receivable'),
        ] as $accountingUrl) {
            $this->assertStringNotContainsString($accountingUrl, $html, "Dashboard leaked an accounting URL: {$accountingUrl}");
        }

        // No DOM node exists for an accounting stat tile at all — so no
        // client-side fetch failure handler has anywhere to write "Error"
        // into (see fetchStat() in dashboard.blade.php). This is the
        // static-HTML precondition a feature test CAN prove; see this
        // class's report for why the literal JS-written "Error" text
        // itself is out of reach without a browser test.
        foreach (['stat-payable-supplier', 'stat-total-receivable', 'stat-total-bank', 'stat-gateway-receivable'] as $id) {
            $this->assertStringNotContainsString("id=\"{$id}\"", $html, "Dashboard rendered an accounting stat tile node (#{$id}) that has no business existing for a package client.");
        }

        // Catch-all: literal "Error" text anywhere a tile value would sit.
        $this->assertStringNotContainsString('>Error<', $html);
    }

    // ------------------------------------------------------------------
    // 6b. Control: the granted company DOES show the accounting surface
    // ------------------------------------------------------------------

    public function test_dashboard_shows_accounting_content_once_the_module_is_granted(): void
    {
        $granted = $this->makeCompany(grantAccounting: true);
        $this->plantLedgerCanary($granted['company'], $granted['creditors']);

        $this->actingAs($granted['user']);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $html = $response->getContent();

        foreach (['Payable Supplier', 'Total Receivable', 'Total Bank', 'Gateway Receivable', 'Jazeera'] as $needle) {
            $this->assertStringContainsString($needle, $html, "Control dashboard (accounting granted) is missing expected label: \"{$needle}\" — the leak test above would be vacuous.");
        }

        $this->assertStringContainsString(
            self::CANARY_LEDGER_BALANCE,
            $html,
            'Control dashboard (accounting granted) never rendered the planted ledger balance — the canary fixture itself is broken.'
        );

        foreach (['stat-payable-supplier', 'stat-total-receivable', 'stat-total-bank', 'stat-gateway-receivable'] as $id) {
            $this->assertStringContainsString("id=\"{$id}\"", $html);
        }
    }

    // ------------------------------------------------------------------
    // 4. Global layout partials leak nothing on a NON-dashboard page
    // ------------------------------------------------------------------

    public function test_layout_partials_leak_nothing_on_a_non_dashboard_page_for_the_denied_company(): void
    {
        $denied = $this->makeCompany(grantAccounting: false);
        $this->plantLedgerCanary($denied['company'], $denied['creditors']);

        $this->actingAs($denied['user']);

        // tasks.index: a genuinely different page from dashboard, and one
        // this same company is entitled to (task_uploader) — proves the
        // header dropdown (layouts/profile.blade.php) and mobile drawer
        // (layouts/mobile-drawer.blade.php), both @included globally via
        // layouts.navigation, stay clean everywhere, not only on the one
        // page anyone remembered to guard.
        $response = $this->get(route('tasks.index', ['invoiced' => 0]));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('Jazeera', $html, 'A non-dashboard page leaked the Jazeera Airways Credit section from a global layout partial.');
        $this->assertStringNotContainsString(self::CANARY_LEDGER_BALANCE, $html);

        foreach ([
            'Chart of Account',
            'Payment Voucher',
            'Receipt Voucher',
            'Currency Exchange',
            'Transaction List',
            'Creditors Report',
            'Bank Settlement',
        ] as $label) {
            $this->assertStringNotContainsString($label, $html, "Global layout partial leaked accounting nav label: \"{$label}\"");
        }

        foreach ([
            route('coa.index'),
            route('bank-payments.index'),
            route('receipt-voucher.index'),
            route('accounting.index'),
            route('exchange.index'),
        ] as $accountingUrl) {
            $this->assertStringNotContainsString($accountingUrl, $html, "Global layout partial leaked an accounting href: {$accountingUrl}");
        }
    }

    public function test_layout_partials_show_accounting_links_once_the_module_is_granted(): void
    {
        $granted = $this->makeCompany(grantAccounting: true);
        $this->plantLedgerCanary($granted['company'], $granted['creditors']);

        $this->actingAs($granted['user']);

        $response = $this->get(route('tasks.index', ['invoiced' => 0]));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Jazeera', $html, 'Control: global layout partial never shows the Jazeera section even with accounting granted — the leak test above would be vacuous.');
        $this->assertStringContainsString('Chart of Account', $html);
        $this->assertStringContainsString(route('coa.index'), $html);
    }

    // ------------------------------------------------------------------
    // 5. The 5 sold modules keep working for the DENIED company
    // ------------------------------------------------------------------

    public function test_all_five_sold_modules_still_work_for_the_denied_company(): void
    {
        $denied = $this->makeCompany(grantAccounting: false);
        $this->actingAs($denied['user']);

        // task_uploader
        $this->get(route('tasks.index', ['invoiced' => 0]))->assertOk();

        // payment_gateway
        $this->get(route('payment.outstanding'))->assertOk();

        // crm — list, plus the client-credit READ endpoint specifically
        // called out in the task background as "re-scoped to crm/
        // payment_gateway" (ClientController::showCredit(), gated by
        // ClientPolicy::view() -> Modules::CRM).
        $this->get(route('clients.index'))->assertOk();
        $this->get(route('clients.credits', ['id' => $denied['client']->id]))->assertOk();

        // agent_profit — list, plus the dashboard tile's own ajax endpoint,
        // which must keep succeeding precisely BECAUSE it now lives on its
        // own module:agent_profit route rather than the shared
        // module:accounting one (routes/web.php's own split, see
        // reports.ajax.dashboard-stats-profit-agent).
        $this->get(route('agents.index'))->assertOk();
        $this->get(route('reports.ajax.dashboard-stats-profit-agent'))->assertOk();

        // A package-safe report the agent_profit module owns, reachable
        // directly (its route carries no module:accounting middleware at
        // all — see routes/web.php's own "mixes pure accounting reports
        // with ... agent/profit-agent" comment).
        $this->get(route('reports.profit-agent'))->assertOk();
    }

    /** Same 5-module smoke test against the GRANTED company — accounting
     *  being ON must not be what makes the package work either. */
    public function test_all_five_sold_modules_still_work_for_the_granted_company(): void
    {
        $granted = $this->makeCompany(grantAccounting: true);
        $this->actingAs($granted['user']);

        $this->get(route('tasks.index', ['invoiced' => 0]))->assertOk();
        $this->get(route('payment.outstanding'))->assertOk();
        $this->get(route('clients.index'))->assertOk();
        $this->get(route('clients.credits', ['id' => $granted['client']->id]))->assertOk();
        $this->get(route('agents.index'))->assertOk();
        $this->get(route('reports.ajax.dashboard-stats-profit-agent'))->assertOk();
        $this->get(route('reports.profit-agent'))->assertOk();
    }
}
