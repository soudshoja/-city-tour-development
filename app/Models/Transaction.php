<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'company_id',
        'client_id',
        'transaction_date',
        'transaction_type',
        'amount',
        'status',
        'payment_type',
        'description',
        'created_at',
    ];

    // Define the relationship to the Invoice model
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'transaction_id');
    }
}
