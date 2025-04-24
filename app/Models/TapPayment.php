<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TapPayment extends Model
{
    protected $table = 'tap_payments';

    protected $fillable = [
        'payment_id',
        'tap_id',
        'authorization_id',
        'timezone',
        'expiry_period',
        'expiry_type',
        'amount',
        'currency',
        'date_created',
        'date_completed',
        'date_transaction',
        'receipt_id',
        'receipt_email',
        'receipt_sms',
    ];

    protected $casts = [
        'receipt_email' => 'boolean',
        'receipt_sms' => 'boolean'
    ];
}
