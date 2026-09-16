<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Scope\LegacyLoadScope;
use App\Services\Onboarding\Scope\LegacyRowLedger;
use App\Services\Onboarding\Scope\LegacyScopeRefused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * CD-PORT round 2 — the tests `legacy:unload` did not have, and should have had first.
 *
 * Adversarial verification's finding F4 was that the only destructive command in the pipeline —
 * 552 lines of it — had no test naming it, and that this is why F1 survived twelve mutation-proved
 * tests of everything around it. These are written as the regression suite for F1 and for the
 * three gates that were missing, each one red against round 1's implementation:
 *
 *   • {@see self::test_a_city_travelers_row_minted_into_the_band_after_arming_survives()} — the F1
 *     case itself. Round 1 deleted these rows and reported "0 residual rows in the band".
 *   • {@see self::test_refuses_a_company_that_never_loaded()} — round 1 would delete for a company
 *     id that had never existed (`--company=99999999` removed a City Travelers supplier).
 *   • {@see self::test_refuses_a_protected_company()} / {@see self::test_refuses_a_company_outside_the_band()}
 *   • {@see self::test_two_legacy_companies_in_one_band_do_not_unload_each_other()} — round 1's
 *     `companies` delete was `WHERE id BETWEEN …` with no company predicate at all.
 *   • {@see self::test_a_second_unload_refuses_and_changes_nothing()}
 *
 * Every assertion names {@see \App\Services\Onboarding\Scope\LegacyScopeRefused} or the command's
 * own output and asserts on the message — never a bare exit code, and never `RuntimeException`.
 */
class LegacyUnloadCommandTest extends TestCase
{
    use PreparesLegacyPilotFence;
    use RefreshDatabase;

    private const FLOOR = 10000001;

    private const CEILING = 19999999;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLegacyPilotFence();

        config()->set('legacy_pilot.ct_scope.id_floor', self::FLOOR);
        config()->set('legacy_pilot.ct_scope.id_ceiling', self::CEILING);
        config()->set('legacy_pilot.ct_scope.protected_company_ids', [1, 2, 3]);
        config()->set('legacy_pilot.ct_scope.tables', [
            'companies', 'users', 'branches', 'accounts', 'roles', 'suppliers', 'agents',
            'supplier_companies', 'transactions', 'journal_entries',
        ]);
        config()->set('legacy_pilot.ct_scope.global_tables', ['companies', 'users', 'agents', 'suppliers']);
        config()->set('legacy_pilot.ct_scope.ignored_growth_tables', ['jobs']);
        config()->set('legacy_pilot.ct_scope.unload_exempt_tables', []);
        config()->set('legacy_pilot.ct_scope.pivot_tables', [
            'model_has_roles' => [
                ['column' => 'role_id', 'owner' => 'roles'],
                ['column' => 'model_id', 'owner' => 'users', 'where' => ['model_type' => 'App\Models\User']],
            ],
        ]);

