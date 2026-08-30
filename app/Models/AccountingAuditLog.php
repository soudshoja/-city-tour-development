<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F): "APPEND-ONLY -- model has no update/delete ... no soft deletes."
 * Enforced on TWO layers (fix-round 2026-08-30, per verify findings — CONFIRMED #1: a boot guard
 * alone never fires for a query-builder bulk mutation or raw SQL, since neither hydrates a model or
 * fires model events):
 *   1. This model's boot guard — {@see self::booted()} refuses any `updating`/`deleting` Eloquent
 *      event unconditionally, for every per-instance caller, including `forceFill()->save()` on an
 *      existing row and `::destroy()`. Kept alongside the trigger below (not replaced by it)
 *      because it gives an instant, catchable `\RuntimeException` for the common per-instance path
 *      instead of surfacing a generic `\Illuminate\Database\QueryException` first, and it is
 *      directly unit-testable ({@see \Tests\Unit\Models\AccountingAuditLogAppendOnlyTest}) without
 *      needing a real trigger to fire inside a transactional test.
 *   2. A real MySQL `BEFORE UPDATE`/`BEFORE DELETE` trigger on the `accounting_audit_log` table
 *      itself (`database/migrations/2026_08_30_150001_..._add_append_only_triggers_...php`) — the
 *      layer that actually closes the query-builder/raw-SQL gap the boot guard cannot see. The
 *      DELETE trigger has one narrow, documented escape hatch for the opt-in retention/archival job
 *      ({@see \App\Console\Commands\AccountingAuditLogPurge}); the UPDATE trigger has none.
 *
 * `subject_type` values are the short, friendly strings {@see \App\Services\Accounting\AuditLogLinker}
 * already commits to (invoice, payment, invoice_receipt, transaction, journal_entry,
 * accounting_period, refund, voucher, task, setting, ...) — never a raw FQCN.
 */
class AccountingAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'accounting_audit_log';

    protected $fillable = [
        'company_id',
        'actor_id',
        'actor_type',
        'action',
        'subject_type',
        'subject_id',
        'transaction_id',
        'before',
        'after',
        'reason',
        'ip',
        'route',
        'posting_period',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'actor_id' => 'integer',
        'subject_id' => 'integer',
        'transaction_id' => 'integer',
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $log) {
            throw new \RuntimeException('accounting_audit_log is append-only: rows may never be updated.');
        });

        static::deleting(function (self $log) {
            throw new \RuntimeException('accounting_audit_log is append-only: rows may never be deleted.');
        });
    }

    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Best-effort deep link to the subject row this audit entry names, for the screen's "row links
     * to ... the subject" requirement. Kept intentionally small — only the subject types this
     * build's own writers actually produce — rather than a speculative universal resolver. Invoice/
     * refund routes take {companyId}/{invoiceNumber|refundNumber}, not a bare id, so those two
     * branches look the row up (a single indexed PK lookup per rendered row) rather than guessing a
     * URL shape from subject_id alone; a since-deleted subject or an unrecognised subject_type
     * yields null (AuditLogLinker's own established contract: "omit the link, never an error").
     */
    public function subjectUrl(): ?string
    {
        if ($this->subject_id === null || $this->subject_type === null) {
            return null;
        }

        return match ($this->subject_type) {
            'invoice' => $this->urlFor(Invoice::class, 'invoices.show', fn ($m) => [
                'companyId' => $m->company_id, 'invoiceNumber' => $m->invoice_number,
            ]),
            'refund' => $this->urlFor(Refund::class, 'refunds.show', fn ($m) => [
                'companyId' => $m->company_id, 'refundNumber' => $m->refund_number,
            ]),
            'accounting_period' => \Illuminate\Support\Facades\Route::has('accounting.periods.index')
                ? route('accounting.periods.index') : null,
            default => null,
        };
    }

    /**
     * The ledger document viewer for this row's linked transaction ({@see \App\Http\Controllers\
     * JournalEntryController::index()}, which lists every journal_entries line for one
     * transaction) — the "row links to the ledger document viewer" requirement.
     */
    public function transactionUrl(): ?string
    {
        if ($this->transaction_id === null) {
            return null;
        }

        return \Illuminate\Support\Facades\Route::has('journal-entries.index')
            ? route('journal-entries.index', $this->transaction_id)
            : null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(Model):array  $params
     */
    private function urlFor(string $modelClass, string $routeName, callable $params): ?string
    {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            return null;
        }

        $model = $modelClass::withoutGlobalScopes()->find($this->subject_id);

        return $model !== null ? route($routeName, $params($model)) : null;
    }
}
