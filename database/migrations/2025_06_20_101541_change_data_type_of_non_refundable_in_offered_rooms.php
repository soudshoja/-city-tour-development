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
        Schema::table('offered_rooms', function (Blueprint $table) {
            $table->text('non_refundable')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offered_rooms', function (Blueprint $table) {
            $table->text('non_refundable')->nullable(false)->change();
        });
    }
};
