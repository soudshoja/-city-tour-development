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
        Schema::create('task_hotel_details_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('hotel_id');
            $table->dateTime('booking_time')->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->string('room_reference')->nullable();
            $table->string('room_number')->nullable();
            $table->string('room_type')->nullable();
            $table->integer('room_amount')->nullable();
            $table->text('room_details')->nullable();
            $table->string('room_promotion')->nullable();
            $table->decimal('rate', 8, 2)->nullable();
            $table->string('meal_type')->nullable();
            $table->string('is_refundable')->nullable();
            $table->text('supplements')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_hotel_details_emails');
    }
};
