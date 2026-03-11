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
        Schema::table('bulk_uploads', function (Blueprint $table) {
            $table->json('invoice_ids')->nullable()->after('error_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_uploads', function (Blueprint $table) {
            $table->dropColumn('invoice_ids');
        });
    }
};
