<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use HasFactory, SoftDeletes;

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
        'passenger_name',
        'reference',
        'gds_reference',
        'airline_reference',
        'created_by',
        'issued_by',
        'duration',
        'payment_type',
        'payment_method_account_id',
        'price',
        'exchange_currency',
        'exchange_rate',
        'original_price',
        'original_currency',
        'tax',
        'surcharge',
        'penalty_fee',
        'total',
        'cancellation_policy',
        'cancellation_deadline',
        'additional_info',
        'venue',
        'invoice_price',
        'voucher_status',
        'refund_date',
        'enabled',
        'taxes_record',
        'refund_charge',
        'ticket_number',
        'file_name',
        'issued_date',
        'expiry_date',
        'supplier_created_date'
    ];


    protected $requiredColumn = [
        'company_id',
        'supplier_id',
        'type',
        'status',
        // 'client_name',
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

    protected $casts = [
        'issued_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    // protected static function boot()
    // {
    //     parent::boot();
        
    //     static::creating(function ($task) {
    //         if (!empty($task->status)) {
    //             $task->status = strtolower(str_replace(' ', '_', $task->status));
    //         }
    //     });
    // }

    public function getRequiredColumns(): array
    {
        return $this->requiredColumn;
    }

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
        if($this->issued_date === null) {
            return null;
        }
        return $this->issued_date->format('d-m-Y');
    }

    public function getFormattedDateTimeAttribute()
    {
        if($this->issued_date === null) {
            return null;
        }
        return $this->issued_date->format('d-m-Y H:i');
    }

    public function getTaskPriceChangeableAttribute()
    {
        if(empty($this->original_currency)) {
            return true;
        } 

        return $this->original_currency !== 'KWD';
    }

    public function flightDetails() // temporary fix
    {
        return $this->hasOne(TaskFlightDetail::class, 'task_id');
    }

    public function flightDetail() // temporary fix
    {
        return $this->hasMany(TaskFlightDetail::class, 'task_id');
    }

    public function hotelDetails()
    {
        return $this->hasOne(TaskHotelDetail::class, 'task_id');
    }

    public function insuranceDetails()
    {
        return $this->hasMany(TaskInsuranceDetail::class, 'task_id');
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

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'task_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
