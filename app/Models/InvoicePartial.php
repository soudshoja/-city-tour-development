<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePartial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'client_id',
        'invoice_charge',
        'service_charge',
        'gateway_fee',
        'amount',
        'status',
        'expiry_date',
        'type',
        'charge_id',
        'payment_gateway',
        'payment_method',
        'payment_id',
        'receipt_voucher_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function invoiceReceipt()
    {
        return $this->hasOne(InvoiceReceipt::class, 'invoice_partial_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method');
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id');
    }

    public function paymentApplications()
    {
        return $this->hasMany(PaymentApplication::class, 'invoice_partial_id');
    }
}
