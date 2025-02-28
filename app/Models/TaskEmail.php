<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TaskEmail extends Model
{
    use HasFactory;

    protected $table = 'task_emails';
    
    protected $fillable = [
        'additional_info',
        'status',
        'price',
        'surcharge',
        'total',
        'tax',
        'reference',
        'type',
        'email_id',
        'agent_id',
        'client_id',
        'supplier_id',
        'client_name',
        'cancellation_policy',
        'invoice_price',
        'venue',
        'voucher_status',
        'enabled'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}