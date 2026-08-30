<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.I fix round (verify finding: "file_uploads.import_hash does not actually replace the
 * filename-only dedupe at the upload() call sites" -- confirmed against
 * TaskController::upload()'s merge-supplier ('batches') branch, which never read/wrote
 * import_hash at all).
 *
 * A merge-supplier upload can bundle SEVERAL source PDFs into ONE `file_uploads` row (the
 * merged output). `import_hash` alone (unique per company) can only carry one hash per row, so
 * it is used for the merged output's own content hash; `source_hashes` (nullable JSON array)
 * additionally carries each individual source file's content hash, mirroring the existing
 * `source_files` (names) column -- this is what lets a single source file, re-delivered later
 * inside a *different* batch/combination under a *different* name, still be recognised as the
 * same content that was already imported.
 *
 * Additive, nullable; existing rows backfill NULL (no retroactive computation -- original source
 * file bytes are not guaranteed to still be on disk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->json('source_hashes')->nullable()->after('import_hash');
        });
    }

    public function down(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->dropColumn('source_hashes');
        });
    }
};
