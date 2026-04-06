<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentSettlement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'settlement_number',
        'agent_id',
        'branch_id',
        'company_id',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'settlement_date',
        'notes',
        'created_by',
        'updated_by'
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function details()
    {
        return $this->hasMany(AgentSettlementDetail::class);
    }

    public function payments()
    {
        return $this->hasMany(AgentSettlementPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
