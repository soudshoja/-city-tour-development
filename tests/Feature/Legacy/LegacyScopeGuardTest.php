<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Scope\LegacyCompanyGuard;
use App\Services\Onboarding\Scope\LegacyIdBandGuard;
use App\Services\Onboarding\Scope\LegacyLoadScope;
use App\Services\Onboarding\Scope\LegacyScopeRefused;
use App\Services\Onboarding\Scope\LegacySerialSchemaPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * CD-PORT — the guards that make the ported pipeline safe to run inside a SHARED database.
 *
 * Every assertion here catches {@see LegacyScopeRefused} specifically and asserts on the MESSAGE.
 * That is deliberate and is the lesson from the `test-oracle-exception-type` rule: the ported
 * pipeline throws bare `RuntimeException` for a dozen unrelated reasons (missing staging table,
 * unresolvable purpose, malformed CSV header), and PHPUnit's own `AssertionFailedError` is a
 * `RuntimeException` too — so a test that caught `RuntimeException` and asserted nothing about the
 * message would pass when the guard was deleted outright, as long as anything at all went wrong.
 *
 * Each test's docblock records the MUTATION that was run against it and the failure it produced,
 * so a reviewer can see the oracle was proved rather than assumed.
 */
class LegacyScopeGuardTest extends TestCase
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
        config()->set('legacy_pilot.ct_scope.tables', ['companies', 'branches', 'accounts', 'transactions', 'journal_entries']);
        config()->set('legacy_pilot.ct_scope.global_tables', ['companies']);
        config()->set('legacy_pilot.ct_scope.pivot_tables', []);
        config()->set('legacy_pilot.ct_scope.unload_exempt_tables', []);

        // The sandbox gate runs before every other gate on every legacy:* command.
        config()->set('legacy_pilot.ct_scope.sandbox_database', \Illuminate\Support\Facades\DB::connection()->getDatabaseName());
        config()->set('legacy_pilot.ct_scope.never_sandbox_databases', ['citycomm_city-tour', 'citycomm_city-tour-test']);
        app(\App\Services\Onboarding\Scope\LegacySandboxGuard::class)->mark('phpunit fence');
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // The company guard
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * MUTATION PROOF. Deleting the `in_array($scope->companyId, $protected)` block from
     * {@see LegacyCompanyGuard::assertTargetCompany()} makes this test fail with
     * "Failed asserting that exception of type LegacyScopeRefused is thrown", i.e. it fails
     * because the refusal did not happen — not because something else went wrong.
     */
    public function test_company_guard_refuses_a_protected_company_before_any_write(): void
    {
        $this->seedCityTravelersBaseline();

        $scope = LegacyLoadScope::forCompany(1);

        $before = $this->rowSnapshot();

        try {
            app(LegacyCompanyGuard::class)->assertTargetCompany($scope);
            $this->fail('Expected LegacyScopeRefused: company 1 is on the protected list.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('Refused before the first write', $e->getMessage());
            $this->assertStringContainsString('company 1 is on the protected list', $e->getMessage());
            $this->assertStringContainsString('R-CO4', $e->getMessage());
        }

        // "before the first write" is the load-bearing half of the claim, so it is asserted
        // rather than trusted: nothing moved.
        $this->assertSame($before, $this->rowSnapshot());
    }

    /**
     * MUTATION PROOF. Changing the guard's `$company === null` branch to `return;` makes this
     * test fail on the missing exception.
     */
    public function test_company_guard_refuses_a_company_that_does_not_exist(): void
    {
        $scope = LegacyLoadScope::forCompany(self::FLOOR);

        $this->expectException(LegacyScopeRefused::class);
        $this->expectExceptionMessageMatches('/does not exist in database/');

        app(LegacyCompanyGuard::class)->assertTargetCompany($scope);
    }

    /**
     * The case the id band exists for: a company that is real, is not protected, but whose own id
     * sits BELOW the floor — so the reversal manifest's `DELETE … WHERE id BETWEEN` could never
     * reach it.
     *
     * MUTATION PROOF. Removing the `! $scope->contains((int) $company->id)` branch makes this
     * test fail on the missing exception; widening the band so the id falls inside it makes the
     * guard correctly allow the load, which is why the band values are set in setUp() rather than
     * read from whatever config the app happens to ship.
     */
    public function test_company_guard_refuses_a_company_whose_own_id_is_outside_the_band(): void
    {
        DB::table('users')->insert(['id' => 90, 'role_id' => 1, 'name' => 'x', 'email' => 'x90@example.invalid', 'password' => 'x']);
        DB::table('companies')->insert([
            'id' => 91, 'user_id' => 90, 'code' => 'X', 'currency' => 'KWD', 'country_id' => 1,
            'status' => 1, 'name' => 'Not a legacy company',
        ]);

        $scope = LegacyLoadScope::forCompany(91);

        $this->expectException(LegacyScopeRefused::class);
        $this->expectExceptionMessageMatches('/its own id.*is outside the reserved band/s');

        app(LegacyCompanyGuard::class)->assertTargetCompany($scope);
    }

    /**
     * The fingerprint is the R-CO4 post-condition. This proves it DETECTS an update — not merely
     * an insert or a delete, which a row count would also catch.
     *
     * MUTATION PROOF. Replacing the fingerprint expression with `COUNT(*)` alone makes this test
     * fail: the update below leaves the count identical, so the "no diff" branch is taken and the
     * expected exception never arrives.
     */
    public function test_outside_band_fingerprint_detects_an_update_to_another_companys_row(): void
    {
        $this->seedCityTravelersBaseline();

        $scope = LegacyLoadScope::forCompany(self::FLOOR);
        $guard = app(LegacyCompanyGuard::class);

        $before = $guard->fingerprintOutsideBand($scope);

        // One field, one row, same row count.
        DB::table('accounts')->where('id', 2)->update(['name' => 'Cash (renamed by something that should not have)']);

        $after = $guard->fingerprintOutsideBand($scope);

        try {
            $guard->assertOutsideBandUnchanged($before, $after);
            $this->fail('Expected LegacyScopeRefused: an outside-band row was updated.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('changed OUTSIDE the reserved id band', $e->getMessage());
            $this->assertStringContainsString('accounts', $e->getMessage());
            $this->assertStringContainsString('R-CO4', $e->getMessage());
        }

        // And the same fingerprint says "identical" when nothing changed — otherwise the test
        // above would pass for a guard that simply always refuses.
        DB::table('accounts')->where('id', 2)->update(['name' => 'Cash']);
        $guard->assertOutsideBandUnchanged($before, $guard->fingerprintOutsideBand($scope));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // The id-band guard
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * MUTATION PROOF. Deleting the `$offenders !== []` throw at the end of
     * {@see LegacyIdBandGuard::assertArmed()} makes this test fail on the missing exception.
     * Narrowing the comparison to `$counter < $scope->idFloor` alone still passes this test but
     * fails {@see self::test_id_band_guard_refuses_a_counter_above_the_ceiling()}, which is why
     * both halves of the band are tested separately.
     */
    public function test_id_band_guard_refuses_before_the_first_write_when_a_table_is_not_armed(): void
    {
        $this->seedCityTravelersBaseline();

        $scope = LegacyLoadScope::forCompany(self::FLOOR);
        $band = app(LegacyIdBandGuard::class);

        $before = $this->rowSnapshot();

        try {
            $band->assertArmed($scope);
            $this->fail('Expected LegacyScopeRefused: no counter has been raised to the floor.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('Refused before the first write', $e->getMessage());
            $this->assertStringContainsString('outside the reserved band', $e->getMessage());
            $this->assertStringContainsString('legacy:scope', $e->getMessage());
        }

        $this->assertSame($before, $this->rowSnapshot());

        // Arm it, and the same call must now pass — the guard has to be satisfiable, or it is
        // just a permanent refusal that proves nothing.
        $band->arm($scope);
        $band->assertArmed($scope);

        foreach ($scope->tables as $table) {
            $this->assertGreaterThanOrEqual(
                self::FLOOR,
                $band->autoIncrement($table),
                "AUTO_INCREMENT on {$table} was not raised to the floor"
            );
        }
    }

    /**
     * The other end of the band. A counter ABOVE the ceiling means the reserved range is
     * exhausted or somebody else is minting in it; either way the next id would land outside.
     *
     * MUTATION PROOF. Removing the `|| $counter > $scope->idCeiling` limb makes this test fail on
     * the missing exception while the floor test above keeps passing.
     */
    public function test_id_band_guard_refuses_a_counter_above_the_ceiling(): void
    {
        $scope = LegacyLoadScope::forCompany(self::FLOOR);
        $band = app(LegacyIdBandGuard::class);

        $band->arm($scope);
        DB::statement('ALTER TABLE `accounts` AUTO_INCREMENT = '.(self::CEILING + 1));

        try {
            $band->assertArmed($scope);
            $this->fail('Expected LegacyScopeRefused: accounts would mint above the ceiling.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('accounts='.(self::CEILING + 1), $e->getMessage());
            $this->assertStringContainsString('outside the reserved band', $e->getMessage());
        }
    }

    /**
     * R-CO5's PREMISE, re-measured on the engine this build actually runs against rather than
     * quoted from CD0's report.
     *
     * CD0's central finding is that `ALTER TABLE t AUTO_INCREMENT = n` with `n <= MAX(id)` returns
     * **exit 0, empty SHOW WARNINGS, and changes nothing** — so a reversal that trusted the exit
     * code would report every counter restored and be wrong about every one. The whole of
     * {@see LegacyIdBandGuard} and {@see \App\Console\Commands\Legacy\LegacyUnloadCommand}'s
     * restore step is built on that asymmetry, so this test asserts the asymmetry itself.
     *
     * WHAT THIS TEST DELIBERATELY DOES NOT CLAIM. `arm()` only ever RAISES a counter, and raising
     * it to a floor above `MAX(id)` is the case InnoDB honours. Its `$after < $idFloor` re-read
     * branch is therefore belt-and-braces and is not reachable by any sequence a caller can
     * construct — saying so here is more honest than building an elaborate fixture that fakes
     * reachability and proves nothing. The branch that DOES fire in practice is the LOWERING one,
     * in `LegacyUnloadCommand::restoreCounters()`, and it fired on the real fence run: 22 of 23
     * counters returned to their recorded pre-load value and `accounting_audit_log` did not
     * (target 1, re-read 10,000,002), because its append-only trigger had refused the delete that
     * would have let it. That run reported the one failure instead of claiming 23 successes, which
     * is the behaviour this premise exists to produce.
     *
     * MUTATION PROOF. Asserting `assertNotSame()` here (i.e. claiming the counter DOES move) fails
     * with "Failed asserting that 15000002 is not identical to 15000002" — so the assertion is
     * reading the real counter and not a constant. If a future MariaDB starts honouring the
     * lowering ALTER, this test fails FIRST, loudly, instead of the reversal quietly changing
     * meaning underneath the report.
     */
    public function test_innodb_reports_success_for_an_auto_increment_lowering_it_does_not_perform(): void
    {
        DB::table('users')->insert(['id' => 90, 'role_id' => 1, 'name' => 'x', 'email' => 'x90@example.invalid', 'password' => 'x']);
        DB::table('companies')->insert([
            'id' => self::FLOOR + 5_000_000, 'user_id' => 90, 'code' => 'HI', 'currency' => 'KWD',
            'country_id' => 1, 'status' => 1, 'name' => 'high id',
        ]);

        $band = app(LegacyIdBandGuard::class);

        $counterBefore = $band->autoIncrement('companies');
        $this->assertGreaterThan(self::FLOOR, $counterBefore);

        // Returns success. Emits no warning.
        DB::statement('ALTER TABLE `companies` AUTO_INCREMENT = '.self::FLOOR);
        $this->assertSame([], DB::select('SHOW WARNINGS'));

        $this->assertSame(
            $counterBefore,
            $band->autoIncrement('companies'),
            'This engine DID lower AUTO_INCREMENT below MAX(id)+1. CD0\'s finding no longer holds '.
            'on this build, and the reversal design must be re-derived rather than patched.'
        );

        // And it comes back the moment the row that raised it goes — the asymmetry in full, and
        // the reason `legacy:unload` restores counters only AFTER deleting.
        DB::table('companies')->where('id', self::FLOOR + 5_000_000)->delete();
        DB::statement('ALTER TABLE `companies` AUTO_INCREMENT = '.self::FLOOR);
        $this->assertSame(self::FLOOR, $band->autoIncrement('companies'));
    }

    /**
     * The check that catches a table nobody declared.
     *
     * MUTATION PROOF. Changing `in_array($table, $scope->declaredTables(), true)` to `true` makes
     * every growth "declared" and this test fails on the missing exception.
     */
    public function test_undeclared_table_growth_is_refused(): void
    {
        $scope = LegacyLoadScope::forCompany(self::FLOOR);
        $band = app(LegacyIdBandGuard::class);

        $before = $band->rowCensus();

        DB::table('users')->insert(['id' => 90, 'role_id' => 1, 'name' => 'x', 'email' => 'x90@example.invalid', 'password' => 'x']);

        try {
            $band->assertNoUndeclaredTableGrew($scope, $before, $band->rowCensus());
            $this->fail('Expected LegacyScopeRefused: `users` is not in this test\'s declared write set.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('outside the declared write set changed row count', $e->getMessage());
            $this->assertStringContainsString('users (0 -> 1, +1)', $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Document numbering (adaptation A-2)
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The failure this planner exists to prevent, asserted against the LIVE column width rather
     * than against the literal 20 — an oracle that restated the constant would keep passing if the
     * column were widened or narrowed.
     *
     * MUTATION PROOF. Removing the `strlen($sample) > $limit` refusal makes this test fail on the
     * missing exception; and running the real replay without this planner produced
     * `SQLSTATE[22001] … Data too long for column 'reference_number'` on the very first document,
     * which is the failure the refusal replaces.
     */
    public function test_serial_planner_refuses_a_document_number_that_would_not_fit(): void
    {
        $this->seedCityTravelersBaseline();
        $this->seedLegacyCompanyWithBranch('THIS-TAG-IS-FAR-TOO-LONG-TO-FIT');

        $planner = app(LegacySerialSchemaPlanner::class);
        $scope = LegacyLoadScope::forCompany(self::FLOOR);

        config()->set('legacy_pilot.ct_scope.branch_tags', [self::FLOOR => 'THIS-TAG-IS-FAR-TOO-LONG-TO-FIT']);

        try {
            $planner->plan($scope, [2025]);
            $this->fail('Expected LegacyScopeRefused: the planned number is longer than the column.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('characters, and transactions.reference_number holds', $e->getMessage());
            $this->assertStringContainsString((string) $planner->referenceNumberLimit(), $e->getMessage());
        }
    }

    /**
     * MUTATION PROOF. Deleting the duplicate-tag loop from `branchTags()` makes this test fail on
     * the missing exception — and the two branches would then both number documents `OJV-CO-…`
     * and collide on `UNIQUE (company_id, doc_type, reference_number)` partway through a replay.
     */
    public function test_serial_planner_refuses_two_branches_that_would_share_a_tag(): void
    {
        $this->seedCityTravelersBaseline();
        $this->seedLegacyCompanyWithBranch('CO');

        DB::table('branches')->insert([
            'id' => self::FLOOR + 1, 'user_id' => self::FLOOR, 'company_id' => self::FLOOR, 'name' => 'second',
        ]);

        config()->set('legacy_pilot.ct_scope.branch_tags', [
            self::FLOOR => 'CO',
            self::FLOOR + 1 => 'CO',
        ]);

        $this->expectException(LegacyScopeRefused::class);
        $this->expectExceptionMessageMatches('/would both number documents under the tag/');

        app(LegacySerialSchemaPlanner::class)->plan(LegacyLoadScope::forCompany(self::FLOOR), [2025]);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // Scope construction
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * There is deliberately no default company id. `legacy_pilot.default_company_id` is 1, and on
     * the City Travelers development database company 1 is City Travelers.
     */
    public function test_scope_refuses_a_non_positive_company_id(): void
    {
        $this->expectException(LegacyScopeRefused::class);
        $this->expectExceptionMessageMatches('/needs an explicit positive company id/');

        LegacyLoadScope::forCompany(0);
    }

    /**
     * MUTATION PROOF. Removing the empty-list refusal from `forCompany()` makes this test fail on
     * the missing exception — and every undeclared-growth check downstream would then pass
     * vacuously, which is worse than having no check at all.
     */
    public function test_scope_refuses_an_empty_declared_write_set(): void
    {
        config()->set('legacy_pilot.ct_scope.tables', []);
        config()->set('legacy_pilot.ct_scope.global_tables', []);

        $this->expectException(LegacyScopeRefused::class);
        $this->expectExceptionMessageMatches('/ct_scope\.tables is empty/');

        LegacyLoadScope::forCompany(self::FLOOR);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // ROUND 2 — the gates adversarial verification found missing
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * FINDING F3. `legacy:scope` had no company gate whatsoever:
     * `legacy:scope --company=2 --apply` reported "Band armed for company 2: 21 counter(s) raised"
     * and armed the reversal band under a PROTECTED City Travelers id.
     *
     * MUTATION PROOF. Removing the protected-list branch from
     * {@see \App\Console\Commands\Legacy\LegacyScopeCommand::assertCompanyGate()} makes this test
     * fail on the exit code (0 instead of 1) and the counters move.
     */
    public function test_scope_command_refuses_a_protected_company(): void
    {
        $this->seedCityTravelersBaseline();

        $band = app(LegacyIdBandGuard::class);
        $before = $band->autoIncrement('accounts');

        $this->artisan('legacy:scope', ['--company' => 2, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(
            $before,
            $band->autoIncrement('accounts'),
            'legacy:scope armed the band for a protected company id'
        );
    }

    /**
     * The same gate must not fire for a legitimate target, or it proves nothing. A company id that
     * does not exist YET is explicitly allowed: the deploy order is scope-then-provision, precisely
     * so `companies`' own pre-load counter is recorded before the company row is minted.
     */
    public function test_scope_command_arms_for_a_company_that_does_not_exist_yet(): void
    {
        $this->seedCityTravelersBaseline();

        $this->artisan('legacy:scope', ['--company' => self::FLOOR, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(self::FLOOR, app(LegacyIdBandGuard::class)->autoIncrement('accounts'));
    }

    /**
     * FINDING F5, second half. {@see \App\Services\Accounting\SequenceService::next()}
     * `firstOrCreate`s a `serial_schemas` row with `DEFAULT_MASK` for any combination that has
     * none — and on this company that mask renders the EIGHT-DIGIT branch id, overflowing
     * `transactions.reference_number` part-way through a replay instead of before it.
     *
     * MUTATION PROOF. Deleting the `$missing !== []` refusal from `assertSeeded()` makes this test
     * fail on the missing exception — and the real replay then dies on `SQLSTATE[22001]` at
     * whichever document first needs the unseeded combination.
     */
    public function test_serial_planner_refuses_a_replay_whose_numbering_is_not_seeded(): void
    {
        $this->seedCityTravelersBaseline();
        $this->seedLegacyCompanyWithBranch('CO');

        $planner = app(LegacySerialSchemaPlanner::class);
        $scope = LegacyLoadScope::forCompany(self::FLOOR);

        try {
            $planner->assertSeeded($scope, [2025]);
            $this->fail('Expected LegacyScopeRefused: nothing has been seeded yet.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('have no pre-seeded serial_schemas row', $e->getMessage());
            $this->assertStringContainsString('DEFAULT_MASK', $e->getMessage());
        }

        // Seed it, and the same assertion must now PASS — a guard that can never be satisfied
        // proves nothing.
        $planner->apply($planner->plan($scope, [2025])['rows']);
        $planner->assertSeeded($scope, [2025]);

        // And a HAND-EDITED mask that no longer fits is caught too, which a "does a row exist"
        // check alone would miss.
        DB::table('serial_schemas')
            ->where('company_id', self::FLOOR)
            ->where('doc_type', 'INV')
            ->update(['mask' => '{TYPE}-A-VERY-LONG-BRANCH-TAG-{YYYY}-{SEQ:5}']);

        try {
            $planner->assertSeeded($scope, [2025]);
            $this->fail('Expected LegacyScopeRefused: an existing mask renders too long.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('render longer than transactions.reference_number holds', $e->getMessage());
            $this->assertStringContainsString((string) $planner->referenceNumberLimit(), $e->getMessage());
        }
    }

    /**
     * FINDING F5, first half. `LegacySerialsCommand::resolveYears()` read
     * `replay.window_from` / `replay.window_to` — NEITHER KEY EXISTS (they are `window_start` /
     * `window_end`), so both `config()` calls fell through to the PHP defaults written beside them
     * and the command worked only because those defaults happened to be the intended window. A
     * typo'd key that silently returns a plausible answer is the exact shape of defect this phase
     * exists to find in somebody else's ledger.
     *
     * MUTATION PROOF. Restoring `window_from`/`window_to` in resolveYears() makes this test fail on
     * the missing exception, because the wrong key falls through to a default instead of refusing.
     */
    public function test_serials_command_refuses_when_the_replay_window_is_not_configured(): void
    {
        $this->seedCityTravelersBaseline();

        // This test exercises a COMMAND, so its declared write set has to cover everything the
        // command's own post-conditions will see grow — `users` and `serial_schemas` included.
        // (The narrow default set in setUp() is what test_undeclared_table_growth_is_refused needs,
        // and widening it there would make that test vacuous.)
        config()->set('legacy_pilot.ct_scope.tables', [
            'companies', 'users', 'branches', 'accounts', 'serial_schemas', 'transactions', 'journal_entries',
        ]);
        config()->set('legacy_pilot.ct_scope.global_tables', ['companies', 'users']);

        // ARM FIRST. Without this the command exits non-zero anyway — its post-conditions refuse
        // because no pre-load baseline is persisted — and this test would pass with the wrong
        // config keys restored. A mutation run proved exactly that. Arming makes the window config
        // the only thing left that can refuse.
        $this->artisan('legacy:scope', ['--company' => self::FLOOR, '--apply' => true])->assertExitCode(0);
        $this->seedLegacyCompanyWithBranch('CO');

        // The keys the command must read. Asserting they EXIST first means this test fails loudly
        // if the config is renamed again, rather than passing for the wrong reason.
        $this->assertIsString(config('legacy_pilot.replay.window_start'));
        $this->assertIsString(config('legacy_pilot.replay.window_end'));

        // Control: with the window configured and no --years, the command succeeds. This is what
        // makes the refusal below meaningful rather than a permanent failure.
        $this->artisan('legacy:serials', ['--company' => self::FLOOR, '--apply' => true])
            ->assertExitCode(0);

        $seeded = DB::table('serial_schemas')->where('company_id', self::FLOOR)->count();
        $this->assertGreaterThan(0, $seeded);

        config()->set('legacy_pilot.replay.window_start', null);

        try {
            $this->resolveSerialYears();
            $this->fail('Expected LegacyScopeRefused: the replay window is not configured.');
        } catch (LegacyScopeRefused $e) {
            $this->assertStringContainsString('window_start / window_end are not both configured', $e->getMessage());
            $this->assertStringContainsString('will not substitute a plausible default', $e->getMessage());
        }

        $this->artisan('legacy:serials', ['--company' => self::FLOOR, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(
            $seeded,
            DB::table('serial_schemas')->where('company_id', self::FLOOR)->count(),
            'legacy:serials wrote rows although the window it derives years from was unconfigured'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * {@see \App\Console\Commands\Legacy\LegacySerialsCommand::resolveYears()}, invoked directly
     * so its refusal MESSAGE can be asserted — Command::error() wraps at the terminal width, so a
     * long substring straddles a line break and expectsOutputToContain() can never match it.
     *
     * @return list<int>
     */
    private function resolveSerialYears(): array
    {
        $command = new \App\Console\Commands\Legacy\LegacySerialsCommand;
        $method = new \ReflectionMethod($command, 'resolveYears');
        $method->setAccessible(true);

        // resolveYears() reads only $this->option('years'), which is empty on a freshly constructed
        // command, and config() — which is what this test is about.
        $command->setLaravel($this->app);
        $input = new \Symfony\Component\Console\Input\ArrayInput([], $command->getDefinition());
        $command->setInput($input);

        return $method->invoke($command);
    }

    /** @return array<string,int> */
    private function rowSnapshot(): array
    {
        $out = [];

        foreach (['companies', 'branches', 'accounts', 'transactions', 'journal_entries', 'users'] as $t) {
            $out[$t] = (int) DB::table($t)->count();
        }

        return $out;
    }

    private function seedCityTravelersBaseline(): void
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

    private function seedLegacyCompanyWithBranch(string $branchName): void
    {
        DB::table('users')->insert(['id' => self::FLOOR, 'role_id' => 1, 'name' => 'Como', 'email' => 'como@example.invalid', 'password' => 'x']);
        DB::table('companies')->insert([
            'id' => self::FLOOR, 'user_id' => self::FLOOR, 'code' => 'COMO', 'currency' => 'KWD',
            'country_id' => 1, 'status' => 1, 'name' => 'Como',
        ]);
        DB::table('branches')->insert([
            'id' => self::FLOOR, 'user_id' => self::FLOOR, 'company_id' => self::FLOOR, 'name' => $branchName,
        ]);
    }
}
