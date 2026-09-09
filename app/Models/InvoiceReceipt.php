<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The durable Receipt Voucher document row (w5-brief.md §W5.R). `transaction_id` is the posted
 * engine document once approved -- NULL while the voucher is still `pending` (drafted, not yet
 * posted; see ReceiptVoucherController's own docblock for why the engine cannot represent
 * "reserved but unposted" any other way). See migration
 * 2026_08_29_100000_w5r_receipt_voucher_document_columns.php for the full column-by-column
 * rationale.
 */
class InvoiceReceipt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'type',
        'sub_type',
        'voucher_number',
        'company_id',
        'branch_id',
        'doc_date',
        'invoice_id',
        'invoice_partial_id',
        'account_id',
        'client_id',
        'task_id',
        'credit_id',
        'transaction_id',
        'amount',
        'status',
        'is_used',
        'bank_account_id',
        // CT-A3 wave 2 (W2-2): WHICH payment method / gateway this money came in through.
        // ReceiptPostingRule resolves the instrument leg's bank account from the matching
        // `charges.acc_bank_id`, so the account itself is never copied onto the receipt.
        'settlement_channel',
        'cheque_no',
        'cheque_date',
        'cheque_clearance_date',
        'cheque_image_path',
        'bank_info',
        'auth_no',
        'allocations',
        'remainder_amount',
        'remainder_policy',
        'remarks',
        'remarks_internal',
        'applied_at',
        'applied_transaction_id',
    ];

    protected $casts = [
        'doc_date' => 'date',
        'cheque_date' => 'date',
        'cheque_clearance_date' => 'date',
        'allocations' => 'array',
        'amount' => 'decimal:3',
        'remainder_amount' => 'decimal:3',
        'is_used' => 'boolean',
        'applied_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * W6.S fix round -- the task this receipt is a deposit against (on hold/confirmed lifecycle),
     * when this row is that kind of receipt. Null for every other receipt shape.
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * W6.U2 fix -- the `deposit_apply:{task_id}` JV that CONSUMED this deposit row (see
     * `applied_at`'s own migration docblock). Null until {@see \App\Services\TaskStatusService::
     * applyHoldDepositToInvoice()} applies it.
     */
    public function appliedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'applied_transaction_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * A time-limited, unauthenticated link to this receipt voucher's public-facing view -- e.g.
     * for sharing over WhatsApp/email, or for the "View Receipt" link embedded in an invoice's
     * own public page (see resources/views/invoice/show.blade.php). Reachable ONLY via the
     * signed 'receipt-voucher.show.public' route (routes/web.php), which validates the signature
     * (and therefore companyId/voucherNumber, plus expiry) before ReceiptVoucherController::
     * show() ever runs -- mirrors Invoice::publicUrl()/Refund::publicUrl() exactly, including
     * reuse of the SAME app.invoice_link_ttl_minutes config key rather than a second, near-
     * identical TTL env var for a sibling client-facing document link.
     *
     * Keyed on the TRANSACTION's reference_number, not this row's own `voucher_number` column --
     * `voucher_number` is stamped once as the `RV-DRAFT-{id}` placeholder at creation and never
     * updated (see ReceiptVoucherController::createReceiptVoucher()); the real, sequence-assigned
     * voucher number only ever lives on `transaction.reference_number`, which is exactly what
     * show()'s own lookup is keyed on. Callers must ensure `transaction` is loaded/loadable and
     * the voucher has actually been posted (a still-pending voucher has no transaction yet).
     */
    public function publicUrl(): ?string
    {
        $referenceNumber = $this->transaction?->reference_number;

        if ($referenceNumber === null || $this->company_id === null) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'receipt-voucher.show.public',
            now()->addMinutes((int) config('app.invoice_link_ttl_minutes', 60 * 24 * 7)),
            ['companyId' => $this->company_id, 'voucherNumber' => $referenceNumber]
        );
    }
}
