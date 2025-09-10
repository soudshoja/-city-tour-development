<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HesabePayment extends Model 
{
    protected $table = 'hesabe_payments';
    protected $fillable = [
        'payment_int_id',
        'status',
        'payment_token',
        'payment_id',
        'order_reference_number',
        'auth_code',
        'track_id',
        'transaction_id',
        'invoice_id',
        'paid_on',
        'payload',

    ];

    protected $casts = [
        'payload' => 'array'
    ];

    public function payment() 
    {
        return $this->belongsTo(Payment::class, 'payment_int_id', 'id');
    }
}