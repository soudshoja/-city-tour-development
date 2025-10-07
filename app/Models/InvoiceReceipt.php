<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceReceipt extends Model
{
    protected $table = 'invoice_receipt';
    protected $fillable = [
        'invoice_id',
        'transaction_id',
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
