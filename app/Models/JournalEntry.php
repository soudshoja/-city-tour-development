<?php

namespace App\Models;

use App\Http\Traits\Lockable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes, Lockable, BelongsToCompany;

    protected $fillable = [
        'transaction_id',
        'company_id',
        'account_id',
        'branch_id',
        'invoice_id',
        'invoice_detail_id',
        'transaction_date',
        'posting_date',
        'description',
        'debit',
        'credit',
        'balance',
        'voucher_number',
        'name',
        'type',
        'type_reference_id',
        'currency',
        'exchange_rate',
        'amount',
        'cheque_no',
        'cheque_date',
        'cheque_clearance_date',
        'bank_info',
        'auth_no',
        'reconciled',
        'reconciled_ref_id',
        'task_id',
        'original_currency',
        'original_amount',
        'receipt_reference_number',
        'is_locked',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        // P2.5.B (p2_5-brief.md §P2.5.B): the period-bucketing date every report query must key
        // off instead of created_at (BUG-C4) — see TrialBalanceService/ReportController's own
        // P2.5.B notes. Distinct from transaction_date (the document's own, never-altered date).
        'posting_date' => 'date',
    ];

    public const ADDITIONAL_INVOICE_CHARGE = 'Additional Invoice Charge';

    /**
     * P2.5.E (p2_5-brief.md §P2.5.E): a lightweight override for unlocking a `JournalEntry`
     * DIRECTLY (as opposed to via {@see \App\Models\Invoice}'s cascade, which is the chain
     * {@see \App\Services\Accounting\UnlockDependencyResolver} owns). Two of the same three
     * signals the resolver checks at each chain leaf, applied to THIS line itself: its own
     * `reconciled` flag, and the accounting period its own `posting_date`/`transaction_date` falls
     * in. No application/receipt walk here -- a `JournalEntry` has no upstream application/receipt
     * of its own; it IS the leaf the resolver's chain walk already reaches when unlocking an
     * Invoice.
     */
    public function unlockBlockers(): array
    {
        $blockers = [];

        if ((int) $this->reconciled !== 0) {
            $blockers[] = [
                'type' => 'reconciled_line',
                'id' => (int) $this->id,
                'number' => 'JE-'.$this->id,
                'status' => 'reconciled',
                'url' => \Illuminate\Support\Facades\Route::has('journal-entries.index')
                    ? route('journal-entries.index', ['transactionId' => $this->transaction_id])
                    : null,
                'hint' => 'This line is bank-reconciled -- unreconcile it (with a reason) before it can be unlocked, or correct via reverse + repost instead.',
                'log_center_url' => \App\Services\Accounting\AuditLogLinker::forSubject('journal_entry', (int) $this->id),
            ];
        }

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
                        ? 'This period is locked -- reopen it (accounting.period.reopen, with a reason) before this line can be unlocked.'
                        : 'This period is soft-closed -- an accounting.period.post-soft-closed override (with a reason) is required.',
                    'log_center_url' => \App\Services\Accounting\AuditLogLinker::forSubject('accounting_period', $year * 100 + $month),
                ];
            }
        }

        return $blockers;
    }

    // public static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($journalEntry) {
    //         $account = Account::find($journalEntry->account_id);

    //         // Log::info('Creating Journal Entry for Account ID: ' . $journalEntry->account_id);
    //         // Log::info('Account Details: ', $account->toArray());
    //         // Log::infO('Account Children'. json_encode($account->children()->get()));

    //         if ($account && $account->children()->exists()) {

    //             Log::error('Attempt to create journal entry for an account with child accounts.', [
    //                 'account_id' => $journalEntry->account_id,
    //                 'account_name' => $account->name,
    //             ]);

    //             throw new \Exception('Cannot create journal entry for an account that has child accounts.');
    //         }
    //     });
    // }

    // Define the relationship to the Invoice model
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function referenceAccount()
    {
        return $this->belongsTo(Account::class, 'type_reference_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function invoiceDetail()
    {
        return $this->belongsTo(InvoiceDetail::class, 'invoice_detail_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function agent()
    {
        return $this->hasOneThrough(
            Agent::class,
            Task::class,
            'id',
            'id',
            'task_id',
            'agent_id'
        );
    }
}
