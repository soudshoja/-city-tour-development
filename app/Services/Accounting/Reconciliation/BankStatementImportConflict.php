<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T9 (Wave 2). Thrown by {@see BankStatementImporter::import()} when the SAME
 * `statement_reference` is re-submitted for the SAME bank leaf with DIFFERENT row content — this
 * task's explicit "changed re-import under same reference = conflict/warn" requirement, a
 * deliberate closing of the gap T8's own review packet (§9 advisory) left open for supplier
 * statements ("silently keeps the first import when a same-reference file's content differs, with
 * no warning surfaced"). Never thrown for an identical re-import (same reference AND same content
 * — that returns the existing {@see \App\Models\BankStatementImport} untouched, per spec
 * "idempotent re-import").
 */
final class BankStatementImportConflict extends \RuntimeException {}
