<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'refund_id',
        'task_id',
        'invoice_id', // Link to the specific invoice this task's original belongs to
        'refund_invoice_id',
        'client_id',
        'task_description',
        'original_invoice_price', //(Original selling price of this specific task)
        'original_task_cost', //(Original cost price of this specific task)
        'original_task_profit', //(Original profit of this specific task)
        'refund_fee_to_client', //(Specific fee charged to client for this task's refund)
        'refund_task_supplier_charge', //(Supplier charges for refunding this specific task)
        // 'refund_task_cost_price', //(Adjusted cost price for the refunded task, if applicable)
        'new_task_profit', //(Adjusted profit for this specific task after refund)
        'total_refund_to_client', //(Total amount to be refunded to client for this specific task)
        'net_refund', //(Calculated net refund for this specific task)
        'remarks',
    ];

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function refundClient()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function refundChargesInvoice()
    {
        return $this->belongsTo(Invoice::class, 'refund_invoice_id');
    }
}
