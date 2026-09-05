<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reconciliation;

use App\Models\Company;
use App\Models\SupplierStatementImport;
use App\Services\Accounting\Reconciliation\SupplierStatementImportInput;
use App\Services\Accounting\Reconciliation\SupplierStatementImporter;
use App\Services\Accounting\Reconciliation\SupplierStatementImportRejected;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T8 (Lane E): SupplierStatementImporterTest — column map config + override,
 * bad file rejected, row counts (PLAN.md §5 Lane E test list).
 */
class SupplierStatementImporterTest extends AccountingTestCase
{
    private const DEFAULT_HEADER = ['Booking Reference', 'Confirmation Code', 'Guest Name', 'Check-in Date', 'Amount', 'Currency', 'Statement Date', 'Description'];

    private function importer(): SupplierStatementImporter
    {
        return app(SupplierStatementImporter::class);
    }

    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function writeCsv(array $header, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ssi_test_').'.csv';
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
        $path = tempnam(sys_get_temp_dir(), 'ssi_test_').'.xlsx';
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
        $company = $this->makeCompany();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['DTW-001', 'CONF-001', 'Jane Doe', '2026-08-01', '120.500', 'KWD', '2026-08-05', 'Room charge'],
            ['DTW-002', 'CONF-002', 'John Roe', '2026-08-02', '80.000', 'KWD', '2026-08-05', 'Room charge'],
        ]);

        $import = $this->importer()->import(new SupplierStatementImportInput(
            companyId: $company->id,
            supplierId: 999,
            absoluteFilePath: $path,
            fileName: 'dotw-statement.csv',
            statementCurrency: 'KWD',
        ));

        $this->assertSame(SupplierStatementImport::STATUS_STAGED, $import->status);
        $this->assertCount(2, $import->lines);
        $this->assertSame('DTW-001', $import->lines->first()->booking_ref);
        $this->assertEqualsWithDelta(120.5, $import->lines->first()->amount, 0.0001);

        @unlink($path);
    }

    public function test_imports_an_xlsx_statement(): void
    {
        $company = $this->makeCompany();
        $path = $this->writeXlsx(self::DEFAULT_HEADER, [
            ['DTW-101', 'CONF-101', 'Amir K', '2026-08-10', 200, 'KWD', '2026-08-12', 'Room charge'],
        ]);

        $import = $this->importer()->import(new SupplierStatementImportInput(
            companyId: $company->id,
            supplierId: 999,
            absoluteFilePath: $path,
            fileName: 'dotw-statement.xlsx',
            statementCurrency: 'KWD',
        ));

        $this->assertCount(1, $import->lines);
        $this->assertSame('DTW-101', $import->lines->first()->booking_ref);

        @unlink($path);
    }

    public function test_column_map_override_reads_a_relabelled_file(): void
    {
        $company = $this->makeCompany();
        $customHeader = ['Ref', 'ConfNo', 'Guest', 'CheckIn', 'Amt', 'Cur', 'StmtDate', 'Desc'];
        $path = $this->writeCsv($customHeader, [
            ['DTW-201', 'CONF-201', 'Sara M', '2026-08-15', '55.250', 'KWD', '2026-08-16', 'Room charge'],
        ]);

        $import = $this->importer()->import(new SupplierStatementImportInput(
            companyId: $company->id,
            supplierId: 999,
            absoluteFilePath: $path,
            fileName: 'relabelled.csv',
            statementCurrency: 'KWD',
            columnMapOverride: [
                'booking_ref' => 'Ref',
                'confirmation_code' => 'ConfNo',
                'guest' => 'Guest',
                'checkin' => 'CheckIn',
                'amount' => 'Amt',
                'currency' => 'Cur',
                'statement_date' => 'StmtDate',
                'description' => 'Desc',
            ],
        ));

        $this->assertCount(1, $import->lines);
        $this->assertSame('DTW-201', $import->lines->first()->booking_ref);
        $this->assertEqualsWithDelta(55.25, $import->lines->first()->amount, 0.0001);

        @unlink($path);
    }

    public function test_rejects_a_file_missing_a_required_column(): void
    {
        $company = $this->makeCompany();
        // No 'Amount' column at all.
        $path = $this->writeCsv(['Booking Reference', 'Currency'], [['DTW-301', 'KWD']]);

        $this->expectException(SupplierStatementImportRejected::class);

        $this->importer()->import(new SupplierStatementImportInput(
            companyId: $company->id,
            supplierId: 999,
            absoluteFilePath: $path,
            fileName: 'bad.csv',
            statementCurrency: 'KWD',
        ));
    }

    public function test_rejects_an_empty_file(): void
    {
        $company = $this->makeCompany();
        $path = tempnam(sys_get_temp_dir(), 'ssi_empty_').'.csv';
        touch($path);

        $this->expectException(SupplierStatementImportRejected::class);

        try {
            $this->importer()->import(new SupplierStatementImportInput(
                companyId: $company->id,
                supplierId: 999,
                absoluteFilePath: $path,
                fileName: 'empty.csv',
                statementCurrency: 'KWD',
            ));
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_an_unsupported_extension(): void
    {
        $company = $this->makeCompany();
        $path = tempnam(sys_get_temp_dir(), 'ssi_bad_ext_');
        file_put_contents($path, 'not a statement');

        $this->expectException(SupplierStatementImportRejected::class);

        try {
            $this->importer()->import(new SupplierStatementImportInput(
                companyId: $company->id,
                supplierId: 999,
                absoluteFilePath: $path,
                fileName: 'notes.pdf',
                statementCurrency: 'KWD',
            ));
        } finally {
            @unlink($path);
        }
    }

    public function test_blank_rows_are_skipped_and_row_count_is_correct(): void
    {
        $company = $this->makeCompany();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['DTW-401', 'CONF-401', 'A', '2026-08-01', '10.000', 'KWD', '2026-08-05', 'x'],
            ['', '', '', '', '', '', '', ''],
            ['DTW-402', 'CONF-402', 'B', '2026-08-02', '20.000', 'KWD', '2026-08-05', 'y'],
        ]);

        $import = $this->importer()->import(new SupplierStatementImportInput(
            companyId: $company->id,
            supplierId: 999,
            absoluteFilePath: $path,
            fileName: 'with-blank-row.csv',
            statementCurrency: 'KWD',
        ));

        $this->assertCount(2, $import->lines);

        @unlink($path);
    }

    public function test_reimporting_the_identical_file_is_idempotent(): void
    {
        $company = $this->makeCompany();
        $path = $this->writeCsv(self::DEFAULT_HEADER, [
            ['DTW-501', 'CONF-501', 'A', '2026-08-01', '10.000', 'KWD', '2026-08-05', 'x'],
        ]);

        $input = new SupplierStatementImportInput(
            companyId: $company->id,
            supplierId: 999,
            absoluteFilePath: $path,
            fileName: 'repeat.csv',
            statementCurrency: 'KWD',
        );

        $first = $this->importer()->import($input);
        $second = $this->importer()->import($input);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SupplierStatementImport::withoutGlobalScopes()->where('company_id', $company->id)->count());

        @unlink($path);
    }

    public function test_reimporting_by_explicit_statement_reference_is_idempotent_even_if_file_content_differs(): void
    {
        $company = $this->makeCompany();
        $pathA = $this->writeCsv(self::DEFAULT_HEADER, [['DTW-601', 'CONF-601', 'A', '2026-08-01', '10.000', 'KWD', '2026-08-05', 'x']]);
        $pathB = $this->writeCsv(self::DEFAULT_HEADER, [['DTW-602', 'CONF-602', 'B', '2026-08-02', '20.000', 'KWD', '2026-08-05', 'y']]);

        $first = $this->importer()->import(new SupplierStatementImportInput(
            companyId: $company->id, supplierId: 999, absoluteFilePath: $pathA, fileName: 'a.csv',
            statementCurrency: 'KWD', statementReference: 'DOTW-2026-08',
        ));
        $second = $this->importer()->import(new SupplierStatementImportInput(
            companyId: $company->id, supplierId: 999, absoluteFilePath: $pathB, fileName: 'b.csv',
            statementCurrency: 'KWD', statementReference: 'DOTW-2026-08',
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SupplierStatementImport::withoutGlobalScopes()->where('company_id', $company->id)->count());

        @unlink($pathA);
        @unlink($pathB);
    }
}
