<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceReceipt extends Model
{
    protected $table = 'invoice_receipt';
    protected $fillable = [
        'type',
        'invoice_id',
        'account_id',
        'credit_id',
        'transaction_id',
        'amount',
        'status',
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
