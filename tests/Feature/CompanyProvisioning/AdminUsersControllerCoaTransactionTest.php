<?php

namespace Tests\Feature\CompanyProvisioning;

use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * Fix 3 (2026-08-25 pre-pilot defect list): AdminUsersController::store()
 * (Path A — POST /users/companies, route `companies.store`) used to call
 * CoaSeeder::run() in its own try/catch AFTER CompanyController::store()
 * had already committed the company/user rows in its own, separate
 * DB::beginTransaction()/commit(). A seeding failure left a company that
 * exists with no chart of accounts — and since the ledger auto-posts for
 * every company, that company's books had nowhere to write.
 *
 * The fix wraps CompanyController::store()'s call (which now nests as a
 * savepoint rather than a real commit) and CoaSeeder::run() inside ONE
 * outer transaction in AdminUsersController::store(), matching the pattern
 * CompanyProvisioner::provision() already uses for Path B (company creation
 * and seedChartOfAccounts() inside a single DB::transaction()).
 *
 * This test forces a genuine CoaSeeder failure (Mockery alias-mocks the
 * static Database\Seeders\CoaSeeder::run(), which is not DI-injectable) and
 * proves the whole request rolls back to nothing: no orphan Company row, no
 * orphan owner User row.
 *
 * Dispatches a real POST request through the full HTTP kernel
 * (TestCase::post() -> Illuminate\Contracts\Http\Kernel::handle()) as a
 * real authenticated admin user (actingAs() sets the user on the `web`
 * guard) — the harness pattern this project uses for HTTP-behavior fixes.
 * Runs in a separate process so Mockery's static alias mock can intercept
 * Database\Seeders\CoaSeeder before it is autoloaded for real.
 */
class AdminUsersControllerCoaTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();

        // CompanyController::store() calls $user->assignRole('company') —
        // Spatie resolves by name+guard only (a pre-existing, documented
        // quirk elsewhere in this codebase, not something Fix 3 touches),
        // so a single global 'company' role for the 'web' guard is enough
        // to make that call succeed in this isolated DB.
        \App\Models\Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_simulated_coa_seeding_failure_leaves_no_orphan_company_or_user_row(): void
    {
        Mockery::mock('alias:Database\Seeders\CoaSeeder')
            ->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Simulated CoaSeeder failure for Fix 3 verification'));

        $admin = $this->makeAdmin();
        $country = Country::factory()->create();

        $unique = uniqid();
        $payload = [
            'name' => 'Orphan Guard Co',
            'email' => "orphan-guard-{$unique}@example.test",
            'password' => 'password12345',
            'phone' => null,
            'code' => "ORPHAN-{$unique}",
            'country_id' => $country->id,
            'address' => null,
            'status' => 1,
        ];

        $response = $this->actingAs($admin)->post(route('companies.store'), $payload);

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('error', 'Error creating COA accounts.');

        // The heart of Fix 3: NOTHING from this request survives — the
        // company row, its owner user row, and any chart-of-accounts rows
        // are all gone, because CoaSeeder's failure rolled back the SAME
        // transaction that created them (previously the company/user rows
        // would have survived this failure as an orphan, book-less
        // company).
        $this->assertDatabaseMissing('companies', ['code' => $payload['code']]);
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_normal_provisioning_still_creates_the_company_with_its_chart_of_accounts(): void
    {
        // Companion/control test: proves the transaction wrapping did not
        // break the happy path — a real (unmocked) CoaSeeder run still
        // seeds accounts for the new company inside the same request.
        $admin = $this->makeAdmin();
        $country = Country::factory()->create();

        $unique = uniqid();
        $payload = [
            'name' => 'Healthy Provisioning Co',
            'email' => "healthy-{$unique}@example.test",
            'password' => 'password12345',
            'phone' => null,
            'code' => "HEALTHY-{$unique}",
            'country_id' => $country->id,
            'address' => null,
            'status' => 1,
        ];

        $response = $this->actingAs($admin)->post(route('companies.store'), $payload);

        $this->assertDatabaseHas('companies', ['code' => $payload['code']]);
        $this->assertDatabaseHas('users', ['email' => $payload['email']]);

        $company = \App\Models\Company::where('code', $payload['code'])->firstOrFail();
        $this->assertGreaterThan(0, \App\Models\Account::where('company_id', $company->id)->count(), 'CoaSeeder must still have run for a genuinely new company.');
    }
}
