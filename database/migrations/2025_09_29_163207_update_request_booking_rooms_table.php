<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_booking_rooms', function (Blueprint $table) {
            $table->dropColumn('adults');
            $table->dropColumn('children_ages');

            $table->string('hotel')->after('phone_number');
            $table->string('city')->after('hotel');
            $table->unsignedBigInteger('city_id')->after('city');
            $table->json('occupancy')->after('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('request_booking_rooms', function (Blueprint $table) {
            $table->integer('adults')->default(1);
            $table->string('children_ages')->nullable();

            $table->dropColumn('hotel');
            $table->dropColumn('city');
            $table->dropColumn('city_id');
            $table->dropColumn('occupancy');
        });
        
    }
};
