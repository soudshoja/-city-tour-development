<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-visibility record for a post() attempt PostingService REJECTED because its idempotency key
 * collided with a soft-deleted transaction (P1 fix round 4, BLOCKING #2 —
 * .planning/P1-VERIFICATION-FINDINGS.json). See App\Exceptions\Accounting\
 * SupersededIdempotencyKeyException, which is always thrown in the same call that creates one of
 * these rows, and database/migrations/2026_08_24_120007_create_idempotency_key_rejections_table.php
 * for the full column-by-column rationale.
 *
 * `$connection = 'accounting_audit'` IS THE FIX FOR THE ROUND'S OWN "must survive the surrounding
 * transaction rollback" REQUIREMENT — not a stylistic choice. PostingService::post() writes this
 * row from deep inside its own `DB::transaction()` closure, in the exact branch that is ABOUT TO
 * THROW and roll that transaction back. A row inserted on the SAME connection as that open
 * transaction (i.e. the default `mysql`/`mysql_testing` connection) would be undone by that same
 * rollback the instant the exception propagates — the record would exist in PHP for a few
 * microseconds and then never have happened, which defeats the one thing this class exists to
 * guarantee. Setting `$connection` here routes EVERY write (and every future admin-dashboard read)
 * through `accounting_audit` — a second, independent PDO connection to the identical physical
 * database (see config/database.php's own comment on that connection) — so a commit here is real
 * and permanent regardless of what the caller's own transaction does immediately afterward.
 * Concretely: `IdempotencyKeyRejection::create([...])` issues a single autocommit INSERT on a
 * connection with no open transaction of its own, so it commits synchronously, before this method
 * returns — by the time PostingService's catch block executes `throw`, the row is already durable.
 *
 * Deliberately NOT using `DB::afterCommit()`/queued-listener style deferral: those patterns only
 * fire on a COMMIT, and the whole scenario this class exists for is a transaction that is about to
 * ROLL BACK — an after-commit hook registered inside a transaction that rolls back simply never
 * runs, which would silently drop the very record this round's owner asked to be guaranteed.
 */
class IdempotencyKeyRejection extends Model
{
    use HasFactory;

    /** See class docblock — this is the load-bearing line, not incidental configuration. */
    protected $connection = 'accounting_audit';

    protected $fillable = [
        'company_id',
        'idempotency_key',
        'dead_transaction_id',
        'dead_transaction_deleted_at',
        'attempted_amount',
        'attempted_doc_type',
        'attempted_by',
        'resolution_status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'dead_transaction_deleted_at' => 'datetime',
        'attempted_amount' => 'float',
        'resolved_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The soft-deleted transaction this rejection was about. withoutGlobalScopes() so a caller
     * doesn't need to remember Transaction is soft-deleting to load the very row this table exists
     * to point at (the whole reason this record exists is that the target IS soft-deleted).
     */
    public function deadTransaction()
    {
        return $this->belongsTo(Transaction::class, 'dead_transaction_id')->withoutGlobalScopes();
    }

    /** Filter surface for a future admin dashboard — "still needs a human decision". */
    public function scopeUnresolved($query)
    {
        return $query->where('resolution_status', 'open');
    }
}
