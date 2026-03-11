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
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('state');
            $table->decimal('latitude', 10, 8)->nullable()->after('website');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn(['country_id', 'latitude', 'longitude']);
        });
    }
};
