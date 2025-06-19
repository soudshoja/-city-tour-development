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
        Schema::create('offered_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temp_offer_id')->constrained('temporary_offers')->onDelete('cascade');
            $table->string('room_name');
            $table->string('board_basis')->nullable();
            $table->boolean('non_refundable');
            $table->string('info')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency')->nullable();
            $table->text('room_token');
            $table->text('package_token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offered_rooms');
    }
};
