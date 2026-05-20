<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dotwai_bookings', function (Blueprint $table) {
            // CERT-10: Snapshot of validForOccupancy from getRooms blocking response.
            // JSON: associative array (or indexed by room runno for multi-room bookings).
            // Each entry shape: { adults:int, children:int, childrenAges:string|int[], extraBed:int, extraBedOccupant:?string }.
            // Null when the rate had no changedOccupancy — confirmBooking then uses rooms_data values directly.
            $table->json('valid_for_occupancy')->nullable()->after('rooms_data');
        });
    }

    public function down(): void
    {
        Schema::table('dotwai_bookings', function (Blueprint $table) {
            $table->dropColumn('valid_for_occupancy');
        });
    }
};
