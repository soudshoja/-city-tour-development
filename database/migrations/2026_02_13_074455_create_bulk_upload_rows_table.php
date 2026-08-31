<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotency guard: production's migrations table records a retired
        // sibling migration (2026_02_13_074454_create_bulk_upload_rows_table,
        // deleted from this repo as a mis-ordered duplicate) which already
        // created this table. Laravel therefore considers this migration
        // (074455) still pending on those databases even though the table
        // exists. Guard so this migration is a safe no-op there, while still
        // creating the table normally on a fresh database.
        if (Schema::hasTable('bulk_upload_rows')) {
            return;
        }

        Schema::create('bulk_upload_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bulk_upload_id');
            $table->unsignedInteger('row_number');
            $table->enum('status', ['valid', 'error', 'flagged']);
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->json('raw_data');
            $table->json('errors')->nullable();
            $table->string('flag_reason')->nullable();
            $table->timestamps();

            $table->foreign('bulk_upload_id')->references('id')->on('bulk_uploads')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mirror-guard: if the retired sibling (074454) is the one recorded
        // as having created this table on this database, don't drop it out
        // from under that migration's ownership. Only drop when this
        // migration's own up() was the one that ran on a fresh database.
        // Since we cannot cheaply distinguish "created by 074454" from
        // "created by 074455" at down()-time, and the safe default is to
        // never destroy data on a production-shaped DB, this intentionally
        // stays a straightforward dropIfExists — matching this migration's
        // original (pre-guard) behavior. Rolling back 074455 on a DB where
        // 074454 owns the table will still drop it; that mirrors the
        // pre-existing (non-idempotent) rollback risk and is unchanged here.
        Schema::dropIfExists('bulk_upload_rows');
    }
};
