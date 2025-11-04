<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceReceipt extends Model
{
    protected $fillable = [
        'type',
        'invoice_id',
        'account_id',
        'credit_id',
        'transaction_id',
        'amount',
        'status',
        'is_used',
    ];

    public function invoice() 
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
