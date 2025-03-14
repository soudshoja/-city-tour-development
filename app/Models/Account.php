<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
       'name', 
       'level', 
       'actual_balance',
       'budget_balance',    
       'variance',  
       'parent_id', 
       'company_id', 
       'reference_id', 
       'code',
    ];

     protected static function booted()
    {
        static::addGlobalScope('company', function ($query) {
            if (auth()->check() && auth()->user()->company != null) {
                $query->where('company_id', auth()->user()->company->id);
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id'); 
    }
    
    public function agent()
    {
        return $this->hasOne(Agent::class, 'account_id');
    }

    public function client()
    {
        return $this->hasOne(Agent::class, 'account_id');
    }

    public function generalLedgers()
    {
        return $this->hasMany(GeneralLedger::class, 'account_id');
    }

    public function calculateSupplierBalances()
    {
        // Fetch all general ledger entries for this account
        $generalLedgers = $this->generalLedgers;

        $supplierBalances = [];

        foreach ($generalLedgers as $generalLedger) {
            // Traverse through the relationships to reach suppliers
            $invoice = $generalLedger->invoice;
            if (!$invoice) {
                continue;
            }

            foreach ($invoice->invoiceDetails as $invoiceDetail) {
                $task = $invoiceDetail->task;
                if (!$task || !$task->supplier) {
                    continue;
                }

                $supplier = $task->supplier;

                // Initialize supplier balance if not already set
                if (!isset($supplierBalances[$supplier->id])) {
                    $supplierBalances[$supplier->id] = [
                        'supplier_id' => $supplier->id,
                        'credit' => 0,
                        'debit' => 0,
                        'actual_balance' => 0,
                    ];
                }

                // Sum up credit and debit for this supplier
                $supplierBalances[$supplier->id]['credit'] += $generalLedger->credit;
                $supplierBalances[$supplier->id]['debit'] += $generalLedger->debit;
                $supplierBalances[$supplier->id]['actual_balance'] = $supplierBalances[$supplier->id]['credit'] - $supplierBalances[$supplier->id]['debit'];
            }
        }
    }
}