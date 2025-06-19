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
            $table->dropColumn([
                'room_name',
                'board_basis',
                'refundable',
                'room_token',
                'package_token',
                'min_price',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_offers', function (Blueprint $table) {
            $table->string('room_name');
            $table->string('board_basis')->nullable();
            $table->boolean('refundable');
            $table->text('room_token');
            $table->text('package_token');
            $table->decimal('min_price', 10, 2)->default(0);
        });
    }
};
