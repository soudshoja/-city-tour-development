<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reconciliation;

use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\Company;
use App\Services\Accounting\Reconciliation\BankStatementImportConflict;
use App\Services\Accounting\Reconciliation\BankStatementImporter;
use App\Services\Accounting\Reconciliation\BankStatementImportInput;
use App\Services\Accounting\Reconciliation\BankStatementImportRejected;
use Database\Seeders\CoaSeeder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T9 (Wave 2): BankStatementImporterTest — column map config + override, bad
 * file rejected, row counts, bank-leaf currency scoping, idempotent re-import, and the
 * "changed re-import under same reference = conflict" requirement this task adds on top of T8's
 * shape. Mirrors T8's SupplierStatementImporterTest structure exactly.
 */
class BankStatementImporterTest extends AccountingTestCase
{
    private const DEFAULT_HEADER = ['Value Date', 'Posting Date', 'Description', 'Reference', 'Auth No', 'Cheque No', 'Debit', 'Credit', 'Balance'];

    private function importer(): BankStatementImporter
    {
        return app(BankStatementImporter::class);
    }

    /** @return array{0: Company, 1: Account} */
    private function makeCompanyWithBankLeaf(string $currency = 'KWD'): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        $this->trackCompanyForInvariants($company->id);

        $leaf = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        if ($currency !== 'KWD') {
            $leaf->currency = $currency;
            $leaf->save();
        }

