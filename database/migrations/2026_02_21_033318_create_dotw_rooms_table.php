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
        Schema::create('dotw_rooms', function (Blueprint $table) {
            $table->id();

            // Foreign key to pre-booking
            $table->foreignId('dotw_preboot_id')
                ->constrained('dotw_prebooks')
                ->onDelete('cascade')
                ->comment('Reference to parent DOTW pre-booking');

            // Room tracking
            $table->integer('room_number')->comment('Room sequence number (0-indexed)');

            // Occupancy
            $table->integer('adults_count')->default(1);
            $table->integer('children_count')->default(0);
            $table->json('children_ages')->nullable()->comment('Array of child ages');

            // Passenger information
            $table->string('passenger_nationality')->nullable()->comment('DOTW country code');
            $table->string('passenger_residence')->nullable()->comment('Country of residence code');

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('dotw_preboot_id');
            $table->index('room_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dotw_rooms');
    }
};
