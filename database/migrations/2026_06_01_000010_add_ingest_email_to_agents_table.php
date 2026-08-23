<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('agents', 'ingest_email')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->string('ingest_email')->nullable()->after('email');
            $table->index('ingest_email');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('agents', 'ingest_email')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex(['ingest_email']);
            $table->dropColumn('ingest_email');
        });
    }
};
