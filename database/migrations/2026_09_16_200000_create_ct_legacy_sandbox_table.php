<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CD-PORT — the sandbox marker table.
 *
 * This is the ONLY migration this lane adds to the application migration chain, and it is
 * deliberately inert: it creates one empty table and nothing else. An empty
 * `ct_legacy_sandbox` is explicitly NOT a declaration — {@see \App\Services\Onboarding\Scope\
 * LegacySandboxGuard::assertSandbox()} refuses a table with no marker row by name. So running this
 * migration on `citycomm_city-tour-test` costs that database one empty table and changes nothing
 * about how anything behaves there.
 *
 * ── Why a migration rather than a `Schema::create()` in the marking command ──────────────────────
 * The first version created the table at runtime, inside `legacy:sandbox --mark`. That is DDL, and
 * DDL implicitly commits in MySQL — which quietly destroyed the surrounding transaction. It showed
 * up as `SQLSTATE[42000] … SAVEPOINT trans2 does not exist` in the one test where the command under
 * test rolls its own transaction back, and the same hazard would exist in production any time the
 * marker were stamped inside a transaction. A guard that can invalidate a transaction as a side
 * effect of arming itself is the wrong shape.
 *
 * ── What declares a sandbox ─────────────────────────────────────────────────────────────────────
 * A ROW here, naming the schema it was stamped for, plus `LEGACY_SANDBOX_DATABASE` in the
 * environment naming that same schema. Both, independently. The row travels with the data, so a
 * dump of a sandbox restored under another name disagrees with its own live name and is refused
 * until somebody re-stamps it deliberately. See the guard's class docblock for the two adversarial
 * verification rounds that made this gate necessary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ct_legacy_sandbox', function (Blueprint $table) {
            $table->id();
            // The schema this marker was stamped FOR. Compared against the live database name on
            // every legacy:* command — that comparison is what makes a restored copy refuse.
            $table->string('database_name', 128);
            $table->string('token', 64);
            $table->string('note', 255)->nullable();
            $table->timestamp('marked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ct_legacy_sandbox');
    }
};
