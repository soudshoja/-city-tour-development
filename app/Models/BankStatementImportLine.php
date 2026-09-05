<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * accounting-builds T9 (Wave 2), migration M6. One row per parsed statement line. `state` is set
 * by {@see \App\Services\Accounting\Reconciliation\BankStatementMatcher} — never flips
 * `journal_entries.reconciled` itself (that only happens through the existing
 * {@see \App\Services\Accounting\ReconciliationProposalService::approve()} pipeline once a
 * proposal is approved — ArchitectureTest::test_no_post_hoc_reconciled_updates()).
 *
 * No `company_id` column on this table by design (same convention as
 * `supplier_statement_import_lines`, T8) — a line only exists in the context of its parent import,
 * which is itself company- and bank-leaf-scoped ({@see BankStatementImport}); every query in this
 * task reaches lines via `$import->lines()`, never a bare `BankStatementImportLine::query()`
 * company filter.
 */
class BankStatementImportLine extends Model
{
    public const STATE_UNMATCHED = 'unmatched';

    public const STATE_MATCHED = 'matched';

    public const STATE_DISPUTED = 'disputed';

    // Reserved (migration-shape parity with T8), unused by the matcher this task.
    public const STATE_SUGGESTED = 'suggested';

    protected $fillable = [
        'import_id',
        'row_no',
        'value_date',
        'posting_date',
        'description',
        'reference',
        'auth_no',
        'cheque_no',
        'debit',
        'credit',
        'running_balance',
        'state',
        'matched_journal_entry_id',
        'difference',
        'note',
        'raw',
    ];

    protected $casts = [
        'import_id' => 'integer',
        'row_no' => 'integer',
        'value_date' => 'date',
        'posting_date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
        'running_balance' => 'float',
        'matched_journal_entry_id' => 'integer',
        'difference' => 'float',
        'raw' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'import_id');
    }

    public function matchedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'matched_journal_entry_id');
    }

    /** Signed net movement this statement row represents (debit - credit), matching the ledger's own sign convention. */
    public function amount(): float
    {
        return round((float) $this->debit - (float) $this->credit, 3);
    }
}
