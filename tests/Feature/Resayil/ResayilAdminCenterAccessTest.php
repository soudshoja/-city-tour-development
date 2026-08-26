<?php

namespace Tests\Feature\Resayil;

use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Resayil Admin Center — slice 1 access + isolation contract
 * (plan .planning/specs/RESAYIL-ADMIN-CENTER.md §9.2 / §9.3).
 *
 * Five things must hold, and each is one test below:
 *
 *  1. A company sees ONLY its own Resayil workspace. The reseller token
 *     can read every customer on the platform, so this is the property
 *     that keeps one agency's WhatsApp number out of another's panel.
 *  2. A forged company_id / customer_id / device_id in the request is
 *     ignored — every id comes from the authenticated user's own admin
 *     row, and no route in the group takes a Resayil id as a parameter.
 *  3. Roles outside {ADMIN, COMPANY} are refused (403) by the
 *     `can:manage-resayil` gate.
 *  4. A company without `module.resayil` gets 404, not 403 — the module
 *     middleware runs first so an un-entitled company cannot even learn
 *     the section exists.
 *  5. Pause/resume is operator-only and confirm-gated: a COMPANY user
 *     gets 403, and an unconfirmed POST changes nothing.
 *
 * Runs on the isolated mysql_testing / laravel_testing connection. All
 * Resayil HTTP is faked — no real API traffic, no real device is ever
 * disabled.
 */
class ResayilAdminCenterAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();

        Company::forgetModuleCache();

        config([
            'resayil.reseller_base_url' => 'https://api.resayil.io/v1/resellers',
            'resayil.reseller_token' => 'fake-reseller-token-for-test',
            'resayil.test_mode' => false,
            'resayil.admin_cache_ttl' => 0,
        ]);

        // Two distinct Resayil workspaces, each with one device. The
        // reseller token legitimately sees both; the panel must not.
        Http::fake([
            'api.resayil.io/v1/resellers/customers/cust-A*' => Http::response([
                'id' => 'cust-A',
                'displayName' => 'Alpha Travel',
                'email' => 'owner@alpha.test',
                'accountType' => 'business',
                'status' => 100,
                'createdAt' => '2026-01-01T00:00:00.000Z',
                'billingProfile' => ['companyName' => 'Alpha Travel', 'country' => 'KW'],
            ], 200),
            'api.resayil.io/v1/resellers/customers/cust-B*' => Http::response([
                'id' => 'cust-B',
                'displayName' => 'Beta Tours',
                'email' => 'owner@beta.test',
                'accountType' => 'business',
                'status' => 100,
                'createdAt' => '2026-02-02T00:00:00.000Z',
                'billingProfile' => ['companyName' => 'Beta Tours', 'country' => 'KW'],
            ], 200),
            'api.resayil.io/v1/resellers/devices*' => function ($request) {
                // Mirrors the live API, which DOES filter /devices by ?user=.
                $user = $request->data()['user'] ?? null;
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $user = $user ?: ($query['user'] ?? null);

                $all = [
                    'cust-A' => [
                        'id' => 'device-A',
                        'phone' => '+96500000001',
                        'alias' => 'Alpha Line',
                        'status' => 'operative',
                        'user' => ['id' => 'cust-A'],
                        'session' => ['status' => 'online', 'linkedDevices' => 2],
                        'billing' => ['subscription' => [
                            'status' => 'active',
                            'billingStatus' => 'active',
                            'planCode' => 'io-enterprise',
                            'agents' => 8,
                            'interval' => 'month',
                            // The live API returns a price here. It must
                            // never survive into the rendered page.
                            'price' => 124.9,
                            'usage' => ['textMessages' => 10],
                        ]],
                        'health' => ['score' => 97, 'tier' => 'normal', 'metrics' => [], 'reasons' => []],
                    ],
                    'cust-B' => [
                        'id' => 'device-B',
                        'phone' => '+96500000002',
                        'alias' => 'Beta Line',
                        'status' => 'operative',
                        'user' => ['id' => 'cust-B'],
                        'session' => ['status' => 'online', 'linkedDevices' => 1],
                        'billing' => ['subscription' => [
                            'status' => 'active',
                            'billingStatus' => 'active',
                            'planCode' => 'io-business',
                            'agents' => 5,
                            'interval' => 'month',
                            'price' => 87.4,
                            'usage' => ['textMessages' => 20],
                        ]],
                        'health' => ['score' => 90, 'tier' => 'normal', 'metrics' => [], 'reasons' => []],
                    ],
                ];

                return Http::response(isset($all[$user]) ? [$all[$user]] : [], 200);
            },
            'api.resayil.io/v1/resellers/customers/*/payments*' => Http::response([], 200),
        ]);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function makeCompany(string $customerId, bool $moduleEnabled = true): array
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id]);

        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => Modules::settingKey(Modules::RESAYIL)],
            ['type' => 'boolean', 'value' => $moduleEnabled]
        );
        Company::forgetModuleCache();

        ResayilAccount::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => ResayilAccount::ROLE_ADMIN,
            'resayil_customer_id' => $customerId,
            'resayil_email' => $user->email,
            'status' => ResayilAccount::STATUS_PROVISIONED,
            'provisioned_at' => now(),
        ]);

        return [$user->fresh(), $company];
    }

    public function test_a_company_sees_only_its_own_workspace_and_number(): void
    {
        [$alpha] = $this->makeCompany('cust-A');
        $this->makeCompany('cust-B');

        $response = $this->actingAs($alpha)->getJson(route('resayil-admin.overview'));

        $response->assertOk();
        $overview = $response->json('overview');

        $this->assertSame('cust-A', $overview['workspace']['customer_id']);
        $this->assertSame('+96500000001', $overview['device']['phone']);

        // The other tenant must appear nowhere in the payload.
        $blob = json_encode($overview);
        $this->assertStringNotContainsString('cust-B', $blob);
        $this->assertStringNotContainsString('+96500000002', $blob);
        $this->assertStringNotContainsString('Beta', $blob);
    }

    public function test_forged_ids_in_the_request_are_ignored(): void
    {
        [$alpha] = $this->makeCompany('cust-A');
        $this->makeCompany('cust-B');

        $response = $this->actingAs($alpha)->getJson(
            route('resayil-admin.overview').'?company_id=999&customer_id=cust-B&device_id=device-B'
        );

        $response->assertOk();
        $overview = $response->json('overview');

        // Still Alpha's own workspace, untouched by the forged parameters.
        $this->assertSame('cust-A', $overview['workspace']['customer_id']);
        $this->assertSame('device-A', $overview['device']['id']);
    }

    public function test_no_resayil_price_is_ever_exposed(): void
    {
        [$alpha] = $this->makeCompany('cust-A');

        // Owner decision D-1: no Resayil price renders client-facing, ever.
        // The faked device carries price 124.9 precisely so this can fail
        // loudly if the projection ever turns into a blocklist.
        $json = $this->actingAs($alpha)->getJson(route('resayil-admin.overview'))->getContent();
        $this->assertStringNotContainsString('124.9', $json);
        $this->assertStringNotContainsString('"price"', $json);

        $html = $this->actingAs($alpha)->get(route('resayil-admin.index'))->getContent();
        $this->assertStringNotContainsString('124.9', $html);
    }

    public static function refusedRoleProvider(): array
    {
        return [
            'branch' => [Role::BRANCH],
            'agent' => [Role::AGENT],
            'accountant' => [Role::ACCOUNTANT],
            'client' => [Role::CLIENT],
        ];
    }

    /**
     * @dataProvider refusedRoleProvider
     */
    public function test_roles_outside_admin_and_company_are_refused(int $roleId): void
    {
        // The user belongs to a company WITH the module, so a 403 here can
        // only come from the manage-resayil gate, never from the module
        // middleware.
        [, $company] = $this->makeCompany('cust-A');

        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)
            ->get(route('resayil-admin.index'))
            ->assertForbidden();
    }

    public function test_a_company_without_the_module_gets_404_not_403(): void
    {
        [$user] = $this->makeCompany('cust-A', moduleEnabled: false);

        // 404, deliberately: a 403 would confirm the section exists.
        $this->actingAs($user)->get(route('resayil-admin.index'))->assertNotFound();
        $this->actingAs($user)->getJson(route('resayil-admin.overview'))->assertNotFound();
        $this->actingAs($user)->getJson(route('resayil-admin.billing.payments'))->assertNotFound();
        $this->actingAs($user)->postJson(route('resayil-admin.device.pause'), ['confirmed' => true])->assertNotFound();
    }

    public function test_pause_and_resume_are_operator_only(): void
    {
        [$companyUser] = $this->makeCompany('cust-A');

        // A COMPANY user passes can:manage-resayil but must still be
        // refused: pausing takes a live business number offline (U-6).
        $this->actingAs($companyUser)
            ->postJson(route('resayil-admin.device.pause'), ['confirmed' => true])
            ->assertForbidden();

        $this->actingAs($companyUser)
            ->postJson(route('resayil-admin.device.resume'), ['confirmed' => true])
            ->assertForbidden();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/disable')
            || str_contains($request->url(), '/enable'));
    }

    public function test_an_unconfirmed_pause_changes_nothing(): void
    {
        [, $company] = $this->makeCompany('cust-A');

        $operator = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->actingAs($operator)
            ->postJson(route('resayil-admin.device.pause'), [])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/disable'));
    }

    public function test_the_panel_renders_for_a_company_with_no_workspace_yet(): void
    {
        // The state most clients see first: module on, no admin row. It
        // must render an explained page, never a 404, 500 or blank screen.
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => Modules::settingKey(Modules::RESAYIL)],
            ['type' => 'boolean', 'value' => true]
        );
        Company::forgetModuleCache();

        $this->actingAs($user->fresh())
            ->get(route('resayil-admin.index'))
            ->assertOk()
            ->assertSee("isn't set up yet", escape: false);
    }
}
