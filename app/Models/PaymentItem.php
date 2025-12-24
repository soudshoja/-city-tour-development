<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'product_name',
        'quantity',
        'unit_price',
        'extended_amount',
        'currency',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:3',
        'extended_amount' => 'decimal:3',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
