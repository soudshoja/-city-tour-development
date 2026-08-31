<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSV formula-injection hardening (SEC-1, .planning/accounting-waves/p2_5/p2_5-followups.md).
 *
 * Excel/Sheets/LibreOffice treat a cell whose first character is one of `= + - @` (and, in some
 * parsers, a leading tab or carriage return) as a formula, not literal text — a stored value like
 * `=HYPERLINK("http://evil","click")` or `=cmd()` re-opened from an exported CSV EXECUTES in the
 * victim's spreadsheet app, it does not just display. Every accounting CSV export writer must
 * route every cell it writes through {@see self::cell()} (or {@see self::row()} for a whole row)
 * before fputcsv() — this is an export-ENCODING concern only: the on-screen Log Center table, and
 * the stored `accounting_audit_log` row itself, are both untouched. Only the bytes written to the
 * CSV file change.
 *
 * OWASP CSV Injection guidance: prefix a dangerous leading character with a single quote so
 * spreadsheet apps render the cell as literal text instead of evaluating it as a formula.
 *
 * Current callers: {@see \App\Http\Livewire\Accounting\AuditLogIndex::exportCsv()} (the Log
 * Center's on-demand CSV download) and {@see \App\Console\Commands\AccountingAuditLogPurge}'s
 * archive writer (the retention-purge CSV written to storage before rows are deleted). Any future
 * accounting CSV export (Reconciliation Center, statement CSV, etc.) must route through this same
 * helper rather than growing its own ad-hoc escaping.
 */
final class CsvSafe
{
    /** First-character triggers a spreadsheet formula parser will act on. */
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Neutralize one CSV cell. Only strings are inspected — ints, floats, bools, null, and
     * Carbon/DateTime instances pass through unchanged (fputcsv() stringifies them itself), which
     * matches the SEC-1 finding's own acceptance test: "numeric cells untouched".
     */
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }

    /**
     * Neutralize every value in a row array, preserving keys — the shape a writer normally has in
     * hand right before calling fputcsv().
     *
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
