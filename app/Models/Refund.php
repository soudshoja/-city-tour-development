<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'refund_number',
        'company_id',
        'branch_id',
        'agent_id',
        'account_id',
        'method',
        'reference',
        'remarks',
        'remarks_internal',
        'reason',
        'total_refund_amount', // Sum of total_refund_to_client from refund_details
        'total_refund_charge', // Sum of refund_fee_to_client + refund_task_supplier_charge from refund_details
        'total_net_refund',    // Calculated: total_refund_amount - total_refund_charge
        'status',
        'refund_date',
        'created_by',
        'updated_by',
    ];

    public function refundDetails()
    {
        return $this->hasMany(RefundDetail::class, 'refund_id');
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

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function getTotalRefundAmountAttribute()
    {
        return $this->refundDetails()->sum('total_refund_to_client');
    }

    public function getTotalRefundChargeAttribute()
    {
        return $this->refundDetails()->sum('refund_fee_to_client');
    }

    public function getTotalNetRefundAttribute()
    {
        return $this->refundDetails()->sum('net_refund');
    }

    public function formattedStatus(): string
    {
        return ucfirst($this->status ?? 'pending');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
