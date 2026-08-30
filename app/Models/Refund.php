<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use SoftDeletes;

    /**
     * W4.R (w4-brief.md §4) — real status workflow: draft -> approved -> posted ->
     * completed | rejected. See migration 2026_08_28_140000_w4r_refund_document_columns.php's
     * docblock for why `status` was widened from its original 5-value ENUM to a plain string.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_POSTED = 'posted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    /** w4-brief.md §4f disposition values. */
    public const DISPOSITION_CREDIT = 'credit';

    public const DISPOSITION_REFUND_OUT = 'refund_out';

    public const DISPOSITION_APPLY = 'apply';

    protected $fillable = [
        'refund_number',
        'company_id',
        'branch_id',
        'agent_id',
        'invoice_id',
        'refund_invoice_id',
        'refund_batch_id',
        'method',
        'disposition',
        'applied_invoice_id',
        'remarks',
        'remarks_internal',
        'reason',
        'total_refund_amount',
        'total_refund_charge',
        'total_nett_refund',
        'airline_clawback_amount',
        'gateway_refund_id',
        'status',
        'refund_date',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'completed_by',
        'completed_at',
        'rejected_by',
        'rejected_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'refund_date' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'total_refund_amount' => 'float',
        'total_refund_charge' => 'float',
        'total_nett_refund' => 'float',
        'airline_clawback_amount' => 'float',
    ];

    public function refundDetails()
    {
        return $this->hasMany(RefundDetail::class, 'refund_id', 'id');
    }

    public function originalInvoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'refund_invoice_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function formattedStatus(): string
    {
        return ucfirst($this->status ?? 'pending');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function appliedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'applied_invoice_id');
    }

    /**
     * W4.R bundled fix (w4-brief.md §5 "refunds.show route -> auth OR signed URL (mirror
     * Invoice::publicUrl() pattern + TTL env)"). Deliberately reuses the SAME
     * `app.invoice_link_ttl_minutes` config key as {@see Invoice::publicUrl()} rather than
     * introducing a second, near-identical TTL env var for a sibling client-facing document link.
     */
    public function publicUrl(): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'refunds.show.public',
            now()->addMinutes((int) config('app.invoice_link_ttl_minutes', 60 * 24 * 7)),
            ['companyId' => $this->company_id, 'refundNumber' => $this->refund_number]
        );
    }
}
