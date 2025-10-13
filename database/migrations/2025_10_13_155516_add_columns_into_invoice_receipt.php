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
        Schema::table('invoice_receipt', function (Blueprint $table) {
            $table->string('type')->after('id');
            $table->integer('account_id')->nullable()->after('invoice_id');
            $table->integer('credit_id')->nullable()->after('account_id');
            $table->string('status')->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_receipt', function (Blueprint $table) {
            $table->dropColumn('type', 'account_id', 'credit_id', 'status');
        });
    }
};
