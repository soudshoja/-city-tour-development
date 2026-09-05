<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T9 (Wave 2). Thrown by {@see BankStatementImporter} when a file cannot be
 * imported at all — unsupported extension, unreadable/empty file, a required column
 * (config('accounting.bank_statements.columns')) missing from the header row, an unknown/
 * cross-company bank account, or a statement currency that does not match the bank leaf's own
 * currency. Never thrown for a per-row problem (a bad row is skipped and counted, not fatal).
 */
final class BankStatementImportRejected extends \RuntimeException {}
