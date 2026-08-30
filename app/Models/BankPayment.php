<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The durable Payment Voucher document row (w5-brief.md §W5.P). `transaction_id` is the posted
 * engine document once approved -- NULL while the voucher is still `pending` (drafted, not yet
 * posted; mirrors InvoiceReceipt's own RV lifecycle, W5.R). See migration
 * 2026_08_29_110000_w5p_create_bank_payments_table.php for the full column-by-column rationale.
 */
class BankPayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'company_id',
        'branch_id',
        'doc_date',
        'sub_type',
        'pay_from_account_id',
        'target_account_id',
        'agent_id',
        'amount',
        'bank_charge_amount',
        'cheque_no',
        'cheque_date',
        'cheque_clearance_date',
        'cheque_image_path',
        'bank_info',
        'auth_no',
        'reconcile_journal_entry_ids',
        'voucher_number',
        'reference_ref',
        'remarks',
        'remarks_internal',
        'remarks_fl',
        'status',
        'transaction_id',
        'created_by',
    ];

    protected $casts = [
        'doc_date' => 'date',
        'cheque_date' => 'date',
        'cheque_clearance_date' => 'date',
        'reconcile_journal_entry_ids' => 'array',
        'amount' => 'decimal:3',
        'bank_charge_amount' => 'decimal:3',
    ];

    public function payFromAccount()
    {
        return $this->belongsTo(Account::class, 'pay_from_account_id');
    }

    public function targetAccount()
    {
        return $this->belongsTo(Account::class, 'target_account_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
