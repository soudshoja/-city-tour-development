<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('country_code')
                ->nullable()
                ->default('+965')
                ->after('phone')
                ->comment('country code for the client phone number');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->string('country_code')
                ->nullable()
                ->default('+965')
                ->after('phone_number')
                ->comment('country code for the agent phone number');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
