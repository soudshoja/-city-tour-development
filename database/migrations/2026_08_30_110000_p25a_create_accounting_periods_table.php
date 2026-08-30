<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.A (p2_5-brief.md §P2.5.A; doc 11 §P5.1; period-lock-design.md §2/§14): the calendar-range
 * period-control table `PeriodGuard::assertOpen()` (a documented P1 no-op stub until now) resolves
 * against. One row per company per accounting period.
 *
 * Column set matches the brief's literal list (company_id, year, month, status, closed_by,
 * closed_at, reopened_by, reopen_reason, timestamps) plus one documented addition:
 *   - `reopened_at` — not named in the brief's own column list, but required by both the design
 *     doc (§5: "writes `reopened_by`/`reopened_at`, audit-logged") and doc 22 §5's reopen fields
 *     (`reopened_by`/`reopened_at`/`reopen_reason`). Additive, nullable — omitting it would leave
 *     the reopen action unable to record *when* without overloading `closed_at` or `updated_at`
 *     for two different meanings.
 *
 * `year`/`month` (plain integers, not a `period_start`/`period_end` date-range pair as doc 11's
 * older §P5.1 sketch used) per this wave's binding brief, which supersedes that sketch's mechanics
 * for this build. `month` is a sentinel-bearing tinyint rather than nullable: for
 * `accounting.period.length = monthly` (default) it is 1-12; for `= annual` it is
 * `AccountingPeriod::ANNUAL_MONTH` (0), representing the whole year as a single lockable unit --
 * this keeps the `unique(company_id, year, month)` index meaningful under both modes (MySQL does
 * NOT enforce uniqueness among NULLs, so a nullable month would have let a company accumulate
 * multiple "whole year" rows for the same year under annual mode).
 *
 * No rows are seeded by this migration -- `accounting:periods:init` (a separate, idempotent
 * command) populates them per company. PeriodGuard treats a MISSING row as open (see that class's
 * own docblock) precisely so this additive migration changes no existing behaviour for any
 * company that hasn't been initialised yet, and so every accounting test that predates this wave
 * keeps passing without needing to provision period rows itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            // 1-12 under monthly length; 0 (AccountingPeriod::ANNUAL_MONTH) under annual length.
            $table->unsignedTinyInteger('month');
            $table->enum('status', ['open', 'soft_closed', 'locked'])->default('open');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
