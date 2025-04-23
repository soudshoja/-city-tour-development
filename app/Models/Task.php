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


    protected $requiredColumn = [
        'client_id',
        'agent_id',
        'company_id',
        'supplier_id',
        'type',
        'status',
        'client_name',
        'reference',
        'total',
        // 'venue',
    ];

    // protected static function booted()
    // {
    //     static::addGlobalScope('enabled', function (Builder $builder) {
    //         $builder->where('enabled', true);
    //     });
    // }

    public function getIsCompleteAttribute()
    {
        $isComplete = true;

        foreach ($this->requiredColumn as $column) {
            if (empty($this->$column)) {
                $isComplete = false;
                break;
            }
        }

        return $isComplete;
    }

    public function scopeCompleted($query)
    {
        return $query->where(function ($q) {
            foreach ($this->requiredColumn as $column) {
                $q->whereNotNull($column)->where($column, '!=', '');
            }
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