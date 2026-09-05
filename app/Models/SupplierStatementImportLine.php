<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * accounting-builds T8 (Lane E), migration M5. One row per parsed statement line. `state` is set
 * by {@see \App\Services\Accounting\Reconciliation\SupplierStatementMatcher} — never flips
 * `journal_entries.reconciled` itself (that only happens through the existing
 * {@see \App\Services\Accounting\ReconciliationProposalService::approve()} pipeline once an
 * owner/operator approves the proposal the matcher created for a 'matched' line).
 *
 * No `company_id` column on this table by design (unlike most accounting-builds tables) — a line
 * only ever exists in the context of its parent import, which is itself company-scoped
 * ({@see SupplierStatementImport}); every query in this task reaches lines via
 * `$import->lines()`, never a bare `SupplierStatementImportLine::query()` company filter.
 */
class SupplierStatementImportLine extends Model
{
    public const STATE_UNMATCHED = 'unmatched';

    public const STATE_MATCHED = 'matched';

    public const STATE_DISPUTED = 'disputed';

    // Reserved (migration-shape parity), unused this task — see the migration's own docblock.
    public const STATE_SUGGESTED = 'suggested';

    protected $fillable = [
        'import_id',
        'row_no',
        'booking_ref',
        'confirmation_code',
        'guest',
        'checkin',
        'checkout',
        'amount',
        'currency',
        'statement_date',
        'statement_line_reference',
        'description',
        'state',
        'matched_journal_entry_id',
        'matched_journal_entry_ids',
        'matched_task_id',
        'difference',
        'note',
        'raw',
    ];

    protected $casts = [
        'import_id' => 'integer',
        'row_no' => 'integer',
        'checkin' => 'date',
        'checkout' => 'date',
        'statement_date' => 'date',
        'amount' => 'float',
        'difference' => 'float',
        'matched_journal_entry_id' => 'integer',
        // RV-1: every payable line this statement row consumed (aggregate matches cover more
        // than the one id `matched_journal_entry_id` can hold) — see migration ...000006.
        'matched_journal_entry_ids' => 'array',
        'matched_task_id' => 'integer',
        'raw' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(SupplierStatementImport::class, 'import_id');
    }

    public function matchedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'matched_journal_entry_id');
    }
}
