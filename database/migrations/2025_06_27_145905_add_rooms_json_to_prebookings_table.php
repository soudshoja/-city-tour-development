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
        Schema::table('prebookings', function (Blueprint $table) {
            $table->json('rooms')->nullable()->after('result_token');

            $table->dropColumn([
                'room_token',
                'room_name',
                'board_basis',
                'non_refundable',
                'price',
                'currency',
                'occupancy'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prebookings', function (Blueprint $table) {
            $table->string('room_token')->nullable();
            $table->string('room_name')->nullable();
            $table->string('board_basis')->nullable();
            $table->boolean('non_refundable')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->json('occupancy')->nullable();

            $table->dropColumn('rooms');
        });
    }
};
