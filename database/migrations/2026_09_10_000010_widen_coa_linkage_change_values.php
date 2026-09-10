<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-A3 R3-2 — VERIFY-CT-A3-STACK-R2 finding **V1**: `accounting:coa-linkage --rollback` printed
 * *"Undo this run in full"* while restoring only three `accounts` columns, leaving every minted
 * leaf and every purpose mapping the run created in place.
 *
 * Making the undo genuinely full means recording ROW-level before-images — a created
 * `system_accounts` mapping carries a `purpose_code` (varchar 64) and a `service_type` (varchar 32),
 * which do not fit in this table's original `varchar(64)` value columns even singly once they are
 * paired with a label. `CoaLinkage::recordColumnChange()` papered over that with an
 * `mb_substr($value, 0, 64)`, which is a silent truncation on exactly the values a rollback must be
 * able to trust.
 *
 * `text` rather than a longer varchar: these two columns are written once per changed value by a
 * console command and read only by `--rollback`; nothing indexes or sorts them, so the only
 * property that matters is that they are never truncated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coa_linkage_changes', function (Blueprint $table) {
            $table->text('before_value')->nullable()->change();
            $table->text('after_value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('coa_linkage_changes', function (Blueprint $table) {
            $table->string('before_value', 64)->nullable()->change();
            $table->string('after_value', 64)->nullable()->change();
        });
    }
};
