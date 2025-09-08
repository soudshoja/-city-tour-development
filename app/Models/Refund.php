<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'refund_number',
        'task_id',
        'invoice_id',
        'company_id',
        'branch_id',
        'agent_id',
        'remarks',
        'remarks_internal',
        'airline_nett_fare',
        'tax_refund',
        'refund_airline_charge',
        'original_task_profit',
        'new_task_profit',
        'total_nett_refund',
        'service_charge',
        'reason',
        'method',
        'account_id',
        'date',
        'reference',
        'status',
        'created_by',
        'updated_at',
    ];
    
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
    
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
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
        return $this->belongsTo(Agent::class, 'agent_id'); // or Agent model if separate
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function getOriginalInvoiceAttribute()
    {
        return $this->task?->originalTask?->invoiceDetail?->invoice;
    }
}
