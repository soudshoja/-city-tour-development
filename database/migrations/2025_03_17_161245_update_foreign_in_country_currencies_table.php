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
        Schema::table('country_currencies', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies')->cascadeOnDelete(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('country_currencies', function (Blueprint $table) {
          $table->dropForeign(['currency_id']);
        });
    }
};
