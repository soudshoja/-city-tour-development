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
        'refund_invoice_id', // Link to the specific invoice this refund task belongs to
        'client_id',
        'task_description',
        'original_invoice_price',
        'original_task_cost',
        'original_task_profit',
        'refund_fee_to_client',
        'supplier_charge',
        'new_task_profit',
        'total_refund_to_client',
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

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function refundInvoice()
    {
        return $this->belongsTo(Invoice::class, 'refund_invoice_id');
    }
}
