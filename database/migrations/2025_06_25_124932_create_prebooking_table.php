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
        Schema::create('prebookings', function (Blueprint $table) {
            $table->id();
            $table->string('prebook_key')->unique()->nullable();
            $table->string('telephone')->nullable();
            $table->text('availability_token')->nullable();
            $table->text('package_token')->nullable();
            $table->unsignedBigInteger('hotel_id');
            $table->text('room_token')->nullable();
            $table->string('room_name')->nullable();
            $table->boolean('non_refundable')->nullable();
            $table->string('board_basis')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->date('checkin')->nullable();
            $table->date('checkout')->nullable();
            $table->integer('duration')->nullable();
            $table->json('occupancy')->nullable();
            $table->dateTime('autocancel_date')->nullable();
            $table->json('cancel_policy')->nullable();
            $table->json('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prebookings');
    }
};
