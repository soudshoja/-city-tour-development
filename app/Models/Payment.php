<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'agent_id',
        'voucher_number',
        'payment_reference',
        'invoice_id',
        'invoice_reference',
        'auth_code',
        'from',
        'pay_to',
        'created_by',
        'service_charge',
        'account_id',
        'currency',
        'payment_date',
        'notes',
        'amount',
        'payment_gateway',
        'payment_method_id',
        'payment_url',
        'expiry_date',
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

    protected $casts = [
        'payment_date' => 'datetime',
        'expiry_date' => 'datetime',
        'amount' => 'decimal:3',
        'service_charge' => 'decimal:3',
        'tax' => 'decimal:3',
        'completed' => 'boolean',
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

    public function tapPayment()
    {
        return $this->hasOne(TapPayment::class);
    }

    public function myFatoorahPayment()
    {
        return $this->hasOne(MyFatoorahPayment::class, 'payment_int_id', 'id');
    }

    public function findMyFatoorahPayment()
    {
        if (empty($this->payment_reference)) {
            return null;
        }
        
        return MyFatoorahPayment::where('invoice_id', $this->payment_reference)
            ->orWhere('payment_id', $this->payment_reference)
            ->first();
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function credit() 
    {
        return $this->hasMany(Credit::class, 'payment_id');
    }

    public function hotelBooking()
    {
        return $this->hasOne(HotelBooking::class, 'payment_id');
    }
}
