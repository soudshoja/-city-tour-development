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
        Schema::create('tbo_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tbo_id')->constrained('tbo');
            $table->string('room_name')->nullable();
            $table->integer('adult_quantity');
            $table->integer('child_quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbo_room');
    }
};
