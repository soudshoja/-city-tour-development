<?php

namespace Tests\Feature\Accounting;

use App\Console\Commands\SeedAccountingSerialSchemas;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SerialSchema;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\AccountingTestCase;

/**
 * W3-prereq lane B (doc 17 §3.3/§4), ruling (2): `accounting:seed-serial-schemas` must seed
 * `serial_schemas.last_serial` ABOVE the legacy maximum per (company, branch, doc_type, doc_year)
 * so an engine-minted number colliding with a legacy one is the exception, not the rule — and
 * re-running it must be idempotent (never lowers, never re-raises past what a second real scan
 * would find).
 *
 * NO assertion here reads console text (`Artisan::output()`), by design, matching this suite's
 * own established rule (see EnsureSystemLeavesTest's class docblock): Tests\TestCase::setUp()
 * runs `$this->artisan('db:seed', ['--class' => 'PermissionSeeder'])` for every RefreshDatabase
 * test, and Laravel's InteractsWithConsole::mockConsoleOutput() permanently rebinds
 * Illuminate\Console\OutputStyle to a fixed Mockery buffer for the rest of the test — every LATER
 * Command::run() in the same test, including ones invoked through Artisan::call(), resolves that
 * same stale mock instead of the real buffer Artisan::output() reads back from, so it reads empty
 * regardless of what the command actually printed (confirmed empirically against this exact
 * command during this build). DB state and direct return values are this suite's proof instead.
 */
class SeedAccountingSerialSchemasTest extends AccountingTestCase
{
    private function invokePrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    private function plantLegacyTransaction(Company $company, string $referenceNumber): Transaction
    {
        return Transaction::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 100.000,
            'description' => 'SeedAccountingSerialSchemasTest legacy fixture',
            'reference_type' => 'Invoice',
            'reference_number' => $referenceNumber,
            'transaction_date' => now(),
        ]);
    }

    public function test_seeds_above_a_planted_legacy_max_and_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $year = (int) now()->year;

        // A legacy row: doc_type NULL (pre-engine — see the migration's own NULL-safety analysis),
        // branch_id NULL (pre-dates the branch-scoped counter), reference_number in the exact
        // shape the engine's own branchless mask renders.
        $this->plantLegacyTransaction($company, 'INV-'.$year.'-00500');

        $exitCode = Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['INV'],
            '--year' => $year,
        ]);
        $this->assertSame(0, $exitCode);

        $fetchLastSerial = function (int $branchKey) use ($company, $year): int {
            return (int) DB::table('serial_schemas')
                ->where('company_id', $company->id)
                ->where('branch_id', $branchKey)
                ->where('doc_type', 'INV')
                ->where('doc_year', $year)
                ->value('last_serial');
        };

        $this->assertSame(
            500,
            $fetchLastSerial(0),
            'The no-branch sentinel row must be seeded to the legacy ceiling.'
        );
        $this->assertSame(
            500,
            $fetchLastSerial($branch->id),
            'Every real branch this company could post under must ALSO be seeded to the same '
                .'company-wide ceiling (P1 ROUND 3 fix) — the legacy counter was never branch-scoped.'
        );

        // Idempotent: re-running with no new legacy data must change nothing.
        Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['INV'],
            '--year' => $year,
        ]);

        $this->assertSame(500, $fetchLastSerial(0), 'Re-running must not change an already-seeded value.');
        $this->assertSame(500, $fetchLastSerial($branch->id), 'Re-running must not change an already-seeded value.');

        // A THIRD run after a legacy number even HIGHER than the seeded value appears must raise
        // it again — "idempotent" means "no spurious change", not "frozen forever".
        $this->plantLegacyTransaction($company, 'INV-'.$year.'-00777');

        Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['INV'],
            '--year' => $year,
        ]);

        $this->assertSame(777, $fetchLastSerial(0));
        $this->assertSame(777, $fetchLastSerial($branch->id));
    }

    public function test_dry_run_reports_the_proposed_value_without_writing_any_row(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $year = (int) now()->year;

        $this->plantLegacyTransaction($company, 'INV-'.$year.'-00042');

        $exitCode = Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['INV'],
            '--year' => $year,
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $exitCode);

        $this->assertSame(
            0,
            DB::table('serial_schemas')->where('company_id', $company->id)->count(),
            'A --dry-run call must never write any serial_schemas row.'
        );

        // The command's --dry-run path is a pure passthrough of $dryRun to
        // SerialSchema::seedFromLegacyMax() (see SeedAccountingSerialSchemas::handle()) — calling
        // it directly is what the command itself would have printed as the proposed "Seeded"
        // value, without depending on console-text capture (see class docblock for why that's
        // unreliable in this suite).
        $report = SerialSchema::seedFromLegacyMax($company->id, 'INV', $year, true);
        $this->assertNotEmpty($report);
        $this->assertSame(42, $report[0]['seeded_last_serial']);
        $this->assertTrue($report[0]['changed']);

        // Proven independently: the dry run truly changed nothing, so the direct call above must
        // still report the SAME proposed value, not one shifted by an accidental write.
        $this->assertSame(
            0,
            DB::table('serial_schemas')->where('company_id', $company->id)->count()
        );
    }

    /**
     * Ruling (2)'s parseability diagnostic (SeedAccountingSerialSchemas::scanLegacyParseability())
     * — read-only, reported alongside the real seeding table. A legacy value ending in digits but
     * not matching the strict `{TYPE}-{YYYY}-{SEQ}` shape is classified as fallback-parsed, not
     * silently dropped (it is still excluded from the actual seeded ceiling itself — see that
     * method's own docblock for why); a value with no trailing digit group at all is unparseable.
     * Invoked directly via reflection (invokePrivate(), the same pattern already used across this
     * suite — e.g. CreditApplicationCrossFeederIdempotencyTest) rather than through the console,
     * for the same reason the other tests in this class avoid Artisan::output() (see class
     * docblock).
     */
    public function test_scan_legacy_parseability_classifies_strict_fallback_and_unparseable_numbers(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $year = (int) now()->year;

        // Matches the strict mask.
        $this->plantLegacyTransaction($company, 'INV-'.$year.'-00010');
        // Doesn't match the strict mask (no 4-digit year segment shape) but still ends in digits.
        $this->plantLegacyTransaction($company, 'INV-LEGACY-00099');
        // Doesn't end in any digit group at all.
        $this->plantLegacyTransaction($company, 'INV-UNKNOWN');

        $command = app(SeedAccountingSerialSchemas::class);
        $scan = $this->invokePrivate($command, 'scanLegacyParseability', [$company->id, 'INV']);

        $this->assertSame(1, $scan['strict'], 'Exactly one reference_number matches the strict {TYPE}-{YYYY}-{SEQ} mask.');
        $this->assertSame(1, $scan['fallback'], 'Exactly one reference_number is fallback-parseable (trailing digits, wrong shape).');
        $this->assertSame(99, $scan['fallback_max']);
        $this->assertSame(1, $scan['unparseable'], 'Exactly one reference_number has no trailing digit group at all.');
    }
}
