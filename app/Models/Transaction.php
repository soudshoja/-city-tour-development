<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'entity_id',
        'entity_type',
        'branch_id',
        'transaction_type',
        'amount',
        'description',
        'payment_id',
        'invoice_id',
        'payment_reference',
        'reference_type',
        'reference_number',
        'name',
        'remarks_internal',
        'remarks_fl',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('company', function ($query) {
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();

            if($user->role_id == Role::ADMIN){
                $query->all();
            } else if($user->role_id == Role::COMPANY){
                $query->where('company_id', $user->company->id);
            } else if ($user->role_id == Role::BRANCH){
                $query->whereHas('branch_id', $user->branch->id);
            } else if ($user->role_id == Role::AGENT){
                $query->whereHas('invoice.agent_id', $user->agent->id);
            } else if ($user->role_id == Role::ACCOUNTANT){
                $query->where('company_id', $user->accountant->branch->company->id);
            }
        });
    }

    public function getFormattedDateAttribute()
    {
        return $this->transaction_date ? $this->transaction_date->format('Y-m-d') : null;
    }

    // public function getTransactionHashAttribute()
    // {
    //     return hash('sha256', $this->id . $this->date . $this->amount);
    // }

    // public function getReferenceHashAttribute()
    // {
    //     return hash('sha256', $this->reference_type . $this->reference_number . $this->date);
    // }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'transaction_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoiceReceipts()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_receipt', 'transaction_id', 'invoice_id');
    }

    public function invoicePartial()
    {
        return $this->hasOne(InvoicePartial::class, 'invoice_id', 'invoice_id')->latest();
    }

    public function invoiceReceipt()    // one receipt per transaction
    {
        return $this->hasOne(InvoiceReceipt::class, 'transaction_id');
    }
}
