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
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prebook_id');
            $table->foreign('prebook_id')->references('id')->on('prebookings');
            $table->string('supplier_booking_id')->unique();
            $table->string('client_ref');
            $table->string('status');
            $table->decimal('price', 10, 2);
            $table->string('currency');
            $table->dateTime('booking_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};
