<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->foreignId('prebook_id')->nullable()->change();
            $table->string('supplier_booking_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->foreignId('prebook_id')->nullable(false)->change();
            $table->string('supplier_booking_id')->nullable(false)->change();
        });
    }
};
