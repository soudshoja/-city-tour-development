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
        Schema::table('task_flight_details', function (Blueprint $table) {
            $table->foreign('country_id_from')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('country_id_to')->references('id')->on('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_flight_details', function (Blueprint $table) {
            $table->dropForeign(['country_id_from']);
            $table->dropForeign(['country_id_to']);
        });
    }
};
