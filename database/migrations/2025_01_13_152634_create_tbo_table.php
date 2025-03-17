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
        Schema::create('tbo', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code');
            $table->string('hotel_code');
            $table->string('hotel_name');
            $table->string('room_name');
            $table->integer('room_quantity');
            $table->string('inclusion');
            $table->string('currency');
            $table->string('day_rates');
            $table->float('total_fare');
            $table->float('total_tax');
            $table->string('extra_guest_charges');
            $table->string('room_promotion');
            $table->string('cancel_policies')->nullable();
            $table->string('meal_type');
            $table->boolean('is_refundable');
            $table->boolean('with_transfer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_b_o_s');
    }
};
