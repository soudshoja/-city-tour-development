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
            // Add new foreign key columns (temporary, alongside old columns)
            $table->unsignedBigInteger('airport_from_id')->nullable()->after('airport_from');
            $table->unsignedBigInteger('airport_to_id')->nullable()->after('airport_to');
            $table->unsignedBigInteger('airline_id_new')->nullable()->after('airline_id');

            // Add foreign key constraints
            $table->foreign('airport_from_id')->references('id')->on('airports')->onDelete('set null');
            $table->foreign('airport_to_id')->references('id')->on('airports')->onDelete('set null');
            $table->foreign('airline_id_new')->references('id')->on('airlines')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_flight_details', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['airport_from_id']);
            $table->dropForeign(['airport_to_id']);
            $table->dropForeign(['airline_id_new']);

            // Drop columns
            $table->dropColumn(['airport_from_id', 'airport_to_id', 'airline_id_new']);
        });
    }
};
