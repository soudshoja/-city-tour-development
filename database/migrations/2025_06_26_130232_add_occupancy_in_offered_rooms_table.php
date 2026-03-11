<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offered_rooms', function (Blueprint $table) {
            $table->string('occupancy')->nullable()->after('info');
        });
    }

    public function down(): void
    {
        Schema::table('offered_rooms', function (Blueprint $table) {
            $table->dropColumn('occupancy');
        });
    }
};
