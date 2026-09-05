<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

use App\Models\SupplierStatementImport;
use App\Models\SupplierStatementImportLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * accounting-builds T8 (Lane E). Parses a DOTW supplier statement (CSV or XLSX, L15 — no new
 * Composer dependency: `maatwebsite/excel` ^3.1 was already a project dependency before this
 * task, verified via `composer show maatwebsite/excel`) into a {@see SupplierStatementImport}
 * + its {@see SupplierStatementImportLine} rows. Read + state only: never touches
 * journal_entries/transactions.
 *
 * Column mapping is CONFIG (config('accounting.supplier_statements.dotw.columns'), L15) with a
 * per-import override — {@see SupplierStatementImportInput::$columnMapOverride}. Matching a
 * configured label to a header cell is exact (trimmed, case-insensitive) — no fuzzy/partial
 * matching, so a column map that does not match the real file's headers fails LOUD (missing
 * required column -> {@see SupplierStatementImportRejected}) rather than silently importing
 * garbage.
 *
 * Idempotency (spec: "re-importing the same statement is idempotent"; L13's "statement identity
 * = supplier + statement reference/period hash"): {@see contentHash()} computes a stable identity
 * for a (supplier, statement) pair — from the caller-supplied `statement_reference` when given,
 * else from a deterministic projection of the parsed rows (booking_ref, amount, currency,
 * statement_date, statement_line_reference, in file order) — and `import()` looks up an existing
 * row with that identity BEFORE inserting anything. A second import of the identical file (or the
 * same explicit reference) returns the EXISTING import untouched; it never re-parses into a
 * second set of lines. The migration's own UNIQUE (company_id, supplier_id, content_hash) index
 * is the DB-level backstop for the same guarantee under a race.
 */
final class SupplierStatementImporter
{
    /**
     * @throws SupplierStatementImportRejected
     */
    public function import(SupplierStatementImportInput $input): SupplierStatementImport
    {
        $columnMap = $this->resolveColumnMap($input->columnMapOverride);

        $grid = $this->readGrid($input->absoluteFilePath, $input->fileName);

        if (count($grid) === 0) {
            throw new SupplierStatementImportRejected("Statement file '{$input->fileName}' is empty.");
        }

        $header = array_shift($grid);
        $columnIndex = $this->resolveColumnIndex($header, $columnMap);

        $required = (array) config('accounting.supplier_statements.dotw.required_columns', ['booking_ref', 'amount', 'currency']);
        $missing = array_values(array_diff($required, array_keys($columnIndex)));
        if ($missing !== []) {
            throw new SupplierStatementImportRejected(
                "Statement file '{$input->fileName}' is missing required column(s): ".implode(', ', $missing)
                .'. Configured labels: '.json_encode($columnMap)
            );
        }

        $parsedRows = [];
        $rowNo = 0;
        foreach ($grid as $rawRow) {
            $rowNo++;
            if ($this->isBlankRow($rawRow)) {
                continue;
            }
            $parsedRows[] = $this->parseRow($rowNo, $rawRow, $columnIndex, $header);
        }

        if ($parsedRows === []) {
            throw new SupplierStatementImportRejected("Statement file '{$input->fileName}' has a header row but no data rows.");
        }

        $hash = $this->contentHash($input->supplierId, $input->statementReference, $parsedRows);

        $existing = SupplierStatementImport::withoutGlobalScopes()
            ->where('company_id', $input->companyId)
            ->where('supplier_id', $input->supplierId)
            ->where('content_hash', $hash)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($input, $columnMap, $parsedRows, $hash) {
            $import = SupplierStatementImport::create([
                'company_id' => $input->companyId,
                'supplier_id' => $input->supplierId,
                'file_name' => $input->fileName,
                'statement_currency' => strtoupper($input->statementCurrency),
                'period_from' => $input->periodFrom,
                'period_to' => $input->periodTo,
                'statement_reference' => $input->statementReference,
                'content_hash' => $hash,
                'column_map' => $columnMap,
                'status' => SupplierStatementImport::STATUS_STAGED,
                'imported_by' => $input->importedBy,
            ]);

            foreach ($parsedRows as $row) {
                SupplierStatementImportLine::create(array_merge($row, [
                    'import_id' => $import->id,
                    'currency' => strtoupper((string) $row['currency']),
                    'state' => SupplierStatementImportLine::STATE_UNMATCHED,
                ]));
            }

            return $import;
        });
    }

    /**
     * @return array<string,string>
     */
    private function resolveColumnMap(?array $override): array
    {
        $default = (array) config('accounting.supplier_statements.dotw.columns', []);

        return $override === null ? $default : array_merge($default, array_filter($override, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * @return array<int, array<int, mixed>> a raw 2D grid, header row included as row 0.
     */
    private function readGrid(string $absoluteFilePath, string $fileName): array
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->readCsv($absoluteFilePath);
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheets = Excel::toArray(new RawGridImport, $absoluteFilePath);

            return $sheets[0] ?? [];
        }

        throw new SupplierStatementImportRejected(
            "Unsupported statement file type '.{$extension}' for '{$fileName}' — only .csv and .xlsx/.xls are accepted."
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $absoluteFilePath): array
    {
        if (! is_readable($absoluteFilePath)) {
            throw new SupplierStatementImportRejected("Statement file could not be read at '{$absoluteFilePath}'.");
        }

        $rows = [];
        $handle = fopen($absoluteFilePath, 'r');
        if ($handle === false) {
            throw new SupplierStatementImportRejected("Statement file could not be opened at '{$absoluteFilePath}'.");
        }

        // Strip a UTF-8 BOM on the first line, if present — a common Excel-exported-CSV artifact
        // that would otherwise corrupt the FIRST header cell's match.
        $first = true;
        while (($line = fgetcsv($handle)) !== false) {
            if ($first) {
                $first = false;
                if (isset($line[0])) {
                    $line[0] = preg_replace('/^\x{FEFF}/u', '', (string) $line[0]) ?? $line[0];
                }
            }
            $rows[] = $line;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $header
     * @param  array<string,string>  $columnMap  semantic key => configured label
     * @return array<string,int> semantic key => column index
     */
    private function resolveColumnIndex(array $header, array $columnMap): array
    {
        $normalizedHeader = [];
        foreach ($header as $i => $cell) {
            $normalizedHeader[$this->normalizeLabel((string) $cell)] = $i;
        }

        $index = [];
        foreach ($columnMap as $key => $label) {
            $normalizedLabel = $this->normalizeLabel($label);
            if (array_key_exists($normalizedLabel, $normalizedHeader)) {
                $index[$key] = $normalizedHeader[$normalizedLabel];
            }
        }

        return $index;
    }

    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int,mixed>  $rawRow
     * @param  array<string,int>  $columnIndex
     * @param  array<int,mixed>  $header
     * @return array<string,mixed>
     */
    private function parseRow(int $rowNo, array $rawRow, array $columnIndex, array $header): array
    {
        $cell = function (string $key) use ($rawRow, $columnIndex): ?string {
            if (! isset($columnIndex[$key])) {
                return null;
            }
            $value = $rawRow[$columnIndex[$key]] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            return $value === null || $value === '' ? null : (string) $value;
        };

        $raw = [];
        foreach ($header as $i => $label) {
            $raw[(string) $label] = $rawRow[$i] ?? null;
        }

        return [
            'row_no' => $rowNo,
            'booking_ref' => $cell('booking_ref'),
            'confirmation_code' => $cell('confirmation_code'),
            'guest' => $cell('guest'),
            'checkin' => $this->parseDate($cell('checkin')),
            'checkout' => $this->parseDate($cell('checkout')),
            'amount' => $this->parseAmount($cell('amount')),
            'currency' => $cell('currency') ?? '',
            'statement_date' => $this->parseDate($cell('statement_date')),
            'statement_line_reference' => $cell('statement_line_reference'),
            'description' => $cell('description'),
            'raw' => $raw,
        ];
    }

    private function parseAmount(?string $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $normalized = str_replace([',', ' '], '', $value);

        return round((float) $normalized, 3);
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string,mixed>>  $parsedRows
     */
    private function contentHash(int $supplierId, ?string $statementReference, array $parsedRows): string
    {
        if ($statementReference !== null && trim($statementReference) !== '') {
            return hash('sha256', $supplierId.'|ref|'.trim($statementReference));
        }

        $projection = array_map(
            fn (array $row) => [
                $row['booking_ref'],
                $row['amount'],
                $row['currency'],
                $row['statement_date'],
                $row['statement_line_reference'],
            ],
            $parsedRows
        );

        return hash('sha256', $supplierId.'|rows|'.json_encode($projection));
    }
}
