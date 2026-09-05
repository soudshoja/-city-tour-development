<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T9 (Wave 2). Thrown by {@see BankStatementImporter} when a file cannot be
 * imported at all — unsupported extension, unreadable/empty file, a required column
 * (config('accounting.bank_statements.columns')) missing from the header row, an unknown/
 * cross-company bank account, or a statement currency that does not match the bank leaf's own
 * currency.
 *
 * Post-sign-off fix (T9 §12 note 2): also thrown for the ONE per-row problem this importer
 * rejects on — a value_date/posting_date cell that has content but matches neither the configured
 * `date_format` nor any of its `date_format_fallbacks` (see
 * {@see BankStatementImporter::resolveDateCell()}). This aborts the whole import rather than
 * skipping the one bad row: consistent with every other rejection this class already throws for
 * (a missing column, an unreadable file), and — critically — the alternative (a NULL `value_date`
 * reaching the insert) is what used to surface as an uncaught `QueryException`/HTTP 500 instead of
 * this class's own controlled 422.
 */
final class BankStatementImportRejected extends \RuntimeException {}
