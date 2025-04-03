<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('has_hotel')->default(false)->after('auth_method');            
            $table->boolean('has_flight')->default(false)->after('has_hotel');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('has_hotel');
            $table->dropColumn('has_flight');
        });
    }
};
