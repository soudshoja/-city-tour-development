<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyFatoorahPayment extends Model
{
    
    protected $table = 'myfatoorah_payments';
    protected $fillable = [
        'payment_int_id',
        'payment_id',
        'invoice_id',
        'invoice_status',
        'customer_reference',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array', 
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_int_id', 'id');
    }


}
