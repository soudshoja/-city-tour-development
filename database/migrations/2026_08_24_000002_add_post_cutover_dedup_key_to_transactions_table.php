<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `transactions` half of the P0 payment-race hotfix that used to live in migration
 * 2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php — split out because
 * a raw two-column `UNIQUE (payment_id, reference_type)` index, which that migration originally
 * created, cannot be applied to this table's real history. See that migration's own docblock
 * for the partial-failure (implicit-commit) half of the story; this docblock covers why the
 * approach itself had to change, not just the file split.
 *
 * (a) WHY A RAW UNIQUE (payment_id, reference_type) CAN NEVER APPLY TO CT-SHAPED HISTORY.
 * A production-shaped-data analysis (against local DB `akeed_verify_snapshot`) found CT's real
 * `transactions` table violates that pairing 2,007 times. 98% of those are a
 * notification-row-vs-ledger-row DESIGN COLLISION in the MyFatoorah top-up flow: a gateway
 * failure-notification write and a separate ledger write legitimately share the same
 * (payment_id, reference_type) slot by construction (see
 * PaymentController::firstOrCreateFailureTransaction() and the cross-flip tests in
 * tests/Feature/Accounting/PaymentControllerTransactionDedupTest.php for the live shape of
 * this) — this is NOT double money movement, it is two different rows that were always meant to
 * coexist. Only 2 of the 2,007 are genuine GL double-posts, and both of those are already
 * Suspense-balanced (the double-post's imbalance was already caught and corrected downstream).
 * Adding a raw two-column unique index over this table as-is would fail outright on the first
 * `ALTER TABLE`, and even a one-time cleanup pass would have to individually adjudicate 2,007
 * rows before the index could ever be added — not a safe or fast path, and the owner's stated
 * preference is to never mutate historical rows to make a new constraint fit.
 *
 * (b) HISTORIC ROWS ARE DELIBERATELY EXEMPT. Rather than a raw unique index on the two source
 * columns, this migration adds a nullable STORED generated column, `payment_ref_dedup_key`,
 * computed as:
 *   CASE WHEN payment_id IS NOT NULL AND created_at >= '2026-09-01 00:00:00'
 *        THEN CONCAT(payment_id, ':', reference_type)
 *        ELSE NULL
 *   END
 * and a UNIQUE index over that generated column instead of over the raw pair. Every row created
 * before the cutover timestamp (or with a NULL payment_id at any time) computes a NULL dedup
 * key — and MySQL unique indexes permit unlimited NULLs — so all 2,007 historic violations, and
 * every legitimate NULL-payment_id row, are automatically and permanently exempt. No historical
 * row is ever read, rewritten, or backfilled by this migration.
 *
 * (c) NEW / POST-CUTOVER ROWS GET FULL ENFORCEMENT. The race this whole hotfix exists to close
 * is still live in production (~20 new collisions/month per the same analysis) — dropping
 * enforcement entirely was never on the table. Any row with a non-null payment_id and
 * created_at on or after CUTOVER_TS computes a real, non-null `payment_id:reference_type` key,
 * and the unique index on that column makes a second such row collide exactly as the original,
 * un-scoped design intended — surfaced by PostingService as the typed
 * DuplicatePaymentReferenceException, never a raw QueryException (see
 * App\Exceptions\Accounting\DuplicatePaymentReferenceException and
 * PostingService::isPaymentReferenceTypeRaceViolation(), both updated to recognise this new
 * index's name alongside the old raw index's name).
 *
 * CUTOVER_TS = '2026-09-01 00:00:00' is a LITERAL constant baked directly into the generated
 * column's SQL expression below — not a config value, not resolved from any app setting, and
 * not computed at migration-run time — so the cutover point is identical and fixed on every
 * environment this migration ever runs against, regardless of when it happens to run there.
 *
 * (d) FUTURE PATH BACK TO A RAW KEY. Nothing here forecloses eventually migrating to a raw
 * `(payment_id, reference_type)` unique index once the 2,007 historic rows have been through a
 * deliberate, guarded historic-dedup pass (adjudicating the notification-vs-ledger design
 * collision case by case, and re-confirming the 2 genuine double-posts stay Suspense-balanced).
 * That pass, if it ever happens, is a separate future migration; this one commits to nothing
 * about historic rows beyond leaving them untouched.
 *
 * (e) 2026-09-02 PORTABILITY FIX — MariaDB 10.11 REJECTS THE GENERATED COLUMN; TRIGGERS NOW
 * MAINTAIN IT. The column above was originally a STORED GENERATED column. On MariaDB 10.11.19
 * (production, and reproduced on a clean `mariadb:10.11` container) creating it fails with:
 *     SQLSTATE[HY000] 1901: Function or expression 'payment_id' cannot be used in the
 *     GENERATED ALWAYS AS clause of `payment_ref_dedup_key`
 * MariaDB >= 10.5 refuses any generated-column (and any CHECK-constraint) expression that
 * references a column participating in a foreign key with a SET NULL referential action — the
 * referential action would rewrite the column behind the expression's back.
 * `transactions.payment_id` is exactly that (`FOREIGN KEY (payment_id) REFERENCES payments(id)
 * ON DELETE SET NULL`). MariaDB 10.4 (our XAMPP fence) and MySQL 8 have no such validation,
 * which is why this passed locally and only surfaced against 10.11. It is not a syntax problem
 * and no rewrite of the CASE expression avoids it. Same failure family as the
 * `chk_payment_owner` CHECK on `payments` — see
 * 2026_09_02_000010_enforce_payment_owner_xor_via_triggers.php.
 *
 * The fix keeps the design of (a)-(d) byte-for-byte at the data level and changes only HOW the
 * column is maintained: `payment_ref_dedup_key` is now an ORDINARY nullable column, kept in
 * lockstep with the identical CASE expression by BEFORE INSERT and BEFORE UPDATE triggers that
 * assign `NEW.payment_ref_dedup_key` unconditionally. Because the triggers overwrite whatever a
 * writer supplied, the column is as un-writable in practice as a generated one: no application
 * code can set it, historic and NULL-payment_id rows still compute NULL, post-cutover rows still
 * compute `payment_id:reference_type`, and the UNIQUE index over the column still does all the
 * enforcing — the index name, and therefore
 * PostingService::isPaymentReferenceTypeRaceViolation()'s detection of it, is unchanged.
 * Existing rows are backfilled with one guarded UPDATE using the same expression before the
 * unique index is added, so an already-populated database ends up in exactly the state the
 * generated column would have produced. `down()` drops the triggers as well as the index/column.
 * OPERATIONAL NOTE: `transactions` now carries BEFORE INSERT/UPDATE triggers — bulk loads run
 * row-by-row through them, and a dump/restore replays the triggers as recorded (no explicit
 * DEFINER is set below, so they are created as CURRENT_USER and install under any DB account).
 *
 * Chosen index name: `transactions_payment_ref_dedup_key_unique` (explicit, not autogenerated,
 * so it is stable across reruns and across environments — matches this repo's existing
 * convention of always naming multi-purpose unique indexes explicitly, e.g.
 * `transactions_company_doctype_refnum_unique` in migration
 * 2026_08_24_120008_add_unique_reference_number_to_transactions_table.php).
 */
return new class extends Migration
{
    private const COLUMN_NAME = 'payment_ref_dedup_key';

    private const INDEX_NAME = 'transactions_payment_ref_dedup_key_unique';

    /** Literal, fixed cutover instant — see class docblock point (b)/(c). Baked verbatim into
     *  the generated column's SQL expression in up(); never read from config or computed. */
    private const CUTOVER_TS = '2026-09-01 00:00:00';

    /**
     * Run the migrations.
     *
     * Two separate guarded Schema::table() calls (column add, then index add) rather than one —
     * MySQL DDL implicit-commits per statement, so this deliberately mirrors the exact
     * partial-failure shape migration 2026_08_24_000001 was rewritten to survive: if the column
     * add lands but the index add fails/never runs, a rerun must not error on the column (it
     * already exists) and must still attempt the index. Schema::hasColumn()/Schema::hasIndex()
     * are both confirmed present on this Laravel version (v11.39.1); no raw fallback needed.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', self::COLUMN_NAME)) {
            Schema::table('transactions', function (Blueprint $table) {
                // Ordinary nullable column, maintained by the triggers installed below — see
                // class docblock (e) for why this is not a STORED generated column any more.
                $table->string(self::COLUMN_NAME, 64)
                    ->nullable()
                    ->after('reference_type');
            });
        }

        if ($this->isMySql()) {
            $this->installTriggers();

            // Backfill with the SAME expression the triggers apply, so a database that already
            // holds rows lands in exactly the state the generated column would have produced.
            // A no-op on a virgin database. Historic / NULL-payment_id rows stay NULL.
            DB::statement(
                'UPDATE `transactions` SET `'.self::COLUMN_NAME.'` = '.self::keyExpression('`transactions`.')
            );
        }

        if (! Schema::hasIndex('transactions', self::INDEX_NAME)) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unique(self::COLUMN_NAME, self::INDEX_NAME);
            });
        }
    }

    /**
     * The dedup-key expression, verbatim from the original generated-column definition. Rendered
     * against a caller-supplied column prefix ('NEW.' inside the triggers, the table name in the
     * backfill UPDATE) so the trigger bodies and the backfill can never drift apart.
     */
    private static function keyExpression(string $prefix): string
    {
        return 'CASE WHEN '.$prefix.'`payment_id` IS NOT NULL '
            ."AND {$prefix}`created_at` >= '".self::CUTOVER_TS."' "
            .'THEN CONCAT('.$prefix.'`payment_id`, \':\', '.$prefix.'`reference_type`) '
            .'ELSE NULL END';
    }

    /**
     * Idempotent: DROP TRIGGER IF EXISTS before each CREATE, and no explicit DEFINER clause, so
     * the triggers are recorded as CURRENT_USER and reinstall cleanly under any DB account.
     */
    private function installTriggers(): void
    {
        $expr = self::keyExpression('NEW.');
        $column = self::COLUMN_NAME;

        foreach (['insert', 'update'] as $event) {
            $name = "transactions_{$column}_before_{$event}";

            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared(
                "CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON `transactions` "
                ."FOR EACH ROW SET NEW.`{$column}` = {$expr}"
            );
        }
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    /**
     * Reverse the migrations.
     *
     * Symmetric guard, reverse order (drop the index before the column it depends on) — safe to
     * run twice or against a partially-applied state.
     */
    public function down(): void
    {
        if ($this->isMySql()) {
            DB::unprepared('DROP TRIGGER IF EXISTS transactions_'.self::COLUMN_NAME.'_before_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS transactions_'.self::COLUMN_NAME.'_before_update');
        }

        if (Schema::hasIndex('transactions', self::INDEX_NAME)) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropUnique(self::INDEX_NAME);
            });
        }

        if (Schema::hasColumn('transactions', self::COLUMN_NAME)) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn(self::COLUMN_NAME);
            });
        }
    }
};
