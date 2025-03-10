<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'agent_id',
        'company_id',
        'supplier_id',
        'type',
        'status',
        'client_name',
        'reference',
        'duration',
        'payment_type',
        'price',
        'tax',
        'surcharge',
        'total',
        'cancellation_policy',
        'additional_info',
        'venue',
        'invoice_price',
        'voucher_status',
        'enabled'
    ];

    // protected $fillable = [
    //     'additional_info',
    //     'status',
    //     'price',
    //     'surcharge',
    //     'total',
    //     'tax',
    //     'reference',
    //     'type',
    //     'agent_id',
    //     'client_id',
    //     'supplier_id',
    //     'client_name',
    //     'cancellation_policy',
    //     'invoice_price',
    //     'venue',
    //     'voucher_status',
    //     'enabled'
    // ];

    protected static function booted()
    {
        static::addGlobalScope('enabled', function (Builder $builder) {
            $builder->where('enabled', true);
        });
    }

    public function flightDetails()
    {
        return $this->hasOne(TaskFlightDetail::class, 'task_id');
    }

    public function hotelDetails()
    {
        return $this->hasOne(TaskHotelDetail::class, 'task_id');
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
}