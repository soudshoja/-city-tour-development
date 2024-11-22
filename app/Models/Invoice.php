<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'agent_id',
        'currency',
        'sub_amount',
        'amount',
        'status',
        'invoice_date',
        'due_date',
        'label',
        'account_number',
        'bank_name',
        'swift_no',
        'iban_no',
        'country',
        'tax',
        'discount',
        'shipping',
        'accept_payment',
        
    ];
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class);
    }


    public function generalLedgers()
    {
        return $this->hasMany(GeneralLedger::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }


}
