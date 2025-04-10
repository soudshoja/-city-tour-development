<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
    'company_id',
    'entity_id',
    'entity_type',
    'branch_id',
    'transaction_type',
    'amount',
    'date',
    'description',
    'invoice_id', 
    'reference_type', 
    'reference_number',
    'name',
    'remarks_internal',
    'remarks_fl',
    ];

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

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
    
}
