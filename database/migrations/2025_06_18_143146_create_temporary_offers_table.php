<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('temporary_offers', function (Blueprint $table) {
            $table->id();
            $table->string('telephone');
            $table->string('enquiry_id', 100);
            $table->string('srk');
            $table->integer('hotel_index');
            $table->text('offer_index');
            $table->string('room_name');
            $table->text('room_token');
            $table->text('result_token');
            $table->text('package_token');
            $table->decimal('min_price', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_offers');
    }
};
