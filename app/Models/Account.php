<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'serial_number',
        'account_type',
        'report_type',
        'name',
        'level',
        'actual_balance',
        'opening_balance',
        'opening_balance_date',
        'budget_balance',
        'variance',
        'parent_id',
        'root_id',
        'company_id',
        'branch_id',
        'agent_id',
        'client_id',
        'supplier_id',
        'supplier_company_id',
        'reference_id',
        'account_type_id',
        'code',
        'currency',
        'is_group',
        'disabled',
        'balance_must_be',
        'supplier_company_id',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:3',
        'opening_balance_date' => 'date',
    ];

    public const REPORT_TYPES = [
        'PROFIT_LOSS' => 'profit loss',
        'BALANCE_SHEET' => 'balance sheet',
    ];

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

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'account_id');
    }

    // public function client()
    // {
    //     return $this->hasOne(Agent::class, 'account_id');
    // }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'account_id');
    }

    public function root()
    {
        return $this->belongsTo(Account::class, 'root_id');
    }

    public function calculateSupplierBalances()
    {
        // Fetch all general ledger entries for this account
        $JournalEntrys = $this->JournalEntrys;

        $supplierBalances = [];

        foreach ($JournalEntrys as $JournalEntry) {
            // Traverse through the relationships to reach suppliers
            $invoice = $JournalEntry->invoice;
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
                $supplierBalances[$supplier->id]['credit'] += $JournalEntry->credit;
                $supplierBalances[$supplier->id]['debit'] += $JournalEntry->debit;
                $supplierBalances[$supplier->id]['actual_balance'] = $supplierBalances[$supplier->id]['credit'] - $supplierBalances[$supplier->id]['debit'];
            }
        }
    }
}
