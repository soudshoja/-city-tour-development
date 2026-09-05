<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * accounting-builds T9 (Wave 2). Parses a bank statement (CSV or XLSX, L15 — reuses
 * `maatwebsite/excel`, already a dependency per T8's own verification) into a
 * {@see BankStatementImport} + its {@see BankStatementImportLine} rows. Read + state only — never
 * touches journal_entries/transactions. Structurally mirrors T8's SupplierStatementImporter (same
 * CSV/XLSX read path, same column-map resolution, same {@see RawGridImport} marker class for
 * `Excel::toArray()`), with three T9-specific additions:
 *
 *   1. **Bank-leaf scoping at the import boundary** (spec: "a KWD bank statement never matches a
 *      USD bank leaf's lines"): `bank_account_id` must resolve to a real, company-owned account,
 *      and `statement_currency` must equal that leaf's own `accounts.currency` (falling back to
 *      `config('accounting.engine.base_currency')` for a leaf recorded with no currency, i.e. a
 *      base-currency KWD leaf) — refused up front, never a silent cross-currency import. This is
 *      what makes the currency invariant structural rather than a runtime filter: every candidate
 *      query in {@see BankStatementMatcher} is scoped to THIS leaf's `account_id` alone.
 *   2. **Content-hash identity, not reference-derived** (see the creating migration's own
 *      docblock): `content_hash` is ALWAYS a hash of the parsed row content, independent of any
 *      caller-supplied `statement_reference`. `import()` resolves identity in two steps — first by
 *      (company, bank leaf, statement_reference) when a reference is given, THEN by content hash —
 *      so a re-import under the SAME reference with DIFFERENT content is a raised
 *      {@see BankStatementImportConflict}, not a silent keep.
 *   3. **Statement debit/credit polarity** (a bank statement is written from the BANK's point of
 *      view — its "Credit" column is money paid INTO the customer's account, its "Debit" column is
 *      money paid OUT — the OPPOSITE of how the same movement lands in OUR OWN books, where the
 *      bank leaf is an asset: money coming in is a book DEBIT, money going out is a book CREDIT).
 *      This class stores the statement's own debit/credit columns verbatim (so the file's own
 *      figures are never silently rewritten) — {@see BankStatementMatcher} is the one place that
 *      translates statement polarity to ledger polarity for comparison; see that class's own
 *      docblock.
 */
