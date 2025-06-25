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
            $table->text('result_token')->nullable()->after('offer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prebookings', function (Blueprint $table) {
            $table->dropCDolumn('result_token');
        });
    }
};
