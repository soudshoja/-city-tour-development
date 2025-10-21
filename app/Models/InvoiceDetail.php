<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceDetail extends Model
{
    use HasFactory, SoftDeletes;

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

    public function JournalEntrys()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
