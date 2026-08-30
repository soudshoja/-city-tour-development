<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.I "Importer contract" item 3 (w6-brief.md; importer-status-contract.md's own "Bulk upload"
 * finding: idempotency today is an exact `(supplier_id, company_id, file_name)` STRING match --
 * "filename string, not a content hash"). `file_uploads.import_hash` = sha256 of the uploaded
 * file's raw content, computed by `FileUpload::hashContent()`. Additive/nullable -- existing rows
 * are backfilled NULL (their original content is not guaranteed to still be on disk, so a
 * retroactive hash cannot be computed reliably; only new uploads populate this column).
 *
 * Unique per `(company_id, import_hash)` -- the SAME file content re-uploaded under a renamed
 * filename is now caught (closing the exact gap the importer-status-contract.md finding names);
 * two different companies uploading coincidentally-identical file bytes are NOT the same
 * real-world duplicate, so the key is company-scoped, not global. NULL values are, as with
 * `tasks.import_key`, unlimited under this unique index (MySQL does not treat NULL = NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->char('import_hash', 64)->nullable()->after('file_name');
            $table->unique(['company_id', 'import_hash'], 'file_uploads_company_import_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->dropUnique('file_uploads_company_import_hash_unique');
            $table->dropColumn('import_hash');
        });
    }
};
