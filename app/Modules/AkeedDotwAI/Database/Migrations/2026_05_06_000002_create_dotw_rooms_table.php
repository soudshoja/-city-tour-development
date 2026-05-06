<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the dotw_rooms table.
 *
 * Each row represents a single room within a DOTW pre-booking session.
 * A dotw_prebook record may contain one or more rooms (multi-room bookings).
 * Rows are deleted automatically when the parent dotw_prebooks row is removed.
 *
 * room_runno is a zero-based index matching the order of rooms in the original
 * DOTW search/prebook response (0 = first room, 1 = second room, …).
 *
 * The unique constraint on (dotw_prebook_id, room_runno) prevents duplicate
 * room positions from being inserted for the same pre-booking.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dotw_rooms', function (Blueprint $table) {
            $table->id();

            // Parent pre-booking record.
            $table->unsignedBigInteger('dotw_prebook_id');
            $table->foreign('dotw_prebook_id')
                ->references('id')
                ->on('dotw_prebooks')
                ->cascadeOnDelete();

            // Zero-based position of this room in the multi-room booking.
            $table->integer('room_runno');

            // Human-readable room name returned by DOTW.
            $table->string('room_name');

            // Occupancy code used in the DOTW request (e.g. adult/child pattern).
            $table->integer('adults_code');

            // Actual number of adults after DOTW occupancy confirmation.
            $table->integer('actual_adults');

            // Children ages as requested in the search query.
            $table->json('children_ages');

            // Children ages as confirmed by DOTW in the prebook response.
            $table->json('actual_children_ages');

            // Number of extra beds requested (0 = none).
            $table->integer('extra_bed')->default(0);

            // Meal plan / board basis code (e.g. BB, HB, AI).
            $table->string('meal_type');

            $table->timestamps();

            // Prevents duplicate room positions within a single pre-booking.
            $table->unique(
                ['dotw_prebook_id', 'room_runno'],
                'dotw_rooms_prebook_runno_unique'
            );
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
