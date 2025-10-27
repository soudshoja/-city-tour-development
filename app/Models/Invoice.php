<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\InvoiceStatus;
use InvalidArgumentException;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'agent_id',
        'currency',
        'sub_amount',
        'invoice_charge',
        'amount',
        'status',
        'invoice_date',
        'paid_date',
        'due_date',
        'label',
        'account_number',
        'bank_name',
        'swift_no',
        'iban_no',
        'country_id',
        'tax',
        'discount',
        'shipping',
        'accept_payment',
        'payment_type',
        'is_client_credit',
        'external_url',
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($invoice) {
            $validStatuses = array_column(InvoiceStatus::cases(), 'value');

            if (!in_array($invoice->status, $validStatuses, true)) {
                throw new InvalidArgumentException("Invalid invoice status: {$invoice->status}");
            }
        });
    }

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

    public function invoicePartials()
    {
        return $this->hasMany(InvoicePartial::class);
    }


    public function JournalEntrys()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function originalRefunds() // refunds that refer to this invoice as the *original invoice*
    {
        return $this->hasMany(Refund::class, 'invoice_id');
    }

    public function refund() // refunds that use this invoice as the *refund invoice*
    {
        return $this->hasMany(Refund::class, 'refund_invoice_id');
    }

    public function recalculateTotal()
    {
        $this->amount = $this->invoiceDetails()->sum('task_price');
        $this->sub_amount = $this->invoiceDetails()->sum('task_price');
        $this->save();
    }
}
