<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->index('name');
            $table->index('city');
            $table->index('country');
            $table->index('country_id');
            $table->index(['name', 'city', 'country']);
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['city']);
            $table->dropIndex(['country']);
            $table->dropIndex(['country_id']);
            $table->dropIndex(['name', 'city', 'country']);
        });
    }
};
