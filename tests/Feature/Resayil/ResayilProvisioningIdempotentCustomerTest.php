<?php

namespace Tests\Feature\Resayil;

use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Resayil\ResayilProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fix 2 (2026-08-25 pre-pilot defect list): ResayilProvisioningService::
 * provisionAdmin() used to blind-POST a new Resayil customer every time it
 * ran, guaranteeing a 409 on any re-run for an email that already exists as
 * a Resayil customer (a retried onboarding, a resumed wizard, a repaired
 * company). It is now lookup-then-create: GET /customers?email= first, and
 * a 409 that slips through anyway (the create-create race between two
 * concurrent requests) is resolved by re-querying rather than surfaced as a
 * failure.
 *
 * Runs against the isolated `mysql_testing` / `laravel_testing` connection
 * only. All Resayil HTTP calls are faked — no real Resayil API traffic.
 */
class ResayilProvisioningIdempotentCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();

        config([
            'resayil.reseller_token' => 'fake-reseller-token-for-test',
            'resayil.test_mode' => false,
        ]);
    }

    private function makeCompanyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_calling_provisioning_twice_for_the_same_email_produces_one_customer_not_a_conflict(): void
    {
        $user = $this->makeCompanyUser();
        $email = $user->email;

        $postCount = 0;
        $getCount = 0;

        Http::fake([
            'api.resayil.io/v1/resellers/customers*' => function (HttpRequest $request) use (&$postCount, &$getCount, $email) {
                if ($request->method() === 'GET') {
                    $getCount++;

                    // First lookup: nothing exists yet.
                    if ($getCount === 1) {
                        return Http::response([], 200);
                    }

                    // Second lookup (after local state is wiped, simulating
                    // a retried onboarding / resumed wizard): the customer
                    // created by the first run is now found.
                    return Http::response([
                        ['id' => 'cust-idempotent-1', 'email' => $email],
                    ], 200);
                }

                // POST — a real create. Must happen AT MOST ONCE. If the
                // fix regresses to blind-POST on the second run, this
                // second POST would be sent and must come back as a 409 so
                // the test can prove the code path was exercised and
                // correctly avoided/handled it either way.
                $postCount++;

                if ($postCount === 1) {
                    return Http::response(['id' => 'cust-idempotent-1'], 200);
                }

                return Http::response(['message' => 'Conflict: email already exists'], 409);
            },
        ]);

        // Run 1: genuinely creates the customer.
        $service = app(ResayilProvisioningService::class);
        $first = $service->ensureUserProvisioned($user);

        $this->assertSame(ResayilAccount::STATUS_PROVISIONED, $first->status);
        $this->assertSame('cust-idempotent-1', $first->resayil_customer_id);
        $this->assertSame(1, $postCount, 'Exactly one customer must have been created on the first run.');

        // Simulate the scenario Fix 2 targets: local provisioning state is
        // gone (row deleted — e.g. a repaired company, a wiped dev DB row,
        // a resumed wizard that lost its pointer) but the Resayil customer
        // for this email genuinely still exists remotely.
        ResayilAccount::query()->where('user_id', $user->id)->delete();

        // Run 2: must adopt the existing remote customer, not re-POST.
        $second = $service->ensureUserProvisioned($user->fresh());

        $this->assertSame(ResayilAccount::STATUS_PROVISIONED, $second->status);
        $this->assertSame('cust-idempotent-1', $second->resayil_customer_id, 'Re-run must resolve to the SAME customer id — one customer, not two.');
        $this->assertSame(1, $postCount, 'The second run must NOT have issued a second POST /customers call.');
        $this->assertSame(2, $getCount, 'The second run must have looked the customer up by email before deciding whether to create.');

        // Only one ResayilAccount row exists for this company overall.
        $this->assertSame(
            1,
            ResayilAccount::where('company_id', $first->company_id)->where('role', ResayilAccount::ROLE_ADMIN)->count()
        );
    }

    public function test_a_409_create_race_is_resolved_by_requery_not_surfaced_as_a_failure(): void
    {
        $user = $this->makeCompanyUser();

        $postCount = 0;
        $getCount = 0;

        Http::fake([
            'api.resayil.io/v1/resellers/customers*' => function (HttpRequest $request) use (&$postCount, &$getCount) {
                if ($request->method() === 'GET') {
                    $getCount++;

                    // First lookup (pre-create check): not found yet — this
                    // request "loses" a race against a concurrent request
                    // that creates the customer a moment later.
                    if ($getCount === 1) {
                        return Http::response([], 200);
                    }

                    // Second lookup (post-409 recovery): the concurrent
                    // winner's customer is now visible.
                    return Http::response([
                        'id' => 'cust-race-winner',
                        'email' => 'unused@placeholder.test',
                    ], 200);
                }

                // POST: this request loses the race — Resayil already has a
                // customer for this email (created by the concurrent
                // request between our GET and our POST).
                $postCount++;

                return Http::response(['message' => 'Conflict', 'errorCode' => 'error:conflict'], 409);
            },
        ]);

        $account = app(ResayilProvisioningService::class)->ensureUserProvisioned($user);

        $this->assertSame(
            ResayilAccount::STATUS_PROVISIONED,
            $account->status,
            'A 409 create-race must resolve to PROVISIONED via re-query, never STATUS_ERROR.'
        );
        $this->assertSame('cust-race-winner', $account->resayil_customer_id);
        $this->assertSame(1, $postCount);
        $this->assertSame(2, $getCount, 'Must re-query after the 409 to adopt the winning customer.');

        $meta = $account->meta ?? [];
        $this->assertTrue($meta['adopted_after_conflict'] ?? false, 'meta should record that this row was adopted after a create-race conflict.');
    }
}
