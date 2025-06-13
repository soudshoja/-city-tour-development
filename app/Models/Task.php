<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
        'supplier_status',
        'original_task_id',
        'client_name',
        'reference',
        'gds_reference',
        'airline_reference',
        'created_by',
        'issued_by',
        'duration',
        'payment_type',
        'price',
        'exchange_currency',
        'original_price',
        'original_currency',
        'tax',
        'surcharge',
        'penalty_fee',
        'total',
        'cancellation_policy',
        'additional_info',
        'venue',
        'invoice_price',
        'voucher_status',
        'refund_date',
        'enabled',
        'taxes_record',
        'refund_charge',
        'ticket_number',
        'created_at'
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

    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('d-m-Y');
    }
    
    public function getFormattedDateTimeAttribute()
    {
        return $this->created_at->format('d-m-Y h:i A');
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

    public function refundDetail()
    {
        return $this->hasOne(Refund::class, 'task_id');
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

    public function originalTask()
    {
        return $this->belongsTo(Task::class, 'original_task_id');
    }

    public function linkedTask()
    {
        return $this->hasOne(Task::class, 'original_task_id');
    }
}