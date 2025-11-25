<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_booking_rooms', function (Blueprint $table) {
            $table->boolean('disabled')->default(false)->after('occupancy');
        });
    }

    public function down(): void
    {
        Schema::table('request_booking_rooms', function (Blueprint $table) {
            $table->dropColumn('disabled');
        });
    }
};
