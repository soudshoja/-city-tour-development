<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\Company;
use App\Models\SerialSchema;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AccountingTestCase;

/**
 * W5.L item 2 (w5-brief.md §W5.L): serial schemas RV/{branch}/{yy}/{seq}, PV/{branch}/{yy}/{seq},
 * and the NEW AST/{branch}/{yy}/{seq} registered in `accounting:seed-serial-schemas`, seeded above
 * legacy max per company, --dry-run honoured, and legacy RANDOM refs (RV's
 * `'RV-' . Str::upper(Str::random(10))`, per w5-state.md's as-is table row "Numbering") never
 * renumbered — i.e. never mistaken for a legacy ceiling to seed above.
 *
 * Companion to SeedAccountingSerialSchemasTest (the INV-focused suite this class does not
 * duplicate) — this file is scoped to the three voucher/settlement doc_types W5.L adds coverage
 * for. Same "no Artisan::output() assertions" convention as that file — see its own docblock for
 * why (Tests\TestCase::setUp()'s PermissionSeeder call permanently rebinds the console output mock).
 */
class SeedAccountingSerialSchemasVoucherSeriesTest extends AccountingTestCase
{
    private function plantTransaction(Company $company, string $referenceNumber, string $docType): Transaction
    {
        return Transaction::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => $docType,
            'amount' => 100.000,
            'description' => 'SeedAccountingSerialSchemasVoucherSeriesTest legacy fixture',
            'reference_type' => 'Receipt',
            'reference_number' => $referenceNumber,
            'transaction_date' => now(),
        ]);
    }

    private function lastSerial(Company $company, string $docType, int $branchKey, int $year): int
    {
        return (int) DB::table('serial_schemas')
            ->where('company_id', $company->id)
            ->where('branch_id', $branchKey)
            ->where('doc_type', $docType)
            ->where('doc_year', $year)
            ->value('last_serial');
    }

    /**
     * AST is the NEW doc_type this wave adds to ALL_DOC_TYPES — it must seed exactly like RV/PV
     * already do (default-run-every-company shape), starting at 0 with no legacy AST rows in the
     * database (confirmed by SerialSchema::seedFromLegacyMax()'s own docblock: no legacy call site
     * anywhere in this codebase ever wrote an 'AST-' reference number).
     */
    public function test_default_run_seeds_ast_alongside_rv_and_pv_with_no_option_needed(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $year = (int) now()->year;

        // No --doc-type option at all — the default ("every engine doc_type") path.
        $exitCode = Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--year' => $year,
        ]);
        $this->assertSame(0, $exitCode);

        foreach (['RV', 'PV', 'AST'] as $docType) {
            $this->assertSame(
                0,
                $this->lastSerial($company, $docType, 0, $year),
                "{$docType} must be seeded (a row must exist) even with no legacy data — 0 is the correct ceiling."
            );
            $this->assertSame(
                0,
                $this->lastSerial($company, $docType, $branch->id, $year),
                "{$docType}'s real-branch row must also exist, seeded to the same ceiling."
            );
        }
    }

    /**
     * A genuine legacy PV reference number that HAPPENS to match the strict {TYPE}-{YYYY}-{SEQ}
     * mask (BankPaymentController's bankpaymentref is user-supplied free text, so this is a real,
     * if uncommon, possibility) must still raise the seeded ceiling above it — same rule INV
     * already gets, extended to PV/AST explicitly by this test.
     */
    public function test_seeds_pv_and_ast_above_a_planted_legacy_max_and_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $year = (int) now()->year;

        $this->plantTransaction($company, 'PV-'.$year.'-00300', 'PV');
        $this->plantTransaction($company, 'AST-'.$year.'-00050', 'PV');

        Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['PV', 'AST'],
            '--year' => $year,
        ]);

        $this->assertSame(300, $this->lastSerial($company, 'PV', 0, $year));
        $this->assertSame(300, $this->lastSerial($company, 'PV', $branch->id, $year));
        $this->assertSame(50, $this->lastSerial($company, 'AST', 0, $year));
        $this->assertSame(50, $this->lastSerial($company, 'AST', $branch->id, $year));

        // Idempotent re-run must not change anything.
        Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['PV', 'AST'],
            '--year' => $year,
        ]);

        $this->assertSame(300, $this->lastSerial($company, 'PV', 0, $year));
        $this->assertSame(50, $this->lastSerial($company, 'AST', 0, $year));
    }

    /**
     * The core W5.L requirement: RV's existing legacy random reference numbers
     * ('RV-' . Str::upper(Str::random(10)), w5-state.md row "Numbering") must NEVER be mistaken for
     * a legacy ceiling to seed above — Str::random()'s alphabet contains no '-' separator, so the
     * strict `{TYPE}-{YYYY}-{SEQ}` mask this scan requires structurally cannot match it. Proven
     * directly against the REAL legacy shape, not merely asserted from the regex.
     */
    public function test_legacy_random_rv_reference_numbers_are_never_renumbered(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $year = (int) now()->year;

        // The exact legacy shape: ReceiptVoucherController.php's own
        // 'RV-' . Str::upper(Str::random(10)).
        for ($i = 0; $i < 5; $i++) {
            $this->plantTransaction($company, 'RV-'.Str::upper(Str::random(10)), 'RV');
        }

        Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['RV'],
            '--year' => $year,
        ]);

        $this->assertSame(
            0,
            $this->lastSerial($company, 'RV', 0, $year),
            'Legacy RANDOM RV reference numbers must never be parsed as a numeric ceiling — the '
                .'seeded last_serial must stay at 0 (nothing legacy to collide with), never renumbered.'
        );
    }

    /**
     * --dry-run must never write any serial_schemas row for AST, matching the existing behaviour
     * SeedAccountingSerialSchemasTest already pins for INV.
     */
    public function test_dry_run_never_writes_a_serial_schemas_row_for_ast(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $year = (int) now()->year;

        $this->plantTransaction($company, 'AST-'.$year.'-00099', 'PV');

        $exitCode = Artisan::call('accounting:seed-serial-schemas', [
            '--company' => [$company->id],
            '--doc-type' => ['AST'],
            '--year' => $year,
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $exitCode);

        $this->assertSame(
            0,
            DB::table('serial_schemas')->where('company_id', $company->id)->where('doc_type', 'AST')->count(),
            'A --dry-run call must never write any serial_schemas row.'
        );

        $report = SerialSchema::seedFromLegacyMax($company->id, 'AST', $year, true);
        $this->assertSame(99, $report[0]['seeded_last_serial']);
        $this->assertTrue($report[0]['changed']);

        $this->assertSame(
            0,
            DB::table('serial_schemas')->where('company_id', $company->id)->where('doc_type', 'AST')->count()
        );
    }
}
