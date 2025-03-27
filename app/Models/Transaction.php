<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
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


    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
    
}
