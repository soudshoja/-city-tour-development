<?php

namespace Tests\Feature\Resayil;

use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Resayil\ResayilProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Fix 1 (2026-08-25 pre-pilot defect list): ResayilProvisioningService used
 * to generate a per-account secret, POST it to Resayil, and immediately
 * unset() it — discarding the only credential that could ever log the
 * created account in. This proves the fix: the secret is now persisted,
 * encrypted at rest (not plaintext in the DB), hidden from array/JSON
 * serialization, and never appears in a log line even when the upstream
 * API echoes password-shaped fields back in an error body.
 *
 * Runs against the isolated `mysql_testing` / `laravel_testing` connection
 * only (RefreshDatabase + phpunit.xml's DB_CONNECTION=mysql_testing) — the
 * shared dev DB (citycomm_city-tour-test) is never touched. All Resayil
 * HTTP calls are faked (Http::fake) — no real Resayil API traffic.
 */
class ResayilProvisioningSecretTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();
    }

    private function makeCompanyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_admin_secret_is_persisted_encrypted_and_matches_what_was_sent(): void
    {
        config([
            'resayil.reseller_token' => 'fake-reseller-token-for-test',
            'resayil.test_mode' => false,
        ]);

        // Fix 2 (lookup-then-create) means provisionAdmin() now issues a
        // GET /customers?email= lookup before any POST — respond "not
        // found" to the GET so the POST (the thing this test is actually
        // about) is genuinely exercised.
        Http::fake([
            'api.resayil.io/v1/resellers/customers*' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200);
                }

                return Http::response(['id' => 'cust_evidence_1'], 200);
            },
        ]);

        // Any log call during the success path must not carry the secret.
        $loggedPayloads = [];
        Log::listen(function ($event) use (&$loggedPayloads) {
            $loggedPayloads[] = $event->message.' '.json_encode($event->context);
        });

        $user = $this->makeCompanyUser();

        $account = app(ResayilProvisioningService::class)->ensureUserProvisioned($user);

        $this->assertSame(ResayilAccount::STATUS_PROVISIONED, $account->status);
        $this->assertSame('cust_evidence_1', $account->resayil_customer_id);

        // Capture exactly what password we told Resayil to set.
        $sentPassword = null;
        Http::assertSent(function ($request) use (&$sentPassword) {
            $sentPassword = $request['password'] ?? null;

            return true;
        });

        $this->assertNotEmpty($sentPassword, 'The provisioning call must have sent a password.');
        $this->assertSame(32, strlen($sentPassword));

        // 1) The secret is PERSISTED — no longer unset()/discarded.
        $account->refresh();
        $this->assertNotNull($account->resayil_secret);
        $this->assertSame($sentPassword, $account->resayil_secret, 'Decrypted attribute must equal the password actually sent to Resayil.');

        // 2) It is ENCRYPTED AT REST — raw DB column is not the plaintext,
        // and is exactly Laravel's `encrypted` cast envelope (decryptable
        // via Crypt, i.e. APP_KEY-based, not hand-rolled).
        $raw = DB::connection('mysql_testing')
            ->table('resayil_accounts')
            ->where('id', $account->id)
            ->value('resayil_secret');

        $this->assertNotNull($raw);
        $this->assertNotSame($sentPassword, $raw, 'Raw DB column must NOT contain the plaintext secret.');
        $this->assertStringNotContainsString($sentPassword, $raw, 'Raw DB column must not even embed the plaintext as a substring.');
        $this->assertSame($sentPassword, Crypt::decryptString($raw), 'The raw column must be a genuine Laravel encrypted() envelope for the same secret.');

        // 3) NEVER rendered to a page / API body: hidden from serialization.
        $array = $account->toArray();
        $this->assertArrayNotHasKey('resayil_secret', $array);
        $this->assertArrayNotHasKey('resayil_account_token', $array);
        $json = $account->toJson();
        $this->assertStringNotContainsString($sentPassword, $json);

        // 4) NEVER logged: nothing captured by the log listener contains it.
        foreach ($loggedPayloads as $line) {
            $this->assertStringNotContainsString($sentPassword, $line, 'Secret leaked into a log line: '.$line);
        }
    }

    public function test_team_member_secret_is_persisted_encrypted_and_matches_what_was_sent(): void
    {
        config([
            'resayil.reseller_token' => 'fake-reseller-token-for-test',
            'resayil.test_mode' => false,
            'resayil.account_base_url' => 'https://api.resayil.io/v1',
        ]);

        Http::fake([
            'api.resayil.io/v1/devices/*/team*' => Http::response(['id' => 'teamuser_evidence_1'], 200),
        ]);

        $admin = $this->makeCompanyUser();
        $company = $admin->company;

        // Simulate a company whose admin already has a connected device +
        // account token, so provisionTeamMember() actually calls the API
        // instead of returning pending_device.
        $adminRow = ResayilAccount::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => ResayilAccount::ROLE_ADMIN,
            'resayil_customer_id' => 'cust_evidence_1',
            'resayil_device_id' => 'device_evidence_1',
            'resayil_account_token' => 'fake-account-token',
            'status' => ResayilAccount::STATUS_PROVISIONED,
            'provisioned_at' => now(),
        ]);

        $teamMember = User::factory()->create(['role_id' => Role::BRANCH]);
        // Route this second user to the same company via a branch, matching
        // getCompanyId()'s Role::BRANCH -> $user->branch->company path.
        \App\Models\Branch::factory()->create(['company_id' => $company->id, 'user_id' => $teamMember->id]);
        $teamMember = $teamMember->fresh();

        $account = app(ResayilProvisioningService::class)->ensureUserProvisioned($teamMember);

        $this->assertSame(ResayilAccount::STATUS_PROVISIONED, $account->status);

        $sentPassword = null;
        Http::assertSent(function ($request) use (&$sentPassword) {
            $sentPassword = $request['password'] ?? null;

            return true;
        });

        $this->assertNotEmpty($sentPassword);

        $account->refresh();
        $this->assertSame($sentPassword, $account->resayil_secret);

        $raw = DB::connection('mysql_testing')
            ->table('resayil_accounts')
            ->where('id', $account->id)
            ->value('resayil_secret');

        $this->assertNotSame($sentPassword, $raw);
        $this->assertSame($sentPassword, Crypt::decryptString($raw));
    }

    public function test_a_failed_provisioning_call_never_logs_the_secret_even_if_the_api_echoes_password_fields(): void
    {
        config([
            'resayil.reseller_token' => 'fake-reseller-token-for-test',
            'resayil.test_mode' => false,
        ]);

        // Adversarial fixture: pretend Resayil's error response echoes the
        // request body back, including password-shaped fields nested at
        // different depths — proving redactSecret() is not a single
        // top-level check.
        Http::fake([
            'api.resayil.io/v1/resellers/customers*' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->method() === 'GET') {
                    // Lookup-then-create (Fix 2): "not found", so the code
                    // proceeds to the POST this test is actually about.
                    return Http::response([], 200);
                }

                return Http::response([
                    'message' => 'Validation error',
                    'password' => 'ECHOED-TOP-LEVEL-SECRET',
                    'errors' => [
                        ['field' => 'password', 'secret' => 'ECHOED-NESTED-SECRET'],
                    ],
                ], 422);
            },
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                $encoded = json_encode($context);

                return $message === 'resayil.provisioning.admin_failed'
                    && ! str_contains($encoded, 'ECHOED-TOP-LEVEL-SECRET')
                    && ! str_contains($encoded, 'ECHOED-NESTED-SECRET')
                    && str_contains($encoded, '[redacted]');
            });

        $user = $this->makeCompanyUser();

        $account = app(ResayilProvisioningService::class)->ensureUserProvisioned($user);

        $this->assertSame(ResayilAccount::STATUS_ERROR, $account->status);

        // The stored diagnostic `meta` blob must also be scrubbed — it is
        // persisted to the DB and could otherwise leak the secret at rest.
        $metaRaw = DB::connection('mysql_testing')
            ->table('resayil_accounts')
            ->where('id', $account->id)
            ->value('meta');

        $this->assertStringNotContainsString('ECHOED-TOP-LEVEL-SECRET', (string) $metaRaw);
        $this->assertStringNotContainsString('ECHOED-NESTED-SECRET', (string) $metaRaw);
    }
}
