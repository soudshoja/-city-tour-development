<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS `" . config('database.connections.mysql_map.database') . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

        if (!Schema::connection('mysql_map')->hasTable('countries')) {
            Schema::connection('mysql_map')->create('countries', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary(); // Use the API's ID as primary key
                $table->string('name');
                $table->string('iso', 2)->nullable()->index();
                $table->json('services')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('mysql_map')->hasTable('cities')) {
            Schema::connection('mysql_map')->create('cities', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('name');
                $table->unsignedBigInteger('country_id');
                $table->foreign('country_id')->references('id')->on('countries');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('mysql_map')->hasTable('hotels')) {
            Schema::connection('mysql_map')->create('hotels', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('name');
                $table->string('type')->nullable();
                $table->string('address')->nullable();
                $table->string('telephone')->nullable();
                $table->string('fax')->nullable();
                $table->string('email')->nullable();
                $table->string('zipCode')->nullable();
                $table->tinyInteger('stars')->nullable();
                $table->boolean('recommended')->default(false);
                $table->boolean('specialDeal')->default(false);
                $table->unsignedBigInteger('city_id');
                $table->foreign('city_id')->references('id')->on('cities');
                $table->timestamps();

                // Add indexes for common queries
                $table->index('city_id');
                $table->index('stars');
            });
        }

        if (!Schema::connection('mysql_map')->hasTable('hotel_images')) {
            Schema::connection('mysql_map')->create('hotel_images', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hotel_id');
                $table->foreign('hotel_id')->references('id')->on('hotels');
                $table->string('url');
                $table->string('source')->nullable();
                $table->string('name');
                $table->timestamps();

                // Add index for hotel_id
                $table->index('hotel_id');
            });
        }

        if (!Schema::connection('mysql_map')->hasTable('hotel_descriptions')) {
            Schema::connection('mysql_map')->create('hotel_descriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hotel_id');
                $table->foreign('hotel_id')->references('id')->on('hotels');
                $table->text('description')->nullable();
                $table->string('language', 5)->nullable();
                $table->text('name')->nullable();
                $table->text('address')->nullable();
                $table->text('room_description')->nullable();
                $table->text('location_description')->nullable();
                $table->string('location_description_source')->nullable();
                $table->text('facilities_description')->nullable();
                $table->string('facilities_description_source')->nullable();
                $table->text('description_short')->nullable();
                $table->string('description_short_source')->nullable();
                $table->text('description_full')->nullable();
                $table->string('description_full_source')->nullable();
                $table->text('essential_information')->nullable();
                $table->string('essential_information_source')->nullable();
                $table->timestamps();

                // Add index for hotel_id
                $table->index('hotel_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_map')->dropIfExists([
            'hotels',
            'cities',
            'countries',
        ]);
    }
};
