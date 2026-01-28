<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->char('iso3_code', 3)->nullable()->after('iso_code');
            $table->string('nationality', 100)->nullable()->after('dialing_code');
            $table->string('nationality_ar', 100)->nullable()->after('nationality');
            $table->char('currency_code', 3)->nullable()->after('nationality_ar');
            $table->string('continent', 50)->nullable()->after('currency_code');
            $table->boolean('is_active')->default(true)->after('continent');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn([
                'name_ar',
                'iso3_code',
                'nationality',
                'nationality_ar',
                'currency_code',
                'continent',
                'is_active',
            ]);
        });
    }
};