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
        Schema::table('temporary_offers', function (Blueprint $table) {
            $table->string('hotel_name')->after('hotel_index');
            $table->string('board_basis')->after('room_name');
            $table->boolean('refundable')->nullable()->after('board_basis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_offers', function (Blueprint $table) {
            $table->dropColumn('hotel_name');
            $table->dropColumn('board_basis');
            $table->dropColumn('refundable');
        });
    }
};
