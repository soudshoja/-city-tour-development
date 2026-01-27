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
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_partial_id')->nullable()->after('invoice_id');
            $table->foreign('invoice_partial_id')->references('id')->on('invoice_partials')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->dropForeign(['invoice_partial_id']);
            $table->dropColumn('invoice_partial_id');
        });
    }
};
