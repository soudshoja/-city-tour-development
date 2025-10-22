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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('iata_code', 8)->nullable()->after('logo')->comment('IATA 8-digit agency code');
            $table->string('iata_client_id')->nullable()->after('iata_code');
            $table->string('iata_client_secret')->nullable()->after('iata_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('iata_code', 'iata_client_id', 'iata_client_secret');
        });
    }
};
