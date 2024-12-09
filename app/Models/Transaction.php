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
    'transaction_type',
    'amount',
    'date',
    'description',
    'invoice_id', 
    'reference_type', 
    ];


    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
    
}
