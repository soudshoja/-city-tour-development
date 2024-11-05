<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'agent_id',
        'currency',
        'sub_amount',
        'amount',
        'status',
        'invoice_date',
        'due_date',
        'label',
        'account_number',
        'bank_name',
        'swift_no',
        'iban_no',
        'country',
        'tax',
        'discount',
        'shipping',
        'accept_payment',
        
    ];
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class);
    }

    public function payment()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
