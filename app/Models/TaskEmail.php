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
        'email_id',
        'client_id',
        'client_name',
        'agent_id',
        'agent_name', 
        'company_id',
        'company_name', 
        'type',
        'status',
        'reference',
        'duration',
        'payment_type',
        'price',
        'tax',
        'surcharge',
        'total',
        'cancellation_policy',
        'additional_info',
        'destination', 
        'vendor_name', 
        'supplier_id',
        'supplier_name', 
        'venue',
        'invoice_price',
        'voucher_status',
        'enabled',
    ];

    public $incrementing = true;
    protected $primaryKey = 'id';

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