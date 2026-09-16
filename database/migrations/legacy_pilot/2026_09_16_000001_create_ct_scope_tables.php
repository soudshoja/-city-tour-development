<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CD-PORT — the two audit tables the City Travelers port adds to the pilot's quarantined schema.
 *
 * Like every other migration in this directory it runs ONLY on the `legacy_pilot` connection and
 * ONLY when invoked explicitly:
 *
 *     php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot
 *
 * A bare `php artisan migrate` globs `database/migrations/*_*.php` non-recursively and therefore
 * never sees this file. Nothing here is ever created inside `citycomm_city-tour-test`.
 *
 * ── Why `ct_scope_counter` exists ───────────────────────────────────────────────────────────────
 * CD0 proved that a table's `AUTO_INCREMENT` cannot be lowered while the rows that raised it are
 * still present, and coordinator ruling S-A waives the restore-while-loaded requirement on that
 * evidence. What remains possible, and what the reversal manifest actually relies on, is restoring
 * the counter AFTER the rows are deleted. That restore needs a number, and the only correct number
 * is the one the table carried immediately before the load — not `MAX(id) + 1`, which is a
 * DIFFERENT number whenever the table has a gap at its top end (dev's `accounts` carried
 * AUTO_INCREMENT 1,739 over a live MAX of 1,661: a 77-row gap). Restoring to `MAX(id) + 1` there
 * would hand ids 1,662-1,738 back to the dev application to re-mint, and every one of them is an
 * id production will eventually claim for an account of its own — reintroducing, in miniature,
 * exactly the silent `insert_only` collision the id floor exists to prevent.
 *
 * So `legacy:scope --apply` records each counter before it raises it, and `legacy:unload --apply`
 * restores from this table, re-reading `information_schema.TABLES` afterwards to prove the value
 * actually moved (ruling R-CO5 — an `ALTER`'s exit code is not evidence).
 */
return new class extends Migration
{
    private const CONNECTION = 'legacy_pilot';

    public function up(): void
    {
        Schema::connection(self::CONNECTION)->create('ct_scope_counter', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('table_name', 128);
            $table->string('database_name', 128);
            // The value read from information_schema.TABLES immediately BEFORE this run raised it.
            $table->unsignedBigInteger('auto_increment_before');
            // The highest id the table held at that moment — kept so a reviewer can see the gap
            // between it and auto_increment_before without re-querying a database that has since
            // moved on. 0 when the table was empty.
            $table->unsignedBigInteger('max_id_before')->default(0);
            $table->unsignedBigInteger('id_floor');
            $table->unsignedBigInteger('id_ceiling');
            $table->timestamp('recorded_at')->nullable();

            $table->unique(['company_id', 'database_name', 'table_name'], 'ct_scope_counter_unique');
        });

        Schema::connection(self::CONNECTION)->create('ct_scope_run', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('database_name', 128);
            // 'arm' | 'arm-dry-run' | 'unload' | 'unload-dry-run'
            $table->string('action', 32);
            $table->unsignedBigInteger('id_floor');
            $table->unsignedBigInteger('id_ceiling');
            // Per-table counts/counters for this run, as JSON. Deliberately a blob rather than a
            // normalised child table: it is read by humans and by the phase report, never joined.
            $table->longText('detail')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'action'], 'ct_scope_run_company_action');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('ct_scope_run');
        Schema::connection(self::CONNECTION)->dropIfExists('ct_scope_counter');
    }
};
