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
        Schema::table('bulk_upload_rows', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_upload_rows', function (Blueprint $table) {
            $table->dropColumn('payment_id');
        });
    }
};
