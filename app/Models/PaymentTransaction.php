<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_id',
        'transaction_id',
        'status',
        'url',
        'payment_gateway_id',
        'payment_method_id',
        'track_id',
        'reference_number',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }


    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentGateway()
    {
        return $this->belongsTo(Charge::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

}
