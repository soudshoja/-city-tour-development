<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Admin-visibility backing for PostingService::post()'s SupersededIdempotencyKeyException path
     * (P1 fix round 4, BLOCKING #2 — .planning/P1-VERIFICATION-FINDINGS.json: "reusing an
     * idempotency key whose transaction was soft-deleted silently posts NOTHING and returns the
     * soft-deleted document as success"). One row per REJECTED post() attempt where the caller's
     * idempotency key collided with a transaction that turned out to be soft-deleted — i.e. every
     * case this build refuses to silently resurrect. Never written on the ordinary paths (a clean
     * new post, or a genuine concurrent-race idempotent return of a LIVE existing document).
     *
     * DELIBERATELY plain columns, not a JSON blob (`notifications.data` already exists as a
     * per-user JSON-blob mechanism — see App\Models\Notification — and was rejected for this
     * specific need: it requires a `user_id`, which this system-level/webhook-reachable rejection
     * has no natural one for, and the requirement is that an admin can FILTER this list by
     * company_id / key / resolution_status via real indexed columns, not by scanning JSON).
     *
     * DURABILITY (the other half of BLOCKING #2's requirement — "the event must be recorded even
     * though the exception propagates and the surrounding post() transaction rolls back"): this
     * table is written EXCLUSIVELY through the `accounting_audit` connection (see
     * config/database.php and App\Models\IdempotencyKeyRejection::$connection), a second PDO
     * connection to the SAME physical database as the app's normal `mysql` connection, so a row
     * written here commits independently of whatever the caller's own (about-to-roll-back)
     * transaction is doing on the primary connection. See IdempotencyKeyRejection's own docblock
     * for the full reasoning. This migration itself runs on the DEFAULT connection like every other
     * migration (Laravel migrations always do), which is fine: `accounting_audit`, `mysql`, and (in
     * tests) `mysql_testing` all resolve to the exact same physical database, so the table this
     * creates is visible to all three regardless of which one created it.
     */
    public function up(): void
    {
        Schema::create('idempotency_key_rejections', function (Blueprint $table) {
            $table->id();

            // DELIBERATELY NO FK to `companies` — same reasoning as dead_transaction_id below, and
            // discovered the SAME way (running the test suite): under RefreshDatabase, a row this
            // table's INSERT would reference (e.g. the `companies` row this attempt's company_id
            // points to) was itself INSERTed earlier in the SAME still-open test transaction on the
            // PRIMARY connection — and InnoDB holds an exclusive lock on any row a transaction
            // inserted until that transaction commits or rolls back, for the FULL LIFETIME of that
            // transaction, not just while an explicit lockForUpdate() is held. A real FK here would
            // make this INSERT (on the separate `accounting_audit` connection) request a shared lock
            // on that still-exclusively-locked row and block until the primary transaction ends —
            // exactly the same deadlock/lock-wait-timeout shape as dead_transaction_id's FK caused,
            // just triggered by test-harness row-locking rather than an explicit lockForUpdate()
            // call. Generalizing the fix rather than special-casing it to dead_transaction_id alone:
            // NO column on this table takes a real FK, so nothing this table's writer does can ever
            // block on a row the SAME logical caller might still be holding open elsewhere.
            $table->unsignedBigInteger('company_id');

            // Matches transactions.idempotency_key's own definition (migration
            // 2026_08_24_120004): plain string, no explicit length (varchar(255)).
            $table->string('idempotency_key');

            // The soft-deleted transaction the rejected attempt collided with. DELIBERATELY NO FK
            // to `transactions` — an earlier revision of this migration added
            // `->constrained('transactions')` (real FK) reasoning that the row this points to
            // always exists (soft delete only sets deleted_at, never removes the row) so a real FK
            // seemed strictly safe. It was NOT: proven by running the test suite, that FK caused a
            // genuine cross-connection deadlock/lock-wait-timeout. IdempotencyKeyRejection is
            // written via the SEPARATE `accounting_audit` connection specifically so it survives
            // the caller's own transaction rolling back (see that model's own docblock) — but the
            // very row it references here is, at the moment it is written, still held under an
            // EXCLUSIVE `SELECT ... FOR UPDATE` lock by that SAME still-open caller transaction on
            // the PRIMARY connection (PostingService::post()'s header-insert catch block locks
            // exactly this row before deciding to record + throw). A real FK forces MySQL to take a
            // shared lock on the referenced parent row as part of the INSERT's referential-integrity
            // check — on a genuinely different connection/session, that shared-lock request has to
            // wait for the primary connection's exclusive lock to release, which only happens once
            // the primary connection rolls back — which only happens once THIS INSERT returns. Two
            // connections each waiting on the other via the same row is a real deadlock (surfaced by
            // MySQL as `Illuminate\Database\QueryException` / `DeadlockException`, "Lock wait timeout
            // exceeded", after ~innodb_lock_wait_timeout seconds — reproduced directly by this
            // migration's own test coverage before this fix). A bare unsignedBigInteger (same
            // pattern serial_schemas.branch_id already uses, for an analogous reason — see that
            // migration's own docblock) avoids the FK-triggered parent-row check entirely: the value
            // is still always a real `transactions.id` by construction (only ever written by
            // PostingService, from a row it just read), just not DB-enforced.
            $table->unsignedBigInteger('dead_transaction_id');

            // Captured at record-time from the dead row's own deleted_at — kept as a plain
            // timestamp copy (not re-derived by joining transactions later) so this table stays
            // meaningful even if that transaction is ever hard-deleted or its deleted_at changes.
            $table->timestamp('dead_transaction_deleted_at')->nullable();

            // The attempted document's total (Σdebit, already balance-checked by the time this
            // fires — step 4 runs before step 6/7) — i.e. what would have been posted had the
            // engine wrongly accepted the collision. Same precision as transactions.total_debit/
            // total_credit (migration 2026_08_24_120004: decimal(18,3)).
            $table->decimal('attempted_amount', 18, 3);

            // INV/RV/PV/JV/CRN/DBN/OJV/REV — matches transactions.doc_type's own width (migration
            // 2026_08_24_120004: string(8)). Nullable purely for schema robustness against a
            // caller-supplied docType this table doesn't otherwise validate; PostingService always
            // has a real one in scope when it writes this row.
            $table->string('attempted_doc_type', 8)->nullable();

            // The userId in scope on the rejected post() call, if any (mirrors
            // transactions.created_by's own nullable unsignedBigInteger, no FK — same rationale:
            // system/webhook-originated posts often have none).
            $table->unsignedBigInteger('attempted_by')->nullable();

            // The one column the task's own requirement names explicitly ("queryable ... and
            // resolution status"): lets an admin filter "still needs a human decision" from
            // "already looked at". No workflow/UI ships in this round (routes/* and every
            // controller are out of scope for this file's mission) — the column exists so that
            // work has a real place to write to, rather than requiring a follow-up migration.
            $table->string('resolution_status', 16)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'resolution_status']);
            $table->index(['idempotency_key']);
            // No FK on dead_transaction_id (see that column's own comment above), so this index is
            // added explicitly — an FK would normally supply one automatically.
            $table->index(['dead_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_key_rejections');
    }
};
