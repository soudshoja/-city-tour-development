<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;

class InvoicePartial extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'client_id',
        'amount',
        'status',
        'expiry_date',  
        'type',    
        'payment_gateway',
        'payment_id',
    ];
    
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');             
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');             
    }

}
