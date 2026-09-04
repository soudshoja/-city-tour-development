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
        'ticket_status',
        'client_status',
        'deadline_at',
        'supplier_status',
        'original_task_id',
        'import_key',
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
        // P2.5.D (p2_5-brief.md §P2.5.D): the travel/check-in date RevenueRecognitionService
        // reads to release an `at_travel` service's deferred sale — see the migration's own
        // docblock for why this is a new, type-agnostic column rather than reusing an existing
        // date field.
        'travel_date',
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
        'travel_date' => 'date',
        'deadline_at' => 'datetime',
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

    /**
     * W6.I "Importer contract" item 3 (w6-brief.md; importer-status-contract.md Table 4). Every
     * task-creation path (TaskController::store(), TaskWebhook::createTaskWithDetails(), Magic
     * Holiday's processSingleReservation() via store(), the bulk/AI upload path) funnels through
     * `Task::create()`/`new Task` + `->save()`, so hooking `import_key` computation here — rather
     * than hand-editing each call site — is the one place that guarantees every creation path gets
     * it for free, matching this sub-wave's own "surgical edits only" constraint on the 7k-line
     * TaskController. Never overwrites an explicitly-set `import_key` (a caller that already knows
     * its own key, e.g. a future bulk-reimport tool, is respected verbatim).
     */
    protected static function booted()
    {
        static::creating(function (Task $task) {
            if (empty($task->import_key)) {
                $task->import_key = self::computeImportKey(
                    $task->ticket_number,
                    $task->airline_reference,
                    $task->issued_date,
                    $task->reference,
                    $task->passenger_name
                );
            }
        });
    }

    /**
     * W6.I "Importer contract" item 3. Pure function -- `ticket_no+airline_code+issue_date`,
     * fallback `reference+passenger_name+issue_date` when no ticket number exists (EMD, some bulk
     * sources). Returns null when neither shape has enough data to build a stable key (never a
     * key built from partial/empty pieces, which could collide across genuinely different
     * bookings). `$airlineCode` is this schema's own `airline_reference` column -- there is no
     * separate "airline code" field on `tasks` (grepped; reported to the owner in this sub-wave's
     * build report as a naming substitution, not a design gap).
     */
    public static function computeImportKey(
        ?string $ticketNumber,
        ?string $airlineCode,
        $issueDate,
        ?string $reference,
        ?string $passengerName
    ): ?string {
        $ticketNumber = $ticketNumber !== null ? trim($ticketNumber) : null;
        $airlineCode = $airlineCode !== null ? trim($airlineCode) : null;
        $reference = $reference !== null ? trim($reference) : null;
        $passengerName = $passengerName !== null ? trim($passengerName) : null;

        $issueDateKey = null;
        if (! empty($issueDate)) {
            try {
                $issueDateKey = Carbon::parse($issueDate)->toDateString();
            } catch (\Exception $e) {
                $issueDateKey = null;
            }
        }

        if ($issueDateKey === null) {
            return null;
        }

        if (! empty($ticketNumber) && ! empty($airlineCode)) {
            return 'TKT:'.$ticketNumber.':'.$airlineCode.':'.$issueDateKey;
        }

        if (! empty($reference) && ! empty($passengerName)) {
            return 'REF:'.$reference.':'.$passengerName.':'.$issueDateKey;
        }

        return null;
    }

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

    public function getGdsReferenceAttribute(): ?string
    {
        $own = $this->attributes['gds_reference'] ?? null;
        if (!empty($own)) return $own;
        if (empty($this->original_task_id)) return $own;
        return $this->originalTask?->getRawOriginal('gds_reference');
    }

    public function getAirlineReferenceAttribute(): ?string
    {
        $own = $this->attributes['airline_reference'] ?? null;
        if (!empty($own)) return $own;
        if (empty($this->original_task_id)) return $own;
        return $this->originalTask?->getRawOriginal('airline_reference');
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

    public function paymentMethod()
    {
        return $this->belongsTo(Account::class, 'payment_method_account_id');
    }
}
