<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_steps', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique()->comment('User phone number');
            $table->integer('step')->default(0)->comment('Current step in the user journey');
            $table->string('hotel')->comment('Hotel name or identifier if applicable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_steps');
    }
};
