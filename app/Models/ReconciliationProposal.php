<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §6/§9): one auto-match candidate (or
 * manual match) for one Reconciliation Center row. See the creating migration's own docblock for
 * the full column rationale.
 */
class ReconciliationProposal extends Model
{
    public const KIND_RECEIPT_INVOICE_CONSISTENCY = 'receipt_invoice_consistency';

    public const KIND_SUB_LEDGER_VS_CONTROL = 'sub_ledger_vs_control';

    public const KIND_CLEARING_ROLLFORWARD = 'clearing_rollforward';

    public const KIND_MANUAL = 'manual';

    public const CONFIDENCE_EXACT = 'exact';

    public const CONFIDENCE_TOLERANCE = 'tolerance';

    public const CONFIDENCE_SUGGESTED = 'suggested';

    public const CONFIDENCE_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'run_id',
        'account_id',
        'source',
        'kind',
        'confidence',
        'book_journal_entry_id',
        'matched_journal_entry_id',
        'matched_reference',
        'amount',
        'difference_amount',
        'status',
        'reason',
        'decided_by',
        'decided_at',
        'period_year',
        'period_month',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'run_id' => 'integer',
        'account_id' => 'integer',
        'book_journal_entry_id' => 'integer',
        'matched_journal_entry_id' => 'integer',
        'amount' => 'float',
        'difference_amount' => 'float',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
        'period_year' => 'integer',
        'period_month' => 'integer',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function bookJournalEntry(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'book_journal_entry_id');
    }

    public function matchedJournalEntry(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'matched_journal_entry_id');
    }

    public function decidedByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
