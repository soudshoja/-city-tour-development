<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentSettlementDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agent_settlement_id',
        'invoice_id',
        'invoice_detail_id',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function agentSettlement()
    {
        return $this->belongsTo(AgentSettlement::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceDetail()
    {
        return $this->belongsTo(InvoiceDetail::class);
    }
}
