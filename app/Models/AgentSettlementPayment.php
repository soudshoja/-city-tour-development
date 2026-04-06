<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentSettlementPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agent_settlement_id',
        'amount',
        'method',
        'payment_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function agentSettlement()
    {
        return $this->belongsTo(AgentSettlement::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
