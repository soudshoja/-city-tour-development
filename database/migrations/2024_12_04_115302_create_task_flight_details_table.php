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
        Schema::create('task_flight_details', function (Blueprint $table) {
            $table->id();
            $table->float('farebase', 8, 2)->nullable();
            $table->dateTime('departure_time')->nullable();
            $table->integer('country_id_from')->nullable();
            $table->string('airport_from')->nullable();
            $table->string('terminal_from')->nullable();
            $table->dateTime('arrival_time')->nullable();
            $table->integer('country_id_to')->nullable();
            $table->string('airport_to')->nullable();
            $table->string('terminal_to')->nullable();
            $table->string('airline_id')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('class_type')->nullable();
            $table->string('baggage_allowed')->nullable();
            $table->string('equipment')->nullable();
            $table->string('flight_meal')->nullable();
            $table->string('seat_no')->nullable();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->timestamps();

            $table->foreign('country_id_from')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('country_id_to')->references('id')->on('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_flight_details');
    }
};
