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
        Schema::table('supplier_companies', function (Blueprint $table) {
          $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
          $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['account_id']);
        });
    }
};
