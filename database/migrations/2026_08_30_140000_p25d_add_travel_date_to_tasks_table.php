<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6, IFRS 15) — the release-trigger date
 * `App\Services\Accounting\RevenueRecognitionService` reads for an `at_travel` service's deferred
 * sale. Additive, nullable — every existing row (and every task type with no dedicated detail
 * table, see this migration's own docblock note below) is unaffected.
 *
 * WHY A NEW COLUMN, NOT AN EXISTING ONE (repo-wide search, 2026-08-30): none of the 12 task types
 * this build recognises has a uniform "when does the service actually happen" field. `tasks`
 * itself has no such column (`issued_date`/`expiry_date` describe the DOCUMENT, not the service).
 * Only `hotel` has a dedicated detail table with a real date (`task_hotel_details.check_in`) — the
 * four PRINCIPAL-basis, `at_travel`-by-default types this wave's own default actually turns
 * deferral on for (`tour`, `cruise`, `car`, `event`) have NO detail table at all. A single,
 * type-agnostic `tasks.travel_date` is therefore the only field every feeder can populate
 * uniformly, and the only field `RevenueRecognitionService` needs to query — a caller that already
 * knows a task's check-in/departure date (e.g. a future hotel-detail sync) sets this column
 * directly rather than this wave inventing a per-type join. A task with no `travel_date` set
 * simply never becomes due for recognition — see `RevenueRecognitionService::findDue()`'s own
 * docblock — reported, not silently guessed at (never falls back to `issued_date`, which is a
 * document date, not a service date, and would misdate the P&L exactly like the `created_at`
 * fallback BUG-C4 fixed for posting_date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('travel_date')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('travel_date');
        });
    }
};
