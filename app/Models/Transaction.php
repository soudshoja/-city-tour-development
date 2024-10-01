<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'agent_id',
        'client_id',
        'transaction_amount',
        'payment_type',
        'transaction_details',
    ];

    // Define the relationship to the Invoice model
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
