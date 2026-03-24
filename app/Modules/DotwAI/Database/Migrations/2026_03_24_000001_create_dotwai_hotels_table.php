<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the dotwai_hotels table for hotel static data.
 *
 * Populated via the dotwai:import-hotels artisan command from
 * DOTW Excel/CSV files. Used for fuzzy name matching to resolve
 * natural text queries to DOTW hotel IDs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dotwai_hotels', function (Blueprint $table) {
            $table->id();
            $table->string('dotw_hotel_id')->unique()->index();
            $table->string('name')->index();
            $table->string('city')->index();
            $table->string('country');
            $table->tinyInteger('star_rating')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dotwai_hotels');
    }
};
