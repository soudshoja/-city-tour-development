<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'agent_id',
        'voucher_number',
        'payment_reference',
        'invoice_id',
        'from',
        'pay_to',
        'account_id',
        'currency',
        'payment_date',
        'notes',
        'amount',
        'payment_method',
        'status',
        'account_number',
        'bank_name',
        'swift_no',
        'iban_no',
        'country',
        'tax',
        'discount',
        'shipping',
        'completed',
    ];
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
    
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'referenceable', 'reference_type', 'reference_id');
    }

    public function partials()
    {
        return $this->hasMany(InvoicePartial::class);
    }
}