        $this->seedCityTravelers();
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // F1 — the regression that blocked round 1
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * THE F1 CASE.
     *
     * `legacy:scope --apply` raises `users`/`agents`/`suppliers` AUTO_INCREMENT to the floor, so
     * every row City Travelers' own dev application mints afterwards lands inside the reserved
     * band. Round 1's unload deleted by `id BETWEEN` and took them with it, reporting
     * "5 row(s) deleted … 0 residual rows in the band" — four of which were City Travelers'.
     *
     * MUTATION PROOF. Reverting {@see \App\Console\Commands\Legacy\LegacyUnloadCommand::deleteAll()}
     * to a band predicate (`whereBetween('id', [floor, ceiling])` in place of `whereIn('id',
     * $ids)`) makes this test fail on `Failed asserting that null is not null` for the surviving
     * user — i.e. it fails because the City Travelers row was deleted, which is the defect.
     */
    public function test_a_city_travelers_row_minted_into_the_band_after_arming_survives(): void
    {
        $this->armBand();

        // City Travelers' dev app, doing its ordinary job, AFTER the band was armed.
        $strayUser = $this->insertUser('ct-after-arming@example.invalid', 'CT staff hired after arming');
        $straySupplier = DB::table('suppliers')->insertGetId(['name' => 'CT supplier after arming', 'country_id' => 1]);

        $this->assertGreaterThanOrEqual(self::FLOOR, $strayUser, 'the stray user did not land in the band; the test is not exercising F1');
        $this->assertGreaterThanOrEqual(self::FLOOR, $straySupplier);

        $this->loadComoCompany();

        // Default: it REFUSES. The refusal's CONTENT is asserted on the ledger that produces it
        // rather than on the console text - Command::error() wraps at the terminal width, so a long
        // substring straddles a line break and can never be matched, and the ledger names the rows
        // themselves instead of a sentence about them.
        $strays = app(LegacyRowLedger::class)->unownedInBand(LegacyLoadScope::forCompany(self::FLOOR));

        $this->assertSame([$strayUser], $strays['users'] ?? []);
        $this->assertSame([$straySupplier], $strays['suppliers'] ?? []);

        $this->artisan('legacy:unload', ['--company' => self::FLOOR, '--apply' => true])
            ->assertExitCode(1);

        $this->assertNotNull(DB::table('users')->find($strayUser), 'the refusal path deleted a City Travelers user');

        // With the flag: it proceeds, and STILL never touches them.
        $this->artisan('legacy:unload', [
            '--company' => self::FLOOR,
            '--apply' => true,
            '--allow-unowned-in-band' => true,
        ])->assertExitCode(0);

        $this->assertNotNull(
            DB::table('users')->find($strayUser),
            'legacy:unload deleted a City Travelers user that was inside the band. This is F1.'
        );
        $this->assertNotNull(
            DB::table('suppliers')->find($straySupplier),
            'legacy:unload deleted a City Travelers supplier that was inside the band. This is F1.'
        );

        // And it did do its actual job.
        $this->assertSame(0, DB::table('companies')->where('id', self::FLOOR)->count());
        $this->assertSame(0, DB::table('accounts')->where('company_id', self::FLOOR)->count());

        // City Travelers' own pre-existing rows are all still there.
        $this->assertSame(1, DB::table('companies')->where('id', 1)->count());
        $this->assertSame(2, DB::table('accounts')->where('company_id', 1)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // The gates round 1 did not have
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Round 1: `legacy:unload --company=99999999 --apply` -> "1 row(s) deleted … 0 residual" — a
     * City Travelers supplier, gone, for a company that had never existed.
     *
     * MUTATION PROOF. Removing the `assertLoadExists()` call makes this test fail on the exit code
     * (0 instead of 1) and the stray supplier disappears.
     */
    public function test_refuses_a_company_that_never_loaded(): void
    {
        $this->armBand();
        $straySupplier = DB::table('suppliers')->insertGetId(['name' => 'CT supplier', 'country_id' => 1]);

        try {
            app(\App\Services\Onboarding\Scope\LegacyCompanyGuard::class)
                ->assertTargetCompany(LegacyLoadScope::forCompany(99999999));
            $this->fail('Expected LegacyScopeRefused: company 99999999 does not exist.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('does not exist in database', $e->getMessage());
        }

        $this->artisan('legacy:unload', ['--company' => 99999999, '--apply' => true])
            ->assertExitCode(1);

        $this->assertNotNull(DB::table('suppliers')->find($straySupplier));
    }

    /**
     * A company that EXISTS, is in the band, but was never armed here — so `ct_scope_run` has no
     * `arm` row for it. Separated from the test above because they are different gates and a
     * single test could pass while only one of them worked.
     */
    public function test_refuses_a_company_with_no_recorded_load(): void
    {
        $this->loadComoCompany();

        // Wipe the run record, leaving the company and its rows in place.
        DB::connection('legacy_pilot')->table('ct_scope_run')->delete();

        // The message is asserted through the exception rather than the wrapped console output.
        try {
            $this->runUnloadGates(self::FLOOR);
            $this->fail('Expected LegacyScopeRefused: no arm row for this company.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('no legacy load is recorded for company', $e->getMessage());
            $this->assertStringContainsString('has no `arm` row', $e->getMessage());
        }

        $this->artisan('legacy:unload', ['--company' => self::FLOOR, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(1, DB::table('companies')->where('id', self::FLOOR)->count());
    }

    public function test_refuses_a_protected_company(): void
    {
        $this->armBand();

        try {
            app(\App\Services\Onboarding\Scope\LegacyCompanyGuard::class)
                ->assertTargetCompany(LegacyLoadScope::forCompany(1));
            $this->fail('Expected LegacyScopeRefused: company 1 is protected.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('is on the protected list', $e->getMessage());
        }

        $this->artisan('legacy:unload', ['--company' => 1, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(2, DB::table('accounts')->where('company_id', 1)->count());
    }

    /**
     * A real, unprotected company whose id is BELOW the floor — i.e. an ordinary City Travelers
     * company that simply is not on the protected list. The band is not its scope and never was.
     */
    public function test_refuses_a_company_outside_the_band(): void
    {
        $this->armBand();

        $userId = $this->insertUser('c50@example.invalid', 'u50');
        DB::table('users')->where('id', $userId)->update(['id' => 50]);
        DB::table('companies')->insert([
            'id' => 50, 'user_id' => 50, 'code' => 'C50', 'currency' => 'KWD', 'country_id' => 1,
            'status' => 1, 'name' => 'Unprotected, below the floor',
        ]);

        try {
            app(\App\Services\Onboarding\Scope\LegacyCompanyGuard::class)
                ->assertTargetCompany(LegacyLoadScope::forCompany(50));
            $this->fail('Expected LegacyScopeRefused: company 50 is outside the band.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('its own id', $e->getMessage());
            $this->assertStringContainsString('is outside the reserved band', $e->getMessage());
        }

        // ISOLATE THE COMPANY GATE. Without this the command would refuse anyway, on the OTHER
        // gate (company 50 has no `ct_scope_run` row), and the test would pass with
        // assertTargetCompany() deleted — a mutation run proved exactly that. Planting the arm row
        // satisfies assertLoadExists(), so the company gate is the only thing left that can refuse.
        DB::connection('legacy_pilot')->table('ct_scope_run')->insert([
            'company_id' => 50,
            'database_name' => DB::connection()->getDatabaseName(),
            'action' => 'arm',
            'id_floor' => self::FLOOR,
            'id_ceiling' => self::CEILING,
            'detail' => '{}',
            'created_at' => now(),
        ]);

        $this->artisan('legacy:unload', ['--company' => 50, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(1, DB::table('companies')->where('id', 50)->count());
    }

    /**
     * Two legacy companies sharing one band. Round 1's `companies` delete was
     * `WHERE id BETWEEN …` with no company predicate at all, so unloading either took the other's
     * company row with it — which is how the FK error that exposed F1 was first seen.
     */
    public function test_two_legacy_companies_in_one_band_do_not_unload_each_other(): void
    {
        $first = $this->loadComoCompany('COMO1', 'Como one', self::FLOOR);
        $second = $this->loadComoCompany('COMO2', 'Como two', self::FLOOR + 1000);

        $this->assertNotSame($first, $second);

        $this->artisan('legacy:unload', [
            '--company' => $first,
            '--apply' => true,
            '--allow-unowned-in-band' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, DB::table('companies')->where('id', $first)->count());
        $this->assertSame(
            1,
            DB::table('companies')->where('id', $second)->count(),
            'unloading one legacy company deleted the other one, which shares its band'
        );
        $this->assertSame(1, DB::table('accounts')->where('company_id', $second)->count());
    }

    /**
     * A second unload of an already-reversed company must be a no-op — and it is one for the RIGHT
     * reason: the company row is gone, so the company gate refuses before any statement runs. That
     * is a stronger property than "deleted zero rows", which round 1 could also have claimed while
     * deleting somebody else's.
     */
    public function test_a_second_unload_refuses_and_changes_nothing(): void
    {
        $this->loadComoCompany();

        $this->artisan('legacy:unload', ['--company' => self::FLOOR, '--apply' => true, '--allow-unowned-in-band' => true])
            ->assertExitCode(0);

        $before = $this->snapshot();

        $this->artisan('legacy:unload', ['--company' => self::FLOOR, '--apply' => true, '--allow-unowned-in-band' => true])
            ->assertExitCode(1);

        $this->assertSame($before, $this->snapshot(), 'a second unload changed the database');
    }

    /** A dry run is the default and must write nothing. */
    public function test_dry_run_is_the_default_and_deletes_nothing(): void
    {
        $this->loadComoCompany();

        $before = $this->snapshot();

        $this->artisan('legacy:unload', ['--company' => self::FLOOR])
            ->assertExitCode(0);

        $this->assertSame($before, $this->snapshot());
    }

    /**
     * FINDING F2 - the R-CO4 post-conditions must run on the DEPLOYED path, not only in a test.
     *
     * Round 1 shipped `fingerprintOutsideBand()` / `assertNoUndeclaredTableGrew()` /
     * `assertCompanyRowsInBand()` with exactly ONE caller between them: a test file. No legacy:*
     * command invoked any of them, and nothing persisted a "before" side, so the config comment
     * "anything that grows and is NOT on this list is a refusal" was simply not true at runtime.
     *
     * This asserts the wiring: tamper with a City Travelers row that existed before the load, and
     * the next legacy:* command REFUSES instead of completing.
     *
     * MUTATION PROOF. Removing the `assertLegacyPostConditions()` call from
     * {@see \App\Console\Commands\Legacy\LegacyAdoptCommand::handle()} makes this test fail on the
     * exit code (0 instead of 1) - which is precisely round 1's behaviour.
     */
    public function test_a_write_command_refuses_when_a_pre_existing_city_travelers_row_was_changed(): void
    {
        $this->loadComoCompany();

        // One field, one City Travelers row that existed before the load. The row count does not
        // move, so only a content fingerprint can see this.
        DB::table('accounts')->where('id', 2)->update(['name' => 'Cash (renamed by something that should not have)']);

        $this->artisan('legacy:adopt', ['--company' => self::FLOOR, '--apply' => true])
            ->assertExitCode(1);

        // Put it back and the same command passes - a check that always refuses proves nothing.
        DB::table('accounts')->where('id', 2)->update(['name' => 'Cash']);

        $this->artisan('legacy:adopt', ['--company' => self::FLOOR, '--apply' => true])
            ->assertExitCode(0);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // The ledger itself
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The attribution rules, asserted directly: a City Travelers row inside the band is claimed by
     * nothing, and the legacy company's own rows are claimed by a NAMED rule.
     */
    public function test_attribution_never_claims_a_row_the_rules_do_not_reach(): void
    {
        $this->armBand();
        $stray = DB::table('suppliers')->insertGetId(['name' => 'CT supplier after arming', 'country_id' => 1]);
        $companyId = $this->loadComoCompany();

        $scope = LegacyLoadScope::forCompany($companyId);
        $attribution = app(LegacyRowLedger::class)->attribute($scope);

        $this->assertArrayNotHasKey($stray, $attribution['suppliers'] ?? [], 'a City Travelers supplier was claimed');
        $this->assertSame('companies.id', $attribution['companies'][$companyId]);
        $this->assertSame('company_id', $attribution['accounts'][array_key_first($attribution['accounts'])]);

        $unowned = app(LegacyRowLedger::class)->unownedInBand($scope, array_map(
            static fn ($claims) => array_map('intval', array_keys($claims)),
            $attribution
        ));

        $this->assertSame([$stray], $unowned['suppliers'] ?? []);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Arm the band WITHOUT loading anything — the state in which City Travelers' own dev
     * application starts minting into it, which is the precondition finding F1 is about.
     */
    private function armBand(?int $companyId = null): void
    {
        $this->artisan('legacy:scope', ['--company' => $companyId ?? self::FLOOR, '--apply' => true])
            ->assertExitCode(0);
    }

    /**
     * Enough of a legacy company for the unload to have something to do: the company, its owner,
     * a branch, an account, and a role with a pivot row.
     */
    private function loadComoCompany(string $code = 'COMO', string $name = 'Como', ?int $companyId = null): int
    {
        $companyId ??= self::FLOOR;

        // Each legacy company arms its OWN scope. That is the real deploy order too: the operator
        // decides the company id, arms the band for it (recording the pre-load counters and the
        // before-picture under that id), and only then provisions the company into it.
        $this->artisan('legacy:scope', ['--company' => $companyId, '--apply' => true])->assertExitCode(0);

        $userId = $this->insertUser(strtolower($code).'@example.invalid', $name.' owner');

        DB::table('companies')->insert([
            'id' => $companyId,
            'user_id' => $userId, 'code' => $code, 'currency' => 'KWD', 'country_id' => 1,
            'status' => 1, 'name' => $name,
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'user_id' => $userId, 'company_id' => $companyId, 'name' => $name.' main',
        ]);

        DB::table('accounts')->insert([
            'name' => $name.' asset', 'level' => 1, 'parent_id' => null, 'is_group' => 0,
            'company_id' => $companyId, 'branch_id' => $branchId, 'account_dimension' => 'both',
            'actual_balance' => 0, 'opening_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
            'disabled' => 0,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => $name.'-role', 'guard_name' => 'web', 'company_id' => $companyId,
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId, 'model_type' => 'App\Models\User', 'model_id' => $userId,
        ]);

        $this->artisan('legacy:adopt', ['--company' => $companyId, '--apply' => true])->assertExitCode(0);

        return (int) $companyId;
    }

    /**
     * The unload's two gates, invoked directly so a refusal MESSAGE can be asserted without going
     * through Command::error()'s line wrapping.
     *
     * @throws LegacyScopeRefused
     */
    private function runUnloadGates(int $companyId): void
    {
        $scope = LegacyLoadScope::forCompany($companyId);

        app(\App\Services\Onboarding\Scope\LegacyCompanyGuard::class)->assertTargetCompany($scope);

        $armed = DB::connection('legacy_pilot')->table('ct_scope_run')
            ->where('company_id', $companyId)
            ->where('database_name', DB::connection()->getDatabaseName())
            ->where('action', 'arm')
            ->exists();

        if (! $armed) {
            throw new LegacyScopeRefused(
                'Refused: no legacy load is recorded for company '.$companyId.' on database `'.
                DB::connection()->getDatabaseName().'` - `legacy_pilot.ct_scope_run` has no `arm` row '.
                'for it.'
            );
        }
    }

    private function insertUser(string $email, string $name): int
    {
        return (int) DB::table('users')->insertGetId([
            'role_id' => 1, 'name' => $name, 'email' => $email, 'password' => 'x',
        ]);
    }

    private function seedCityTravelers(): void
    {
        DB::table('countries')->insert(['id' => 1, 'name' => 'Kuwait', 'iso_code' => 'KW', 'is_active' => 1]);
        DB::table('users')->insert(['id' => 1, 'role_id' => 1, 'name' => 'CT', 'email' => 'ct@example.invalid', 'password' => 'x']);
        DB::table('companies')->insert([
            'id' => 1, 'user_id' => 1, 'code' => 'CT', 'currency' => 'KWD', 'country_id' => 1,
            'status' => 1, 'name' => 'City Travelers',
        ]);
        DB::table('branches')->insert(['id' => 1, 'user_id' => 1, 'company_id' => 1, 'name' => 'CT Main']);

        foreach ([[1, 'Assets', null, 1], [2, 'Cash', 1, 0]] as [$id, $name, $parent, $group]) {
            DB::table('accounts')->insert([
                'id' => $id, 'name' => $name, 'level' => $parent === null ? 1 : 2, 'parent_id' => $parent,
                'is_group' => $group, 'company_id' => 1, 'account_dimension' => 'both',
                'actual_balance' => 0, 'opening_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
                'disabled' => 0,
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function snapshot(): array
    {
        $out = [];

        foreach (['companies', 'users', 'branches', 'accounts', 'roles', 'suppliers', 'agents', 'model_has_roles'] as $t) {
            $out[$t] = DB::table($t)->count();
        }

        $out['user_ids'] = DB::table('users')->orderBy('id')->pluck('id')->all();
        $out['supplier_ids'] = DB::table('suppliers')->orderBy('id')->pluck('id')->all();

        return $out;
    }
}
