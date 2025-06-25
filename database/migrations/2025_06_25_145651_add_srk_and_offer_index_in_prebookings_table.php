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
            $table->text('srk')->nullable()->after('availability_token');
            $table->text('offer_index')->nullable()->after('hotel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prebookings', function (Blueprint $table) {
            $table->dropCDolumn('srk', 'offer_index');
        });
    }
};
