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
        Schema::table('map_destinations', function (Blueprint $table) {
            $table->foreign('supplier_destination_id')->references('id')->on('supplier_destinations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_destinations', function (Blueprint $table) {
            $table->dropForeign(['supplier_destination_id']);
        });
    }
};
