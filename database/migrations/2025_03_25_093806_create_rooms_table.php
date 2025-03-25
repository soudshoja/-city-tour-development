<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_hotel_details_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable();
            $table->integer('adult_quantity')->default(1);
            $table->integer('child_quantity')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