        return [$company, $leaf];
    }

    private function writeCsv(array $header, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bsi_test_').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    private function writeXlsx(array $header, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bsi_test_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A'.$rowIndex);
            $rowIndex++;
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_imports_a_csv_statement_with_default_column_map(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['2026-08-01', '2026-08-01', 'Salary transfer in', 'REF-001', 'AUTH-001', '', '', '500.000', '5500.000'],
            ['2026-08-02', '2026-08-02', 'Supplier payment out', 'REF-002', '', '', '120.500', '', '5379.500'],
        ]);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id,
            bankAccountId: $leaf->id,
            absoluteFilePath: $path,
            fileName: 'bank-statement.csv',
            statementCurrency: 'KWD',
        ));

        $this->assertSame(BankStatementImport::STATUS_STAGED, $import->status);
        $this->assertCount(2, $import->lines);
        $this->assertSame('REF-001', $import->lines->first()->reference);
        $this->assertEqualsWithDelta(500.0, $import->lines->first()->credit, 0.0001);
        $this->assertEqualsWithDelta(5379.5, $import->closing_balance, 0.0001);

        @unlink($path);
    }

    public function test_imports_an_xlsx_statement(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeXlsx(self::DEFAULT_HEADER, [
            ['2026-08-10', '2026-08-10', 'Deposit', 'REF-101', '', '', '', 200, '200'],
        ]);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id,
            bankAccountId: $leaf->id,
            absoluteFilePath: $path,
            fileName: 'bank-statement.xlsx',
            statementCurrency: 'KWD',
        ));

        $this->assertCount(1, $import->lines);
        $this->assertSame('REF-101', $import->lines->first()->reference);

        @unlink($path);
    }

    public function test_column_map_override_reads_a_relabelled_file(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $customHeader = ['VDate', 'PDate', 'Narration', 'Ref', 'Auth', 'Cheque', 'Dr', 'Cr', 'Bal'];
        $path = $this->writeCsv($customHeader, [
            ['2026-08-15', '2026-08-15', 'x', 'REF-201', '', '', '', '55.250', '55.250'],
        ]);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id,
            bankAccountId: $leaf->id,
            absoluteFilePath: $path,
            fileName: 'relabelled.csv',
            statementCurrency: 'KWD',
            columnMapOverride: [
                'value_date' => 'VDate', 'posting_date' => 'PDate', 'description' => 'Narration',
                'reference' => 'Ref', 'auth_no' => 'Auth', 'cheque_no' => 'Cheque', 'debit' => 'Dr',
                'credit' => 'Cr', 'running_balance' => 'Bal',
            ],
        ));

        $this->assertCount(1, $import->lines);
        $this->assertSame('REF-201', $import->lines->first()->reference);
        $this->assertEqualsWithDelta(55.25, $import->lines->first()->credit, 0.0001);

        @unlink($path);
    }

    public function test_rejects_a_file_missing_a_required_column(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        // No 'Debit'/'Credit' columns at all.
        $path = $this->writeCsv(['Value Date', 'Reference'], [['2026-08-01', 'REF-301']]);

        $this->expectException(BankStatementImportRejected::class);

        $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id,
            bankAccountId: $leaf->id,
            absoluteFilePath: $path,
            fileName: 'bad.csv',
            statementCurrency: 'KWD',
        ));
    }

    public function test_rejects_an_empty_file(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = tempnam(sys_get_temp_dir(), 'bsi_empty_').'.csv';
        touch($path);

        $this->expectException(BankStatementImportRejected::class);

        try {
            $this->importer()->import(new BankStatementImportInput(
                companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
                fileName: 'empty.csv', statementCurrency: 'KWD',
            ));
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_an_unsupported_extension(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = tempnam(sys_get_temp_dir(), 'bsi_bad_ext_');
        file_put_contents($path, 'not a statement');

        $this->expectException(BankStatementImportRejected::class);

        try {
            $this->importer()->import(new BankStatementImportInput(
                companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
                fileName: 'notes.pdf', statementCurrency: 'KWD',
            ));
        } finally {
            @unlink($path);
        }
    }

    public function test_blank_rows_are_skipped_and_row_count_is_correct(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['2026-08-01', '2026-08-01', 'a', 'REF-401', '', '', '', '10.000', '10.000'],
            ['', '', '', '', '', '', '', '', ''],
            ['2026-08-02', '2026-08-02', 'b', 'REF-402', '', '', '', '20.000', '30.000'],
        ]);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
            fileName: 'with-blank-row.csv', statementCurrency: 'KWD',
        ));

        $this->assertCount(2, $import->lines);

        @unlink($path);
    }

    public function test_reimporting_the_identical_file_is_idempotent(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['2026-08-01', '2026-08-01', 'a', 'REF-501', '', '', '', '10.000', '10.000'],
        ]);

        $input = new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
            fileName: 'repeat.csv', statementCurrency: 'KWD',
        );

        $first = $this->importer()->import($input);
        $second = $this->importer()->import($input);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BankStatementImport::withoutGlobalScopes()->where('company_id', $company->id)->count());

        @unlink($path);
    }

    public function test_reimporting_by_explicit_reference_with_identical_content_is_idempotent(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['2026-08-01', '2026-08-01', 'a', 'REF-601', '', '', '', '10.000', '10.000'],
        ]);

        $input = new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
            fileName: 'a.csv', statementCurrency: 'KWD', statementReference: 'NBK-2026-08',
        );

        $first = $this->importer()->import($input);
        $second = $this->importer()->import($input);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BankStatementImport::withoutGlobalScopes()->where('company_id', $company->id)->count());

        @unlink($path);
    }

    /**
     * T9's own explicit requirement (spec: "changed re-import under same reference =
     * conflict/warn") — a DELIBERATE closing of the gap T8's own review packet left open
     * (advisory, not fixed: "silently keeps the first import when a same-reference file's
     * content differs"). See the migration's own docblock for the two-step identity resolution
     * this enables.
     */
    public function test_reimporting_by_explicit_reference_with_different_content_is_a_conflict(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $pathA = $this->writeCsv(self::DEFAULT_HEADER, [['2026-08-01', '2026-08-01', 'a', 'REF-701', '', '', '', '10.000', '10.000']]);
        $pathB = $this->writeCsv(self::DEFAULT_HEADER, [['2026-08-02', '2026-08-02', 'b', 'REF-702', '', '', '', '20.000', '20.000']]);

        $first = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $pathA,
            fileName: 'a.csv', statementCurrency: 'KWD', statementReference: 'NBK-2026-09',
        ));

        $this->expectException(BankStatementImportConflict::class);

        try {
            $this->importer()->import(new BankStatementImportInput(
                companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $pathB,
                fileName: 'b.csv', statementCurrency: 'KWD', statementReference: 'NBK-2026-09',
            ));
        } finally {
            $this->assertSame(1, BankStatementImport::withoutGlobalScopes()->where('company_id', $company->id)->count());
            @unlink($pathA);
            @unlink($pathB);
        }
    }

    /** spec: "company + bank-leaf scoping (a KWD bank statement never matches a USD bank leaf's lines)". */
    public function test_rejects_a_statement_whose_currency_does_not_match_the_bank_leafs_own_currency(): void
    {
        [$company, $usdLeaf] = $this->makeCompanyWithBankLeaf('USD');
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['2026-08-01', '2026-08-01', 'a', 'REF-801', '', '', '', '10.000', '10.000'],
        ]);

        $this->expectException(BankStatementImportRejected::class);

        try {
            $this->importer()->import(new BankStatementImportInput(
                companyId: $company->id, bankAccountId: $usdLeaf->id, absoluteFilePath: $path,
                fileName: 'kwd-on-usd-leaf.csv', statementCurrency: 'KWD',
            ));
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_a_bank_account_belonging_to_a_different_company(): void
    {
        [, $leafA] = $this->makeCompanyWithBankLeaf();
        [$companyB] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['2026-08-01', '2026-08-01', 'a', 'REF-901', '', '', '', '10.000', '10.000'],
        ]);

        $this->expectException(BankStatementImportRejected::class);

        try {
            $this->importer()->import(new BankStatementImportInput(
                companyId: $companyB->id, bankAccountId: $leafA->id, absoluteFilePath: $path,
                fileName: 'cross-company.csv', statementCurrency: 'KWD',
            ));
        } finally {
            @unlink($path);
        }
    }

    // ── Post-sign-off fix (T9 §12 note 2): dd/mm/yyyy date parsing ─────────────────────────────

    /** Default date_format is `d/m/Y` — the shape a Kuwaiti bank export uses. */
    public function test_default_date_format_parses_dd_mm_yyyy(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['25/03/2026', '25/03/2026', 'a', 'REF-DF1', '', '', '', '10.000', '10.000'],
        ]);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
            fileName: 'ddmmyyyy.csv', statementCurrency: 'KWD',
        ));

        $this->assertSame('2026-03-25', $import->lines->first()->value_date->toDateString());

        @unlink($path);
    }

    /**
     * The exact defect from the sign-off packet §12 note 2: `03/04/2026` under dd/mm/yyyy is
     * 3 April — NOT 4 March, which is what a bare `Carbon::parse()` used to silently misread it
     * as for every day-of-month <= 12.
     */
    public function test_default_date_format_reads_day_before_month_not_the_other_way_round(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['03/04/2026', '03/04/2026', 'a', 'REF-DF2', '', '', '', '10.000', '10.000'],
        ]);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
            fileName: 'ddmmyyyy2.csv', statementCurrency: 'KWD',
        ));

        $this->assertSame('2026-04-03', $import->lines->first()->value_date->toDateString());

        @unlink($path);
    }

    /** An explicit `dateFormatOverride` — the "part of the configurable column mapping" this fix adds — reads ISO dates. */
    public function test_iso_date_format_via_explicit_override(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['2026-03-25', '2026-03-25', 'a', 'REF-DF3', '', '', '', '10.000', '10.000'],
        ]);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
            fileName: 'iso.csv', statementCurrency: 'KWD', dateFormatOverride: 'Y-m-d',
        ));

        $this->assertSame('2026-03-25', $import->lines->first()->value_date->toDateString());

        @unlink($path);
    }

    /**
     * An unparseable date rejects the WHOLE import with a clear, per-row message — never a bare
     * `QueryException`/HTTP 500 from a NULL `value_date` reaching the insert (the sign-off
     * packet's own probed defect). Nothing is persisted.
     */
    public function test_an_unparseable_date_rejects_the_whole_import_with_a_clear_message(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['not-a-date', '', 'a', 'REF-DF4', '', '', '', '10.000', '10.000'],
        ]);

        try {
            $this->importer()->import(new BankStatementImportInput(
                companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
                fileName: 'bad-date.csv', statementCurrency: 'KWD',
            ));
            $this->fail('Expected BankStatementImportRejected.');
        } catch (BankStatementImportRejected $e) {
            $this->assertStringContainsString('row 1', $e->getMessage());
            $this->assertStringContainsString('value_date', $e->getMessage());
            $this->assertStringContainsString('not-a-date', $e->getMessage());
        } finally {
            $this->assertSame(0, BankStatementImport::withoutGlobalScopes()->where('company_id', $company->id)->count());
            @unlink($path);
        }
    }

    /** Keeps a genuine XLSX date-typed cell (a numeric Excel serial, never a string) working under the new strict-format path. */
    public function test_xlsx_numeric_date_serial_still_parses(): void
    {
        [$company, $leaf] = $this->makeCompanyWithBankLeaf();
        $path = tempnam(sys_get_temp_dir(), 'bsi_test_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::DEFAULT_HEADER, null, 'A1');
        $sheet->setCellValue('A2', \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime('2026-03-25')));
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->fromArray(['', 'Deposit', 'REF-DF5', '', '', '', '10.000', '10.000'], null, 'B2');
        (new Xlsx($spreadsheet))->save($path);

        $import = $this->importer()->import(new BankStatementImportInput(
            companyId: $company->id, bankAccountId: $leaf->id, absoluteFilePath: $path,
            fileName: 'serial.xlsx', statementCurrency: 'KWD',
        ));

        $this->assertCount(1, $import->lines);
        $this->assertSame('2026-03-25', $import->lines->first()->value_date->toDateString());

        @unlink($path);
    }
}
