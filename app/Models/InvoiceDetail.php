<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'task_id',
        'task_description',
        'task_remark',
        'client_notes',
        'task_price',
        'supplier_price',
        'markup_price',
        'paid',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function generalLedgers()
    {
        return $this->hasMany(GeneralLedger::class);
    }
}
