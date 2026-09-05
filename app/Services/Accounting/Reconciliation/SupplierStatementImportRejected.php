<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T8 (Lane E). Thrown by {@see SupplierStatementImporter} when a file cannot
 * be imported at all — unsupported extension, unreadable/empty file, or a required column
 * (config('accounting.supplier_statements.dotw.required_columns')) missing from the header row.
 * Never thrown for a per-row problem (a bad row is skipped and counted, not fatal).
 */
final class SupplierStatementImportRejected extends \RuntimeException {}
