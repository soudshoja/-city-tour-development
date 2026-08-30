<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): a FIX-NOW draft correcting document. See the creating
 * migration's own docblock and {@see \App\Services\Accounting\ReconciliationFixDraftService}.
 */
class ReconciliationFixDraft extends Model
{
    public const KIND_BANK_CHARGE_PV = 'bank_charge_pv';

    public const KIND_GATEWAY_TIMING_JV = 'gateway_timing_jv';

    public const KIND_UNAPPLY_REAPPLY_RECEIPT = 'unapply_reapply_receipt';

    public const KIND_WRITEOFF_PROPOSAL = 'writeoff_proposal';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'company_id',
        'proposal_id',
        'account_id',
        'branch_id',
        'kind',
        'doc_type',
        'amount',
        'narration',
        'target_purpose_code',
        'target_account_code',
        'status',
        'transaction_id',
        'created_by',
        'posted_by',
        'posted_at',
        'reason',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'proposal_id' => 'integer',
        'account_id' => 'integer',
        'branch_id' => 'integer',
        'amount' => 'float',
        'transaction_id' => 'integer',
        'created_by' => 'integer',
        'posted_by' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function isPostable(): bool
    {
        // v0 scope: un-apply/re-apply of a receipt is a PaymentApplicationService operation, not
        // a balanced ledger posting this service can build generically -- the draft record still
        // exists (satisfies "fix-now creates a DRAFT" for every kind) but posting it here would
        // require a specific PaymentApplication id this generic draft does not carry. Deferred:
        // an operator actions this kind from the Receipt Voucher screen's own unapply flow instead.
        return $this->kind !== self::KIND_UNAPPLY_REAPPLY_RECEIPT;
    }
}
