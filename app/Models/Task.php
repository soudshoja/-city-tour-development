<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'additional_info',
        'status',
        'price',
        'surcharge',
        'total',
        'tax',
        'reference',
        'type',
        'agent_id',
        'client_id',
        'supplier_id',
        'client_name',
        'cancellation_policy',
        'venue'
    ];

    // In Task.php
    public function flightDetails()
   {
        return $this->hasOne(TaskFlightDetail::class, 'task_id');
    }


    public function invoiceDetail()
    {
        return $this->hasOne(InvoiceDetail::class, 'task_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public $timestamps = false;

}