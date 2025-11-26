<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbo', function (Blueprint $table) {
            $table->foreignId('hotel_booking_id')->nullable()->after('id')->constrained('hotel_bookings')->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::table('tbo', function (Blueprint $table) {
            $table->dropForeign(['hotel_booking_id']);
            $table->dropColumn('hotel_booking_id');
        });
    }
};