final class BankStatementImporter
{
    /**
     * @throws BankStatementImportRejected
     * @throws BankStatementImportConflict
     */
    public function import(BankStatementImportInput $input): BankStatementImport
    {
        $account = Account::withoutGlobalScopes()->find($input->bankAccountId);
        if ($account === null || (int) $account->company_id !== $input->companyId) {
            throw new BankStatementImportRejected("Bank account #{$input->bankAccountId} does not belong to this company.");
        }

        $leafCurrency = strtoupper((string) ($account->currency ?: config('accounting.engine.base_currency', 'KWD')));
        if (strtoupper($input->statementCurrency) !== $leafCurrency) {
            throw new BankStatementImportRejected(
                "Statement currency '{$input->statementCurrency}' does not match bank account '{$account->name}''s currency '{$leafCurrency}' — a statement can never be imported against a bank leaf in a different currency."
            );
        }

        $columnMap = $this->resolveColumnMap($input->columnMapOverride);
        $dateFormat = $input->dateFormatOverride ?? (string) config('accounting.bank_statements.date_format', 'd/m/Y');
        $dateFormatFallbacks = array_values(array_unique(array_filter(
            (array) config('accounting.bank_statements.date_format_fallbacks', ['Y-m-d', 'd-m-Y', 'd/m/Y H:i']),
            fn ($f) => $f !== $dateFormat
        )));
        $grid = $this->readGrid($input->absoluteFilePath, $input->fileName);

        if (count($grid) === 0) {
            throw new BankStatementImportRejected("Statement file '{$input->fileName}' is empty.");
        }

        $header = array_shift($grid);
        $columnIndex = $this->resolveColumnIndex($header, $columnMap);

        $required = (array) config('accounting.bank_statements.required_columns', ['value_date', 'debit', 'credit']);
        $missing = array_values(array_diff($required, array_keys($columnIndex)));
        if ($missing !== []) {
            throw new BankStatementImportRejected(
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
            $parsedRows[] = $this->parseRow($rowNo, $rawRow, $columnIndex, $header, $dateFormat, $dateFormatFallbacks);
        }

        if ($parsedRows === []) {
            throw new BankStatementImportRejected("Statement file '{$input->fileName}' has a header row but no data rows.");
        }

        $rowHash = $this->contentHash($input->bankAccountId, $parsedRows);

        // Step 1: an explicit reference identifies a statement independent of its content — if
        // this reference was seen before for this leaf, the file MUST match what was stored under
        // it, or this is a conflict (spec: "changed re-import under same reference = conflict").
        if ($input->statementReference !== null && trim($input->statementReference) !== '') {
            $byReference = BankStatementImport::withoutGlobalScopes()
                ->where('company_id', $input->companyId)
                ->where('bank_account_id', $input->bankAccountId)
                ->where('statement_reference', trim($input->statementReference))
                ->first();

            if ($byReference !== null) {
                if ($byReference->content_hash === $rowHash) {
                    return $byReference;
                }

                throw new BankStatementImportConflict(
                    "Statement reference '{$input->statementReference}' was already imported for this bank account with DIFFERENT content (import #{$byReference->id}). ".
                    'Re-import under a corrected reference, or reconcile the discrepancy manually before retrying.'
                );
            }
        }

        // Step 2: content-hash identity — a byte-identical (or row-identical) file re-imported,
        // with or without a reference, is idempotent.
        $byContent = BankStatementImport::withoutGlobalScopes()
            ->where('company_id', $input->companyId)
            ->where('bank_account_id', $input->bankAccountId)
            ->where('content_hash', $rowHash)
            ->first();

        if ($byContent !== null) {
            return $byContent;
        }

        return DB::transaction(function () use ($input, $columnMap, $parsedRows, $rowHash) {
            $lastRunningBalance = null;
            foreach (array_reverse($parsedRows) as $row) {
                if ($row['running_balance'] !== null) {
                    $lastRunningBalance = $row['running_balance'];
                    break;
                }
            }

            $import = BankStatementImport::create([
                'company_id' => $input->companyId,
                'bank_account_id' => $input->bankAccountId,
                'file_name' => $input->fileName,
                'statement_currency' => strtoupper($input->statementCurrency),
                'statement_from' => $input->statementFrom,
                'statement_to' => $input->statementTo,
                'opening_balance' => $input->openingBalance,
                'closing_balance' => $input->closingBalance ?? $lastRunningBalance,
                'statement_reference' => $input->statementReference,
                'content_hash' => $rowHash,
                'column_map' => $columnMap,
                'status' => BankStatementImport::STATUS_STAGED,
                'imported_by' => $input->importedBy,
            ]);

            foreach ($parsedRows as $row) {
                BankStatementImportLine::create(array_merge($row, [
                    'import_id' => $import->id,
                    'state' => BankStatementImportLine::STATE_UNMATCHED,
                ]));
            }

            return $import;
        });
    }

    /** @return array<string,string> */
    private function resolveColumnMap(?array $override): array
    {
        $default = (array) config('accounting.bank_statements.columns', []);

        return $override === null ? $default : array_merge($default, array_filter($override, fn ($v) => $v !== null && $v !== ''));
    }

    /** @return array<int, array<int, mixed>> a raw 2D grid, header row included as row 0. */
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

        throw new BankStatementImportRejected(
            "Unsupported statement file type '.{$extension}' for '{$fileName}' — only .csv and .xlsx/.xls are accepted."
        );
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $absoluteFilePath): array
    {
        if (! is_readable($absoluteFilePath)) {
            throw new BankStatementImportRejected("Statement file could not be read at '{$absoluteFilePath}'.");
        }

        $rows = [];
        $handle = fopen($absoluteFilePath, 'r');
        if ($handle === false) {
            throw new BankStatementImportRejected("Statement file could not be opened at '{$absoluteFilePath}'.");
        }

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
     * @param  array<string,string>  $columnMap
     * @return array<string,int>
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
     * @param  array<int,string>  $dateFormatFallbacks
     * @return array<string,mixed>
     */
    private function parseRow(int $rowNo, array $rawRow, array $columnIndex, array $header, string $dateFormat, array $dateFormatFallbacks): array
    {
        $cell = function (string $key) use ($rawRow, $columnIndex): ?string {
            if (! isset($columnIndex[$key])) {
                return null;
            }
            $value = $rawRow[$columnIndex[$key]] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            return $value === null || $value === '' ? null : (string) $value;
        };

        // Un-stringified — value_date/posting_date need to know whether the raw grid cell was a
        // genuine XLSX numeric (Excel serial date) BEFORE $cell() casts everything to string.
        $rawCell = function (string $key) use ($rawRow, $columnIndex): mixed {
            if (! isset($columnIndex[$key])) {
                return null;
            }

            return $rawRow[$columnIndex[$key]] ?? null;
        };

        $raw = [];
        foreach ($header as $i => $label) {
            $raw[(string) $label] = $rawRow[$i] ?? null;
        }

        $valueDate = $this->resolveDateCell($rawCell('value_date'), 'value_date', $rowNo, $dateFormat, $dateFormatFallbacks)
            ?? $this->resolveDateCell($rawCell('posting_date'), 'posting_date', $rowNo, $dateFormat, $dateFormatFallbacks);

        return [
            'row_no' => $rowNo,
            'value_date' => $valueDate,
            'posting_date' => $this->resolveDateCell($rawCell('posting_date'), 'posting_date', $rowNo, $dateFormat, $dateFormatFallbacks),
            'description' => $cell('description'),
            'reference' => $cell('reference'),
            'auth_no' => $cell('auth_no'),
            'cheque_no' => $cell('cheque_no'),
            'debit' => $this->parseAmount($cell('debit')),
            'credit' => $this->parseAmount($cell('credit')),
            'running_balance' => $cell('running_balance') !== null ? $this->parseAmount($cell('running_balance')) : null,
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

    /**
     * Post-sign-off fix (T9 §12 note 2): resolves ONE date cell to a `Y-m-d` string, or throws.
     * Two paths:
     *
     *   - **XLSX numeric cell** (a genuine Excel date, read as a raw int/float serial by
     *     {@see RawGridImport} — `Excel::toArray()` never string-formats a date-typed cell on its
     *     own): converted via PhpSpreadsheet's own `Shared\Date`, independent of `$format`/
     *     `$fallbacks` entirely — the file's own date typing is authoritative here, there is no
     *     "wrong format" for a real Excel date serial.
     *   - **Everything else (CSV string, or an XLSX text-typed cell)**: tried strictly against
     *     `$format`, then each of `$fallbacks` in order — `DateTime::createFromFormat('!'.$fmt,
     *     ...)` (the leading `!` resets every field the format doesn't mention, so a bare `Y-m-d`
     *     match never bleeds in today's time-of-day) with `DateTime::getLastErrors()` checked, so
     *     `31/02/2026` (no such day) or a component landing out of range is rejected rather than
     *     silently rolled over into March — first strict match wins.
     *
     * A raw value that is present but matches NEITHER path throws {@see BankStatementImportRejected}
     * naming the row, the column, the offending value and the formats tried — this is the ONLY
     * per-row problem this importer rejects on (every other blank/absent cell is tolerated), and it
     * aborts the whole import (never a partial import, never a 500 — the existing whole-file
     * rejection policy this class already applies to a missing required column or an unreadable
     * file). An absent/blank cell is not an error here — the caller decides whether that's fatal
     * (value_date has no column to fall back to further; posting_date is optional).
     */
    private function resolveDateCell(mixed $raw, string $columnKey, int $rowNo, string $format, array $fallbacks): ?string
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (\Throwable) {
                throw new BankStatementImportRejected(
                    "Statement row {$rowNo}: {$columnKey} value '{$raw}' is not a valid Excel date serial."
                );
            }
        }

        $value = trim((string) $raw);

        foreach ([$format, ...$fallbacks] as $fmt) {
            $candidate = \DateTime::createFromFormat('!'.$fmt, $value);
            $errors = \DateTime::getLastErrors();
            $clean = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
            if ($candidate !== false && $clean) {
                return $candidate->format('Y-m-d');
            }
        }

        throw new BankStatementImportRejected(
            "Statement row {$rowNo}: {$columnKey} value '{$value}' does not match the configured date format ".
            "'{$format}' or its fallback(s) (".implode(', ', $fallbacks).'). Fix the file, or set an explicit date_format for this import.'
        );
    }

    /**
     * @param  array<int, array<string,mixed>>  $parsedRows
     */
    private function contentHash(int $bankAccountId, array $parsedRows): string
    {
        $projection = array_map(
            fn (array $row) => [
                $row['value_date'], $row['description'], $row['reference'],
                $row['auth_no'], $row['cheque_no'], $row['debit'], $row['credit'],
            ],
            $parsedRows
        );

        return hash('sha256', $bankAccountId.'|rows|'.json_encode($projection));
    }
}
