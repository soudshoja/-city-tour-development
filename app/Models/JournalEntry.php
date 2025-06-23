<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'company_id',
        'account_id',
        'branch_id',
        'invoice_id',
        'invoice_detail_id',
        'transaction_date',
        'description',
        'debit',
        'credit',
        'balance',
        'voucher_number',
        'name',
        'type',
        'type_reference_id',
        'currency',
        'exchange_rate',
        'amount',
        'cheque_no',
        'cheque_date',
        'bank_info',
        'auth_no',
        'reconciled',
        'reconciled_ref_id',
        'task_id',
    ];

    protected static function booted()
    {
        static::addGlobalScope('company', function ($query) {
            if (auth()->check() && auth()->user()->company != null) {
                $query->where('company_id', auth()->user()->company->id);
            }
        });
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($journalEntry) {
            $account = Account::find($journalEntry->account_id);

            // Log::info('Creating Journal Entry for Account ID: ' . $journalEntry->account_id);
            // Log::info('Account Details: ', $account->toArray());
            // Log::infO('Account Children'. json_encode($account->children()->get()));
            
            if ($account && $account->children()->exists()) {

                Log::error('Attempt to create journal entry for an account with child accounts.', [
                    'account_id' => $journalEntry->account_id,
                    'account_name' => $account->name,
                ]);

                throw new \Exception('Cannot create journal entry for an account that has child accounts.');
            }
        });
    }

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
        return $this->belongsTo(InvoiceDetail::class,'invoice_detail_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class,'transaction_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    } 
 

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    } 

}
