<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'company_id',
        'account_id',
        'invoice_id',
        'invoiceDetail_id',
        'transaction_date',
        'description',
        'debit',
        'credit',
        'balance',
        'voucher_number',
        'name',
        'type',
    ];

    // Define the relationship to the Invoice model
    public function account()
    {
        return $this->belongsTo(Account::class,'account_id');
    }

    
    public function invoice()
    {
        return $this->belongsTo(Invoice::class,'invoice_id');
    }

    public function invoiceDetail()
    {
        return $this->belongsTo(InvoiceDetail::class,'invoiceDetail_id');
    }


    public function transaction()
    {
        return $this->belongsTo(Transaction::class,'transaction_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    } 

}
