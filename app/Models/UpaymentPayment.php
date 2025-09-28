<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpaymentPayment extends Model
{
    use HasFactory;

    protected $table = 'upayments_payments';

    protected $fillable = [
        'payment_int_id',
        'payment_id',
        'order_id',
        'invoice_id',
        'track_id',
        'status',
        'payment_type',
        'payment_method',
        'total_price',
        'payment_date',
        'payload',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'payload' => 'array',
        'total_price' => 'decimal:3',
    ];

    /**
     * Optional relation back to your main Payment model.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_int_id');
    }
}