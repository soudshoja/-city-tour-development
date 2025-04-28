<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'refund_number',
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
    ];
    
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id'); // or Agent model if separate
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
