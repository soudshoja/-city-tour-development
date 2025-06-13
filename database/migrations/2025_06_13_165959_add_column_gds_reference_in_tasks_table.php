<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('gds_reference')->after('reference')->nullable()->comment('GDS Reference Number');
            $table->string('airline_reference')->after('gds_reference')->nullable()->comment('Airline Reference Number');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('gds_reference');
            $table->dropColumn('airline_reference');
        });
    }
};
