<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dotwai_bookings', function (Blueprint $table) {
            // CERT-13: full list of DOTW booking codes returned by confirmBooking.
            // For 1-room bookings: [primary booking_ref]. For N-room bookings: N codes.
            // We keep booking_ref (singular) populated with the FIRST code for
            // backwards compatibility with existing voucher/invoice paths.
            $table->json('booking_refs')->nullable()->after('booking_ref');
        });
    }

    public function down(): void
    {
        Schema::table('dotwai_bookings', function (Blueprint $table) {
            $table->dropColumn('booking_refs');
        });
    }
};
