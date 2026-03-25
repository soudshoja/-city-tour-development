<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\InvoiceStatus;
use App\Models\Reminder;
use App\Http\Traits\Lockable;
use InvalidArgumentException;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, Lockable;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'agent_id',
        'currency',
        'sub_amount',
        'invoice_charge',
        'amount',
        'status',
        'invoice_date',
        'paid_date',
        'due_date',
        'label',
        'account_number',
        'bank_name',
        'swift_no',
        'iban_no',
        'country_id',
        'tax',
        'discount',
        'shipping',
        'accept_payment',
        'payment_type',
        'is_client_credit',
        'external_url',
        'is_locked',
        'locked_by',
        'locked_at',
        'agent_loss',
        'company_loss',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'agent_loss' => 'decimal:2',
        'company_loss' => 'decimal:2',
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($invoice) {
            $validStatuses = array_column(InvoiceStatus::cases(), 'value');

            if (!in_array($invoice->status, $validStatuses, true)) {
                throw new InvalidArgumentException("Invalid invoice status: {$invoice->status}");
            }
        });
    }

    /**
     * When an invoice is locked, also lock:
     * - All transactions where invoice_id = this invoice
     * - All journal entries where invoice_id = this invoice
     */
    public static function getLockCascadeMap(): array
    {
        return [
            [Transaction::class,  'invoice_id'],
            [JournalEntry::class, 'invoice_id'],
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function invoicePartials()
    {
        return $this->hasMany(InvoicePartial::class);
    }


    public function JournalEntrys()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function originalRefunds()
    {
        // Refunds that refer to this invoice as the *original invoice*
        // → one invoice can have many refunds
        return $this->hasMany(Refund::class, 'invoice_id');
    }

    public function refund()
    {
        // Refund that uses this invoice as the *refund invoice*
        // → one refund invoice is linked to one refund record only
        return $this->hasOne(Refund::class, 'refund_invoice_id');
    }

    public function recalculateTotal()
    {
        $this->amount = $this->invoiceDetails()->sum('task_price');
        $this->sub_amount = $this->invoiceDetails()->sum('task_price');
        $this->save();
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'invoice_id');
    }

    /**
     * Get all payment applications (payments applied to this invoice)
     */
    public function paymentApplications()
    {
        return $this->hasMany(PaymentApplication::class, 'invoice_id');
    }

    /**
     * Get total amount paid via payment applications
     */
    public function getTotalPaidViaApplicationsAttribute()
    {
        return PaymentApplication::getTotalAppliedToInvoice($this->id);
    }

    /**
     * Get remaining balance on this invoice
     */
    public function getRemainingBalanceAttribute()
    {
        return $this->amount - $this->total_paid_via_applications;
    }

    /**
     * Check if invoice is fully paid via payment applications
     */
    public function isFullyPaidViaApplications()
    {
        return $this->remaining_balance <= 0;
    }

    /**
     * Get effective loss settings for this invoice.
     * Returns invoice-level override if set, otherwise falls back to agent_loss table default.
     *
     * Bearer is derived from percentages:
     *   agent_loss=100 → agent bears | company_loss=100 → company bears | otherwise → split
     */
    public function getEffectiveLossSettings(): AgentLoss
    {
        if ($this->agent_loss !== null) {
            $agentPct = (float) $this->agent_loss;
            $companyPct = (float) $this->company_loss;

            if ($agentPct >= 100) {
                $bearer = AgentLoss::BEARER_AGENT;
            } elseif ($companyPct >= 100) {
                $bearer = AgentLoss::BEARER_COMPANY;
            } else {
                $bearer = AgentLoss::BEARER_SPLIT;
            }

            return new AgentLoss([
                'agent_id' => $this->agent_id,
                'company_id' => $this->agent?->branch?->company_id ?? 0,
                'loss_bearer' => $bearer,
                'agent_percentage' => $agentPct,
                'company_percentage' => $companyPct,
            ]);
        }

        $companyId = $this->agent?->branch?->company_id;
        if ($companyId && $this->agent_id) {
            return AgentLoss::getForAgent($this->agent_id, $companyId);
        }

        return new AgentLoss([
            'loss_bearer' => AgentLoss::BEARER_COMPANY,
            'agent_percentage' => 0,
            'company_percentage' => 100,
        ]);
    }

    public function hasLossBearerOverride(): bool
    {
        return $this->agent_loss !== null;
    }

    public function hasLoss(): bool
    {
        return $this->invoiceDetails->contains(fn($d) => $d->profit < 0);
    }
}
