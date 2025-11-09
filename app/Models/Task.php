<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
        'client_ref',
        'is_n8n_booking',
        'passenger_name',
        'reference',
        'original_reference',
        'gds_reference',
        'airline_reference',
        'created_by',
        'issued_by',
        'iata_number',
        'issued_date',
        'expiry_date',
        'duration',
        'payment_type',
        'payment_method_account_id',
        'price',
        'exchange_currency',
        'exchange_rate',
        'original_price',
        'original_currency',
        'tax',
        'original_tax',
        'surcharge',
        'original_surcharge',
        'penalty_fee',
        'supplier_surcharge',
        'taxes_record',
        'total',
        'original_total',
        'cancellation_policy',
        'cancellation_deadline',
        'supplier_pay_date',
        'additional_info',
        'ticket_number',
        'original_ticket_number',
        'file_name',
        'venue',
        'invoice_price',
        'voucher_status',
        'enabled',
        'refund_charge',
        'refund_date',
        'enabled',
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
        'supplier_pay_date' => 'datetime',
        'cancellation_deadline' => 'datetime',
        'is_complete' => 'bool',
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
            if (empty($this->$column) && $this->$column != 0 && $this->$column != '0') {
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
        if ($this->issued_date === null) {
            return null;
        }
        return $this->issued_date->format('d-m-Y');
    }

    public function getFormattedDateTimeAttribute()
    {
        if ($this->issued_date === null) {
            return null;
        }
        return $this->issued_date->format('d-m-Y H:i');
    }

    public function getTaskPriceChangeableAttribute()
    {
        return $this->original_currency !== null && $this->original_price !== 'KWD';
    }

    protected function cancellationDeadline(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (empty($value)) return null;

                // Parse the ISO8601 (with offset) but DO NOT change timezone. Just format to 'Y-m-d H:i:s' to fit MySQL DATETIME.
                $dt = Carbon::parse($value);
                return $dt->format('Y-m-d H:i:s');
            },
        );
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
        return $this->hasOne(TaskInsuranceDetail::class, 'task_id');
    }

    public function visaDetails()
    {
        return $this->hasOne(TaskVisaDetail::class, 'task_id');
    }

    public function invoiceDetail()
    {
        return $this->hasOne(InvoiceDetail::class, 'task_id');
    }

    public function refundDetail()
    {
        return $this->hasOne(RefundDetail::class, 'task_id');
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

    public function supplierCompany()
    {
        return $this->belongsTo(SupplierCompany::class, 'supplier_id', 'supplier_id')
            ->where('company_id', '=', $this->company_id);
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

    public function supplierOnline()
    {
        return $this->belongsTo(Supplier::class);
    }
}
