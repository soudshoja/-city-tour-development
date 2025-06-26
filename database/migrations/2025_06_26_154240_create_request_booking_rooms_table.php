<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->required();
            $table->datetime('check_in')->required();
            $table->datetime('check_out')->required();
            $table->integer('adults')->default(1); 
            $table->string('children_ages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_booking_rooms');
    }
};
