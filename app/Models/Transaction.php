<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\Lockable;

class Transaction extends Model
{
    use HasFactory, SoftDeletes, Lockable;

    protected $fillable = [
        'company_id',
        'entity_id',
        'entity_type',
        'branch_id',
        'transaction_type',
        'amount',
        'description',
        'payment_id',
        'invoice_id',
        'payment_reference',
        'reference_type',
        'reference_number',
        'name',
        'remarks_internal',
        'remarks_fl',
        'transaction_date',
        'posting_date',
        'is_locked',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        // P2.5.B (p2_5-brief.md §P2.5.B): the period-bucketing date — see JournalEntry's own
        // identical cast for the full rationale (BUG-C4 / three-date model).
        'posting_date' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('company', function ($query) {
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();

            if($user->role_id == Role::ADMIN){
                $query->where('company_id', '!=', null);
            } else if($user->role_id == Role::COMPANY){
                $query->where('company_id', $user->company->id);
            } else if ($user->role_id == Role::BRANCH){
                $query->where('branch_id', $user->branch->id);
            } else if ($user->role_id == Role::AGENT){
                $query->where('company_id', $user->agent->branch->company->id);
            } else if ($user->role_id == Role::ACCOUNTANT){
                $query->where('company_id', $user->accountant->branch->company->id);
            }
        });
    }

    public function getFormattedDateAttribute()
    {
        return $this->transaction_date ? $this->transaction_date->format('Y-m-d') : null;
    }

    // public function getTransactionHashAttribute()
    // {
    //     return hash('sha256', $this->id . $this->date . $this->amount);
    // }

    // public function getReferenceHashAttribute()
    // {
    //     return hash('sha256', $this->reference_type . $this->reference_number . $this->date);
    // }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'transaction_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoiceReceipts()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_receipt', 'transaction_id', 'invoice_id');
    }

    public function invoicePartial()
    {
        return $this->hasOne(InvoicePartial::class, 'invoice_id', 'invoice_id')->latest();
    }

    public function invoiceReceipt()    // one receipt per transaction
    {
        return $this->hasOne(InvoiceReceipt::class, 'transaction_id');
    }

    /**
     * P2.5.E (p2_5-brief.md §P2.5.E): a lightweight override for unlocking a `Transaction`
     * DIRECTLY. Checks this document's own journal lines for a reconciled leaf, and this
     * document's own accounting period -- the same two leaf signals
     * {@see \App\Models\JournalEntry::unlockBlockers()} checks on itself, and the same signals
     * {@see \App\Services\Accounting\UnlockDependencyResolver} checks when it reaches THIS
     * transaction while walking an Invoice's chain.
     */
    public function unlockBlockers(): array
    {
        $blockers = [];

        $this->journalEntries()
            ->withoutGlobalScope('company')
            ->where('reconciled', '!=', 0)
            ->get()
            ->each(function (JournalEntry $line) use (&$blockers) {
                $blockers[] = [
                    'type' => 'reconciled_line',
                    'id' => (int) $line->id,
                    'number' => 'JE-'.$line->id,
                    'status' => 'reconciled',
                    'url' => \Illuminate\Support\Facades\Route::has('journal-entries.index')
                        ? route('journal-entries.index', ['transactionId' => $this->id])
                        : null,
                    'hint' => 'This line is bank-reconciled -- unreconcile it (with a reason) before it can be unlocked, or correct via reverse + repost instead.',
                    'log_center_url' => \App\Services\Accounting\AuditLogLinker::forSubject('journal_entry', (int) $line->id),
                ];
            });

        $date = $this->posting_date ?? $this->transaction_date;
        if ($date !== null && $this->company_id !== null) {
            $status = app(\App\Services\Accounting\PeriodGuard::class)->statusFor((int) $this->company_id, $date);
            if ($status !== AccountingPeriod::STATUS_OPEN) {
                $year = (int) $date->format('Y');
                $month = (int) $date->format('n');
                $blockers[] = [
                    'type' => 'period',
                    'id' => $year * 100 + $month,
                    'number' => sprintf('%04d-%02d', $year, $month),
                    'status' => 'period_closed',
                    'url' => \Illuminate\Support\Facades\Route::has('accounting.periods.index')
                        ? route('accounting.periods.index', ['year' => $year])
                        : null,
                    'hint' => $status === AccountingPeriod::STATUS_LOCKED
                        ? 'This period is locked -- reopen it (accounting.period.reopen, with a reason) before this document can be unlocked.'
                        : 'This period is soft-closed -- an accounting.period.post-soft-closed override (with a reason) is required.',
                    'log_center_url' => \App\Services\Accounting\AuditLogLinker::forSubject('accounting_period', $year * 100 + $month),
                ];
            }
        }

        return $blockers;
    }
}
