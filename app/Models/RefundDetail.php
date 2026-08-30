<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundDetail extends Model
{
    use SoftDeletes;

    /**
     * W4.R (w4-brief.md §4 process decisions): `supplier_charge` is kept, unrenamed — it carries
     * the airline/consolidator PENALTY amount deducted from the supplier's refund (documented
     * here, not renamed, per the brief's own instruction). `supplier_refund_amount` is the NEW
     * column: what the supplier actually refunds (defaults to original_task_cost - supplier_charge
     * when not explicitly overridden — see RefundPostingService::supplierRefundAmount()).
     */
    protected $fillable = [
        'refund_id',
        'task_id',
        'client_id',
        'task_description',
        'original_invoice_price',
        'original_task_cost',
        'original_task_profit',
        'refund_fee_to_client',
        'supplier_charge',
        'supplier_refund_amount',
        'new_task_profit',
        'total_refund_to_client',
        'remarks',
    ];

    protected $casts = [
        'original_invoice_price' => 'float',
        'original_task_cost' => 'float',
        'original_task_profit' => 'float',
        'refund_fee_to_client' => 'float',
        'supplier_charge' => 'float',
        'supplier_refund_amount' => 'float',
        'new_task_profit' => 'float',
        'total_refund_to_client' => 'float',
    ];

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
