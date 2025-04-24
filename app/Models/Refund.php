<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'invoice_id',
        'company_id',
        'agent_id',
        'amount',
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
